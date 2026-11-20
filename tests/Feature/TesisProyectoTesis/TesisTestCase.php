<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Models\User;
use App\Modules\TesisProyectoTesis\Database\Seeders\TesisDatabaseSeeder;
use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ProyectoEstudiante;
use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base de las pruebas del módulo: requiere PostgreSQL migrado (module:migrate all),
 * corre cada prueba en una transacción y siembra los datos demo del módulo.
 */
abstract class TesisTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }

        $this->withoutVite();
        Storage::fake('local');
        $this->seed(TesisDatabaseSeeder::class);
    }

    /**
     * Fija el reloj en la semana 9 del periodo demo. Las reglas que dependen de la semana del ciclo
     * (revisión intermedia desde la semana 8) dan así el mismo resultado cualquier día del año.
     */
    protected function irASemanaNueveDelPeriodo(): \App\Shared\Models\Core\PeriodoAcademico
    {
        $periodo = app(CatalogoService::class)->periodoActual();
        $this->travelTo($periodo->fecha_inicio->copy()->addWeeks(8)->addDay()->setTime(10, 0));

        return $periodo;
    }

    protected function user(string $correo): User
    {
        return User::query()->where('email', $correo)->firstOrFail();
    }

    protected function como(string $correo): static
    {
        app(IdentidadService::class)->olvidar();

        return $this->actingAs($this->user($correo));
    }

    protected function idEstudiante(string $correo): int
    {
        return (int) app(IdentidadService::class)->actual($this->user($correo))->idEstudiante;
    }

    protected function idDocente(string $correo): int
    {
        return (int) app(IdentidadService::class)->actual($this->user($correo))->idDocente;
    }

    protected function idUsuario(string $correo): int
    {
        return (int) app(IdentidadService::class)->actual($this->user($correo))->idUsuario;
    }

    protected function idLinea(string $codigo = 'LI-SW'): int
    {
        return (int) DB::table('core.linea_investigacion')->where('codigo', $codigo)->value('id_linea');
    }

    protected function pdf(string $nombre = 'documento.pdf', int $kb = 120): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, $kb, 'application/pdf');
    }

    /**
     * Crea un proyecto directamente (sin pasar por el flujo) para preparar escenarios.
     *
     * @param  list<string>  $alumnos
     */
    protected function proyecto(array $alumnos = ['alumno@portal.local'], string $estado = Estados::REGISTRADO, string $etapa = Estados::ETAPA_PROYECTO, ?string $asesor = null, array $jurados = []): Proyecto
    {
        $proyecto = Proyecto::create([
            'codigo' => 'PT-TEST-'.Str::upper(Str::random(6)),
            'titulo' => 'Sistema web de prueba para la gestión de tesis',
            'resumen' => 'Resumen de prueba.',
            'id_linea' => $this->idLinea(),
            'id_periodo_registro' => app(CatalogoService::class)->periodoActual()->id_periodo,
            'etapa' => $etapa,
            'estado' => $estado,
            'enfoque' => 'Cuantitativo',
            'tipo_investigacion' => 'Aplicada',
            'diseno' => 'Pre-experimental',
            'lugar_ejecucion' => 'Trujillo',
            'institucion' => 'Universidad Nacional de Trujillo',
            'duracion_meses' => 8,
            'financiamiento' => 'Autofinanciado',
        ])->refresh();

        foreach (array_values($alumnos) as $i => $correo) {
            ProyectoEstudiante::create(['id_proyecto' => $proyecto->id_proyecto, 'id_estudiante' => $this->idEstudiante($correo), 'orden_autor' => $i + 1, 'estado' => 'Activo']);
        }

        if ($asesor !== null) {
            AsesorProyecto::create(['id_proyecto' => $proyecto->id_proyecto, 'id_docente' => $this->idDocente($asesor), 'tipo' => 'Asesor', 'estado' => 'Activo', 'fecha_inicio' => now()->toDateString()]);
        }

        foreach ($jurados as $cargo => $correo) {
            JuradoProyecto::create(['id_proyecto' => $proyecto->id_proyecto, 'id_docente' => $this->idDocente($correo), 'cargo' => $cargo, 'estado' => 'Activo', 'fecha_asignacion' => now()->toDateString()]);
        }

        return $proyecto;
    }

    /** Crea una entrega directamente (sin pasar por la pantalla). */
    protected function entrega(Proyecto $p, string $tipo = 'Final', string $estado = 'Pendiente', string $alumno = 'alumno@portal.local'): \App\Modules\TesisProyectoTesis\Models\Entrega
    {
        $version = (int) \App\Modules\TesisProyectoTesis\Models\Entrega::query()->where('id_proyecto', $p->id_proyecto)->where('etapa', $p->etapa)->max('version') + 1;

        return \App\Modules\TesisProyectoTesis\Models\Entrega::create([
            'id_proyecto' => $p->id_proyecto, 'etapa' => $p->etapa, 'version' => $version, 'tipo' => $tipo,
            'numero_semana' => 10, 'estado' => $estado, 'entregado_por' => $this->idEstudiante($alumno),
            'id_archivo' => app(\App\Modules\TesisProyectoTesis\Services\ArchivoService::class)->guardar($this->pdf(), $this->idUsuario($alumno)),
        ])->refresh();
    }

    /**
     * Marcas de la rúbrica para la etapa: todo «cumple» salvo lo indicado.
     *
     * @param  array<int, string>  $cambios  orden => marca
     */
    protected function marcas(string $etapa = 'Proyecto', array $cambios = []): array
    {
        $ordenes = app(\App\Modules\TesisProyectoTesis\Services\RubricaService::class)->criterios($etapa)->keys()->all();

        return array_replace(array_fill_keys($ordenes, 'cumple'), $cambios);
    }

    /** Jurado demo estándar. */
    protected function juradoDemo(): array
    {
        return ['Presidente' => 'jurado1@portal.local', 'Secretario' => 'jurado2@portal.local', 'Vocal' => 'jurado3@portal.local'];
    }
}
