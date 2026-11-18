<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Database\Seeders\InvestigacionDemoSeeder;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql' || ! str_contains(DB::connection()->getDatabaseName(), '_test_demo_')) {
            $this->markTestSkipped('Requiere una base PostgreSQL exclusiva con _test_demo_ en su nombre.');
        }
        putenv('IF_DEMO_PASSWORD=OnlyForDisposableTests123');
    }

    protected function tearDown(): void
    {
        putenv('IF_DEMO_PASSWORD');
        parent::tearDown();
    }

    public function test_crea_la_topologia_sin_grupos_ni_archivos_y_no_reemplaza_cuentas(): void
    {
        $existente = User::factory()->create();
        $password = $existente->password;
        $roles = $existente->getRoleNames()->all();
        $directorio = sys_get_temp_dir().'/if-demo-export-'.bin2hex(random_bytes(8));
        putenv('IF_DEMO_EXPORT_DIR='.$directorio);
        try {
            $this->seed(InvestigacionDemoSeeder::class);
            $lotes = glob($directorio.'/*', GLOB_ONLYDIR);
            $this->assertCount(1, $lotes);
            foreach (['identidades', 'directores', 'secretarias', 'participantes', 'competencias'] as $nombre) {
                $this->artisan('investigacion:importar-'.$nombre, ['csv' => $lotes[0].'/'.$nombre.'.csv', '--dry-run' => true])->assertExitCode(0);
            }
            $manifest = json_decode(file_get_contents($lotes[0].'/demo.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(12, $manifest['cuentas']);
            $this->assertStringNotContainsString('OnlyForDisposableTests123', file_get_contents($lotes[0].'/demo.json'));
        } finally {
            putenv('IF_DEMO_EXPORT_DIR');
            foreach (glob($directorio.'/*/*') ?: [] as $archivo) {
                unlink($archivo);
            }
            foreach (glob($directorio.'/*', GLOB_ONLYDIR) ?: [] as $lote) {
                rmdir($lote);
            }
            if (is_dir($directorio)) {
                rmdir($directorio);
            }
        }

        $this->assertSame(12, User::where('email', 'like', '%@demo.example.test')->count());
        $this->assertSame(2, DB::table('core.escuela_profesional')->where('codigo', 'like', 'IFD-%')->count());
        $this->assertSame(2, DB::table('core.periodo_academico')->where('anio', now()->year)->count());
        $this->assertSame(5, DB::table('core.seccion')->where('codigo', 'like', 'IFD-%')->count());
        $seccion = (int) DB::table('core.seccion')->where('codigo', 'IFD-completa')->value('id_seccion');
        $this->assertTrue(app(ConfiguracionService::class)->estaConfigurada($seccion));
        $incompleta = (int) DB::table('core.seccion')->where('codigo', 'IFD-incompleta')->value('id_seccion');
        $this->assertFalse(app(ConfiguracionService::class)->estaConfigurada($incompleta));
        $this->assertSame(1, DB::table('core.seccion')->where('estado', 'Cerrada')->count());
        $this->assertSame(2, DB::table('investigacion_formativa.seccion_configuracion')->where('habilitada', false)->count());
        $this->assertSame(3, DB::table('investigacion_formativa.participante_seccion')->where('estado', 'retirado')->count());
        $multi = User::where('email', 'like', 'multirrol.%@demo.example.test')->firstOrFail();
        $this->assertTrue($multi->hasAllRoles(['profesor', 'alumno']));
        $this->assertTrue(Hash::check('OnlyForDisposableTests123', $multi->password));
        $secretaria = User::where('email', 'like', 'secretaria-a.%@demo.example.test')->firstOrFail();
        $this->assertTrue($secretaria->hasRole('secretaria'));
        $this->assertSame(1, DB::table('investigacion_formativa.secretaria_escuela')
            ->where('user_id', $secretaria->id)->where('activo', true)->count());
        foreach (['grupo', 'proyecto', 'entrega', 'evaluacion'] as $tabla) {
            $this->assertSame(0, DB::table('investigacion_formativa.'.$tabla)->count());
        }
        $this->assertSame(0, DB::table('comun.archivo')->count());

        $this->seed(InvestigacionDemoSeeder::class);
        $this->assertSame(24, User::where('email', 'like', '%@demo.example.test')->count());
        $this->assertSame($password, $existente->fresh()->password);
        $this->assertSame($roles, $existente->fresh()->getRoleNames()->all());
    }

    public function test_solicita_una_contrasena_local_antes_de_crear_datos(): void
    {
        putenv('IF_DEMO_PASSWORD');
        $antes = User::count();
        try {
            $this->seed(InvestigacionDemoSeeder::class);
            $this->fail('Se requiere una contraseña de demostración.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('IF_DEMO_PASSWORD', $exception->getMessage());
        }
        $this->assertSame($antes, User::count());
    }
}
