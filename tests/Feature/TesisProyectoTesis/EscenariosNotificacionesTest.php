<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Services\JuradoService;
use App\Modules\TesisProyectoTesis\Services\NotificacionService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class EscenariosNotificacionesTest extends TesisTestCase
{
    private const ESTADOS = [
        '01' => Estados::REGISTRADO, '02' => Estados::REGISTRADO, '03' => Estados::EN_DESARROLLO, '04' => Estados::EN_DESARROLLO,
        '05' => Estados::EN_DESARROLLO, '06' => Estados::VERIFICADO_ASESOR, '07' => Estados::EN_REVISION_JURADO, '08' => Estados::EN_TESIS,
        '09' => Estados::LISTO_SUSTENTAR, '10' => Estados::PROGRAMADO, '11' => Estados::SUSTENTADO,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // PT-DEMO-08 está en la revisión intermedia (semana 8 o después): no debe depender del día en que se corre.
        $this->irASemanaNueveDelPeriodo();
    }

    private function p(string $n): Proyecto
    {
        return Proyecto::query()->where('codigo', EscenariosTesisSeeder::PREFIJO.$n)->firstOrFail();
    }

    /** Abre el aviso del usuario (por su título) y devuelve la respuesta de la redirección. */
    private function abrir(string $correo, string $codigo)
    {
        $avisos = app(NotificacionService::class)->recientes($this->idUsuario($correo), 50);
        $aviso = collect($avisos)->first(fn ($a) => str_contains($a['titulo'], $codigo)) ?? $avisos[0];

        return $this->como($correo)->post(route('tesis-proyecto-tesis.notificaciones.abrir'), ['n' => $aviso['token']]);
    }

    public function test_escenarios_idempotentes_con_estados_y_pdf_reales(): void
    {
        $this->seed(EscenariosTesisSeeder::class);
        $archivos = DB::table('comun.archivo')->count();
        $this->seed(EscenariosTesisSeeder::class);

        $this->assertSame(11, Proyecto::query()->where('codigo', 'like', 'PT-DEMO-%')->count());
        $this->assertSame($archivos, DB::table('comun.archivo')->count(), 'Al reiniciar no quedan archivos huérfanos.');
        foreach (self::ESTADOS as $n => $estado) {
            $this->assertSame($estado, $this->p($n)->estado, "Escenario $n");
        }
        $this->assertSame(Estados::ETAPA_TESIS, $this->p('08')->etapa);
        $this->assertSame(0, $this->user('nuevo@portal.local')->can('verComoAlumno', $this->p('01')) ? 1 : 0);

        // Cada archivo existe y es un PDF real.
        foreach (DB::table('comun.archivo')->where('ruta_almacenamiento', 'like', 'tesis/demo/%')->pluck('ruta_almacenamiento') as $ruta) {
            Storage::disk('local')->assertExists($ruta);
            $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($ruta));
            $this->assertStringEndsWith("%%EOF\n", Storage::disk('local')->get($ruta));
        }

        // Descargas por la ruta protegida.
        $e = Entrega::query()->where('id_proyecto', $this->p('03')->id_proyecto)->latest('version')->firstOrFail();
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $e->uuid]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->como('est01@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $e->uuid]))->assertForbidden();
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $this->p('01')->uuid]))->assertOk();
        $dictamen = Acta::query()->where('id_proyecto', $this->p('08')->id_proyecto)->firstOrFail();
        $this->como('est08@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['acta-firmada', $dictamen->uuid]))->assertOk();

        // Cada rol tiene algo pendiente.
        $this->como('nuevo@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertSee('Aún no registras tu proyecto de tesis');
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyectos'))->assertSee('PT-DEMO-03')->assertSee('Revisar avance')->assertSee('Verificar con rúbrica');
        $this->assertSame('Intermedia', app(JuradoService::class)->modo($this->p('08'), $this->idDocente('jurado1@portal.local')));
        $this->assertSame('Verificacion', app(JuradoService::class)->modo($this->p('07'), $this->idDocente('jurado2@portal.local')));
        $this->assertNull(app(JuradoService::class)->modo($this->p('07'), $this->idDocente('jurado1@portal.local')));
    }

    public function test_cada_aviso_lleva_a_la_pantalla_exacta_y_queda_leido(): void
    {
        $this->seed(EscenariosTesisSeeder::class);
        $s10 = Sustentacion::query()->where('id_proyecto', $this->p('10')->id_proyecto)->firstOrFail();
        $sol02 = Solicitud::query()->where('id_proyecto', $this->p('02')->id_proyecto)->where('estado', 'Pendiente')->firstOrFail();
        $v2e03 = Entrega::query()->where('id_proyecto', $this->p('03')->id_proyecto)->where('estado', 'Pendiente')->firstOrFail();
        $v2e08 = Entrega::query()->where('id_proyecto', $this->p('08')->id_proyecto)->where('etapa', 'Tesis')->where('estado', 'Pendiente')->firstOrFail();
        $acta11 = Acta::query()->where('id_proyecto', $this->p('11')->id_proyecto)->where('tipo', 'Sustentacion')->firstOrFail();

        $casos = [
            ['director@portal.local', 'PT-DEMO-01', route('tesis-proyecto-tesis.director.asesor.solicitar', $this->p('01'))],
            ['secretaria@portal.local', 'PT-DEMO-02', route('tesis-proyecto-tesis.secretaria.resoluciones', ['solicitud' => $sol02->uuid])],
            ['asesor@portal.local', 'PT-DEMO-03', route('tesis-proyecto-tesis.asesor.revisar', [$this->p('03'), $v2e03])],
            ['est04@portal.local', 'entrega v1', route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $this->p('04'))],
            ['asesor@portal.local', 'Observación respondida · PT-DEMO-04', route('tesis-proyecto-tesis.asesor.proyecto', $this->p('04'))],
            ['asesor@portal.local', 'PT-DEMO-05', route('tesis-proyecto-tesis.asesor.verificar', $this->p('05'))],
            ['director@portal.local', 'PT-DEMO-06', route('tesis-proyecto-tesis.director.jurado', $this->p('06'))],
            ['jurado2@portal.local', 'PT-DEMO-07', route('tesis-proyecto-tesis.jurado.verificar', $this->p('07'))],
            ['jurado1@portal.local', 'PT-DEMO-08', route('tesis-proyecto-tesis.jurado.verificar', $this->p('08'))],
            ['asesor@portal.local', 'PT-DEMO-08', route('tesis-proyecto-tesis.asesor.revisar', [$this->p('08'), $v2e08])],
            ['secretaria@portal.local', 'PT-DEMO-09', route('tesis-proyecto-tesis.secretaria.sustentaciones.programar', $this->p('09'))],
            ['jurado3@portal.local', 'PT-DEMO-10', route('tesis-proyecto-tesis.jurado.resultado', $s10)],
            ['secretaria@portal.local', 'PT-DEMO-11', route('tesis-proyecto-tesis.secretaria.actas.ver', $acta11)],
        ];

        foreach ($casos as [$correo, $titulo, $destino]) {
            $this->assertMatchesRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}-/', $destino, 'El destino usa uuid.');
            $antes = app(NotificacionService::class)->noLeidas($this->idUsuario($correo));
            $this->abrir($correo, $titulo)->assertRedirect($destino);
            $this->assertSame($antes - 1, app(NotificacionService::class)->noLeidas($this->idUsuario($correo)), "$correo · $titulo queda leído");
            $this->como($correo)->get($destino)->assertOk();
        }
    }

    public function test_aviso_con_token_ajeno_o_alterado_no_se_abre(): void
    {
        $p = $this->proyecto(['alumno@portal.local']);
        app(NotificacionService::class)->notificar([$this->idUsuario('director@portal.local')], 'proyecto.registrado', "Nuevo proyecto {$p->codigo}", 'Mensaje', $p);
        $token = app(NotificacionService::class)->recientes($this->idUsuario('director@portal.local'))[0]['token'];

        $this->assertDoesNotMatchRegularExpression('/^\d+$/', $token);
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.notificaciones.abrir'), ['n' => $token])->assertNotFound();
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.notificaciones.abrir'), ['n' => $token.'x'])->assertNotFound();
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.notificaciones.abrir'), ['n' => '1'])->assertNotFound();
        $this->assertSame(1, app(NotificacionService::class)->noLeidas($this->idUsuario('director@portal.local')));

        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.notificaciones.abrir'), ['n' => $token])
            ->assertRedirect(route('tesis-proyecto-tesis.director.asesor.solicitar', $p));
        $this->assertSame(0, app(NotificacionService::class)->noLeidas($this->idUsuario('director@portal.local')));

        $this->app['auth']->forgetGuards();
        $this->post(route('tesis-proyecto-tesis.notificaciones.abrir'), ['n' => $token])->assertRedirect('/login');
    }

    public function test_reiniciar_borra_proyectos_de_ensayo_de_las_cuentas_demo_y_conserva_los_demas(): void
    {
        $this->seed(EscenariosTesisSeeder::class);
        $archivos = DB::table('comun.archivo')->count();

        // Ensayo: nuevo@ registra su proyecto y sube un avance (archivo + aviso); alumno@ tiene el suyo.
        $ensayo = $this->proyecto(['nuevo@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local');
        $this->como('nuevo@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $ensayo), [
            'tipo' => 'Avance', 'numero_semana' => 3, 'comentario' => 'Ensayo', 'archivo' => $this->pdf('ensayo.pdf'),
        ])->assertSessionHasNoErrors();
        $ruta = (string) DB::table('comun.archivo')->orderByDesc('id_archivo')->value('ruta_almacenamiento');
        $propio = $this->proyecto(['alumno@portal.local']);
        $this->assertGreaterThan($archivos, DB::table('comun.archivo')->count());

        $this->seed(EscenariosTesisSeeder::class);

        $this->assertNull(Proyecto::query()->find($ensayo->id_proyecto), 'El proyecto de ensayo de nuevo@ se borra.');
        $this->assertNotNull(Proyecto::query()->find($propio->id_proyecto), 'El proyecto de alumno@ se conserva.');
        $this->assertSame(11, Proyecto::query()->where('codigo', 'like', 'PT-DEMO-%')->count());
        $this->assertSame($archivos, DB::table('comun.archivo')->count(), 'Sin archivos huérfanos del ensayo.');
        Storage::disk('local')->assertMissing($ruta);
        $this->assertSame(0, DB::table('comun.notificacion')->where('referencia_tabla', 'tesis.proyecto_tesis')->where('referencia_id', $ensayo->id_proyecto)->count());
        $this->como('nuevo@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertSee('Aún no registras tu proyecto de tesis');
    }

    public function test_reiniciar_borra_tambien_el_oficio_de_la_solicitud(): void
    {
        $this->seed(EscenariosTesisSeeder::class);
        $archivos = DB::table('comun.archivo')->count();

        // El Director registra la solicitud de PT-DEMO-01: se genera el oficio en PDF.
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.asesor.enviar', $this->p('01')), [
            'destinatario' => 'Decano de la Facultad', 'destinatario_area' => 'Decanato', 'asunto' => 'Designación de asesor',
            'detalle' => 'Solicito la designación de un asesor especialista en la línea del proyecto.', 'docente_sugerido' => '',
        ])->assertSessionHasNoErrors();
        $ruta = (string) DB::table('comun.archivo')->where('ruta_almacenamiento', 'like', 'tesis/oficios/%')->orderByDesc('id_archivo')->value('ruta_almacenamiento');
        $this->assertSame($archivos + 1, DB::table('comun.archivo')->count());

        $this->seed(EscenariosTesisSeeder::class);

        $this->assertSame($archivos, DB::table('comun.archivo')->count(), 'El oficio no queda huérfano al reiniciar.');
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_campana_muestra_avisos_como_formularios_post(): void
    {
        $p = $this->proyecto(['alumno@portal.local']);
        app(NotificacionService::class)->notificar([$this->idUsuario('alumno@portal.local')], 'avance.revisado', 'El asesor revisó tu entrega', 'Mensaje', $p);

        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))
            ->assertOk()->assertSee(route('tesis-proyecto-tesis.notificaciones.abrir'))->assertSee('Ir a la acción');
    }
}
