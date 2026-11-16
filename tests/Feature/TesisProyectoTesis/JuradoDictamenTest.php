<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Services\JuradoService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;

final class JuradoDictamenTest extends TesisTestCase
{
    private const CODIGOS = ['Presidente' => 'DOC-102', 'Secretario' => 'DOC-103', 'Vocal' => 'DOC-104'];

    private function verificado(): Proyecto
    {
        return $this->proyecto(['alumno@portal.local', 'alumno2@portal.local'], Estados::VERIFICADO_ASESOR, asesor: 'asesor@portal.local');
    }

    private function revisar(Proyecto $p, string $jurado, array $marcas, array $obs = [], string $accion = 'conformidad')
    {
        return $this->como($jurado)->post(route('tesis-proyecto-tesis.jurado.verificar.guardar', $p), ['marcas' => $marcas, 'obs' => $obs, 'accion' => $accion]);
    }

    public function test_director_asigna_jurado_valido_y_notifica(): void
    {
        $p = $this->verificado();

        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.panel'))->assertSee('Asignar jurado');
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.jurado', $p))->assertOk()->assertSee('Asignar jurado')->assertDontSee('DOC-101');

        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.jurado.asignar', $p), self::CODIGOS)
            ->assertRedirect(route('tesis-proyecto-tesis.director.panel'))->assertSessionHasNoErrors();

        $this->assertSame(Estados::EN_REVISION_JURADO, $p->refresh()->estado);
        $this->assertSame(3, $p->juradosActivos()->count());
        $this->assertSame(['Presidente', 'Secretario', 'Vocal'], $p->juradosActivos()->orderBy('cargo')->pluck('cargo')->all());
        foreach (['jurado1@portal.local', 'jurado2@portal.local', 'jurado3@portal.local'] as $j) {
            $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario($j))->where('tipo_evento', 'jurado.asignado')->exists());
        }
        $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.jurado.proyectos'))->assertOk()->assertSee($p->codigo)->assertSee('Presidente');
    }

    public function test_reglas_de_asignacion(): void
    {
        $p = $this->verificado();
        $ruta = route('tesis-proyecto-tesis.director.jurado.asignar', $p);

        $this->como('director@portal.local')->post($ruta, [...self::CODIGOS, 'Vocal' => 'DOC-102'])->assertSessionHasErrors('Presidente');
        $this->como('director@portal.local')->post($ruta, [...self::CODIGOS, 'Vocal' => 'DOC-101'])->assertSessionHasErrors('Presidente'); // asesor
        $this->como('director@portal.local')->post($ruta, [...self::CODIGOS, 'Vocal' => 'NO-EXISTE'])->assertSessionHasErrors('Vocal');
        $this->como('director@portal.local')->post($ruta, ['Presidente' => 'DOC-102'])->assertSessionHasErrors(['Secretario', 'Vocal']);
        $this->assertSame(0, JuradoProyecto::count());

        $otro = $this->proyecto(['alumno3@portal.local'], Estados::EN_DESARROLLO, asesor: 'asesor@portal.local');
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.jurado.asignar', $otro), self::CODIGOS)->assertSessionHasErrors('Presidente');

        $this->como('director@portal.local')->post($ruta, self::CODIGOS)->assertSessionHasNoErrors();
        $this->como('director@portal.local')->post($ruta, self::CODIGOS)->assertSessionHasErrors('Presidente');
        $this->assertSame(3, JuradoProyecto::count());
    }

    public function test_tres_conformes_generan_dictamen_y_pasan_a_tesis(): void
    {
        $p = $this->verificado();
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.jurado.asignar', $p), self::CODIGOS);
        $v1 = $this->entrega($p);

        // El vocal observa; presidente y secretario conformes.
        $this->revisar($p, 'jurado3@portal.local', $this->marcas('Proyecto', [8 => 'no_cumple']), [8 => 'Precisa la métrica de validación'], 'observaciones')->assertSessionHasNoErrors();
        $this->revisar($p, 'jurado1@portal.local', $this->marcas())->assertSessionHasNoErrors();
        $this->revisar($p, 'jurado2@portal.local', $this->marcas())->assertSessionHasNoErrors();
        $this->assertSame(Estados::EN_REVISION_JURADO, $p->refresh()->estado);
        $this->assertSame(0, Acta::count());

        // El jurado ve el avance de los demás sin su detalle.
        $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))
            ->assertOk()->assertSee('Con observaciones')->assertSee('Conforme')->assertDontSee('Precisa la métrica de validación');

        // Ya conforme: no vuelve a verificar.
        $this->revisar($p, 'jurado1@portal.local', $this->marcas())->assertSessionHasErrors('marcas');

        // Los estudiantes corrigen y suben nueva versión; el vocal da conformidad.
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), ['tipo' => 'Final', 'numero_semana' => 14, 'archivo' => $this->pdf()])->assertSessionHasNoErrors();
        $this->revisar($p, 'jurado3@portal.local', $this->marcas())->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(Estados::EN_TESIS, $p->estado);
        $this->assertSame(Estados::ETAPA_TESIS, $p->etapa);
        $this->assertNotNull($p->fecha_aprobacion_proyecto);
        $this->assertNotNull($p->id_periodo_tesis);
        $dictamen = Acta::query()->where('tipo', 'Dictamen')->firstOrFail();
        $this->assertMatchesRegularExpression('/^DIC-\d{4}-\d{3}$/', $dictamen->numero);
        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_nuevo' => 'Aprobado']);
        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_anterior' => 'Aprobado', 'estado_nuevo' => 'EnTesis']);
        $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('secretaria@portal.local'))->where('tipo_evento', 'dictamen.generado')->exists());

        // Secretaría: dictamen imprimible, firmado e informado.
        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.actas'))->assertOk()->assertSee($dictamen->numero);
        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.actas.ver', $dictamen))->assertOk()
            ->assertSee('DICTAMEN DE APROBACIÓN DEL PROYECTO DE TESIS')->assertSee('reúne las condiciones para ser ejecutado')->assertSee('Dra. Ana Paredes Luna');
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.informar', $dictamen))->assertSessionHasErrors('informar');
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.firmada', $dictamen), ['archivo' => $this->pdf('dictamen_firmado.pdf')])->assertSessionHasNoErrors();
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.informar', $dictamen))->assertSessionHasNoErrors();
        $this->assertNotNull($dictamen->refresh()->informado_director_en);
        $this->assertSame(Estados::EN_TESIS, $p->refresh()->estado);
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['acta-firmada', $dictamen->uuid]))->assertOk();
    }

    public function test_revision_intermedia_desde_semana_8_es_referencial_y_unica(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_TESIS, Estados::ETAPA_TESIS, 'asesor@portal.local', $this->juradoDemo());
        $p->update(['id_periodo_tesis' => app(\App\Modules\TesisProyectoTesis\Services\CatalogoService::class)->periodoActual()->id_periodo]);
        $this->assertGreaterThanOrEqual(8, app(\App\Modules\TesisProyectoTesis\Services\SeguimientoService::class)->semanaActual($p), 'El periodo demo debe estar en la semana 8 o después.');

        // Sin entregas en Tesis no hay revisión intermedia.
        $this->assertNull(app(JuradoService::class)->modo($p, $this->idDocente('jurado1@portal.local')));

        // Al subir un avance se avisa al jurado una sola vez.
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), ['tipo' => 'Avance', 'numero_semana' => 8, 'archivo' => $this->pdf()])->assertSessionHasNoErrors();
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), ['tipo' => 'Avance', 'numero_semana' => 9, 'archivo' => $this->pdf()])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('jurado1@portal.local'))->where('tipo_evento', JuradoService::EVENTO_INTERMEDIA)->count());

        $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertOk()->assertSee('Revisión intermedia (referencial)')->assertSee('25 criterios');
        $this->revisar($p, 'jurado1@portal.local', $this->marcas('Tesis', [21 => 'no_cumple']), [21 => 'Faltan tablas interpretadas'], 'referencial')->assertSessionHasNoErrors();

        $r = Revision::query()->where('tipo', 'Intermedia')->firstOrFail();
        $this->assertTrue($r->referencial);
        $this->assertFalse($r->conforme);
        $this->assertSame(Estados::EN_TESIS, $p->refresh()->estado, 'La revisión intermedia no bloquea.');
        $this->revisar($p, 'jurado1@portal.local', $this->marcas('Tesis'), [], 'referencial')->assertSessionHasErrors('marcas');
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $p))->assertSee('Faltan tablas interpretadas')->assertSee('referencial');
    }

    public function test_revision_final_de_tesis_con_tres_conformes_queda_lista_para_sustentar(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_REVISION_JURADO, Estados::ETAPA_TESIS, 'asesor@portal.local', $this->juradoDemo());
        $this->entrega($p);

        foreach (['jurado1@portal.local', 'jurado2@portal.local', 'jurado3@portal.local'] as $j) {
            $this->revisar($p, $j, $this->marcas('Tesis'))->assertSessionHasNoErrors();
        }

        $this->assertSame(Estados::LISTO_SUSTENTAR, $p->refresh()->estado);
        $this->assertSame(0, Acta::query()->where('tipo', 'Dictamen')->count());
    }

    public function test_asesor_en_tesis_envia_directo_a_revision_final_del_mismo_jurado(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_TESIS, Estados::ETAPA_TESIS, 'asesor@portal.local', $this->juradoDemo());
        $this->entrega($p);

        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => $this->marcas('Tesis'), 'accion' => 'conformidad'])->assertSessionHasNoErrors();
        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.similitud.registrar', $p), ['porcentaje' => '12', 'archivo' => $this->pdf(), 'conformidad' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(Estados::EN_REVISION_JURADO, $p->refresh()->estado);
        $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('jurado2@portal.local'))->where('tipo_evento', 'jurado.revision_final')->exists());
    }

    public function test_reemplazo_de_jurado_guarda_historial(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_TESIS, Estados::ETAPA_TESIS, 'asesor@portal.local', $this->juradoDemo());
        $ruta = route('tesis-proyecto-tesis.director.jurado.reemplazar', $p);

        $this->como('director@portal.local')->post($ruta, ['cargo' => 'Vocal', 'docente' => 'DOC-101', 'motivo' => 'Licencia médica'])->assertSessionHasErrors('docente');
        $this->como('director@portal.local')->post($ruta, ['cargo' => 'Vocal', 'docente' => 'DOC-102', 'motivo' => 'Licencia médica'])->assertSessionHasErrors('docente');
        $this->como('director@portal.local')->post($ruta, ['cargo' => 'Vocal', 'docente' => 'DOC-105', 'motivo' => ''])->assertSessionHasErrors('motivo');

        $this->como('director@portal.local')->post($ruta, ['cargo' => 'Vocal', 'docente' => 'DOC-105', 'motivo' => 'Licencia médica'])->assertSessionHasNoErrors();
        $this->assertSame(3, $p->juradosActivos()->count());
        $this->assertSame($this->idDocente('profesor@portal.local'), (int) $p->juradosActivos()->where('cargo', 'Vocal')->value('id_docente'));
        $this->assertDatabaseHas('tesis.jurado_proyecto', ['id_proyecto' => $p->id_proyecto, 'id_docente' => $this->idDocente('jurado3@portal.local'), 'estado' => 'Reemplazado', 'motivo_cambio' => 'Licencia médica']);
        $this->como('jurado3@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertForbidden();
    }

    public function test_autorizacion_del_jurado_y_del_director(): void
    {
        $p = $this->proyecto(['alumno@portal.local'], Estados::EN_REVISION_JURADO, asesor: 'asesor@portal.local', jurados: $this->juradoDemo());
        $this->entrega($p);

        $this->como('profesor@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertForbidden();
        $this->revisar($p, 'profesor@portal.local', $this->marcas())->assertForbidden();
        $this->revisar($p, 'asesor@portal.local', $this->marcas())->assertForbidden();
        $this->como('jurado1@portal.local')->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => $this->marcas(), 'accion' => 'conformidad'])->assertForbidden();
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.jurado.proyectos'))->assertForbidden();
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.director.jurado.asignar', $p), self::CODIGOS)->assertForbidden();
        $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.secretaria.actas'))->assertForbidden();
        $this->assertSame(0, Revision::count());
    }
}
