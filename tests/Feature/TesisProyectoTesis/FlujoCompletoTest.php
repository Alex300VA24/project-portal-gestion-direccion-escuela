<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Carbon;

/**
 * Recorrido de punta a punta (07_plan.md), todo por HTTP y con los usuarios demo:
 * registro → asesor → avances y observaciones → rúbrica y Turnitin → jurado → dictamen y Tesis →
 * revisión intermedia → verificación final → sustentación por mayoría → acta firmada.
 */
final class FlujoCompletoTest extends TesisTestCase
{
    private const JURADOS = ['jurado1@portal.local', 'jurado2@portal.local', 'jurado3@portal.local'];

    public function test_recorrido_completo_de_las_dos_etapas(): void
    {
        // 1. El alumno registra el proyecto con su coautor.
        $this->como('alumno@portal.local')->post(route('tesis-proyecto-tesis.alumno.proyecto.guardar'), [
            'titulo' => 'Sistema web con asistente conversacional para la orientación académica de ingresantes',
            'resumen' => 'Asistente conversacional para orientar a los ingresantes de la escuela.',
            'linea' => 'LI-SW', 'enfoque' => 'Cuantitativo', 'tipo_investigacion' => 'Aplicada', 'diseno' => 'Pre-experimental',
            'lugar_ejecucion' => 'Trujillo', 'institucion' => 'UNT', 'financiamiento' => 'Autofinanciado',
            'coautor_codigo' => '1027004588',
            'actividades' => [['descripcion' => 'Revisión bibliográfica', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30', 'horas' => 8]],
            'documento' => $this->pdf('F1.pdf'),
        ])->assertSessionHasNoErrors();
        $p = Proyecto::query()->latest('id_proyecto')->firstOrFail();
        $this->assertEstado($p, Estados::REGISTRADO, Estados::ETAPA_PROYECTO);

        // 2. El Director solicita el asesor al Decanato.
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.asesor.enviar', $p), [
            'destinatario' => 'Decano', 'destinatario_area' => 'Decanato', 'asunto' => 'Designación de asesor',
            'detalle' => 'Solicito la designación de un asesor especialista en la línea.', 'docente_sugerido' => 'DOC-101',
        ])->assertSessionHasNoErrors();

        // 3. La Secretaría registra la resolución.
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', Solicitud::query()->firstOrFail()), [
            'numero' => '0451-2026-FCFYM', 'fecha' => Carbon::today()->toDateString(), 'organo_emisor' => 'Decanato', 'dirigido_a' => 'Director',
            'docente' => 'DOC-101', 'asunto' => 'Designación de asesor', 'archivo' => $this->pdf('resolucion.pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertEstado($p, Estados::CON_ASESOR);

        // 4. El alumno sube dos avances; el asesor observa el primero; el coautor responde; el asesor levanta.
        $v1 = $this->subir($p, 'alumno@portal.local', 'Avance', 5);
        $this->assertEstado($p, Estados::EN_DESARROLLO);
        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.revisar.guardar', [$p, $v1]), [
            'dictamen' => 'Observado', 'observaciones' => [['seccion' => 'Matriz de consistencia', 'descripcion' => 'Define las dimensiones de la variable.']],
        ])->assertSessionHasNoErrors();
        $v2 = $this->subir($p, 'alumno2@portal.local', 'Avance', 6);
        $obs = Observacion::query()->firstOrFail();
        $this->como('alumno2@portal.local')->post(route('tesis-proyecto-tesis.alumno.observaciones.responder', $obs->uuid), [
            'respuesta' => 'Se agregaron las dimensiones en la tabla 3.', 'entrega' => $v2->uuid,
        ])->assertSessionHasNoErrors();
        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.revisar.guardar', [$p, $v2]), [
            'dictamen' => 'SinObservaciones', 'levantar' => [$obs->uuid],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Levantada', $obs->refresh()->estado);

        // 5. Versión final: el asesor verifica con la rúbrica (18 criterios) y pasa Turnitin.
        $this->subir($p, 'alumno@portal.local', 'Final', 7);
        $this->asesorConforme($p, 'Proyecto', '14');
        $this->assertEstado($p, Estados::VERIFICADO_ASESOR);

        // 6. El Director asigna el jurado.
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.jurado.asignar', $p), [
            'Presidente' => 'DOC-102', 'Secretario' => 'DOC-103', 'Vocal' => 'DOC-104',
        ])->assertSessionHasNoErrors();
        $this->assertEstado($p, Estados::EN_REVISION_JURADO);

        // 7. Los tres jurados conformes → dictamen y etapa Tesis.
        foreach (self::JURADOS as $j) {
            $this->jurado($p, $j, 'Proyecto', 'conformidad');
        }
        $this->assertEstado($p, Estados::EN_TESIS, Estados::ETAPA_TESIS);
        $dictamen = Acta::query()->where('tipo', 'Dictamen')->firstOrFail();

        // 8. Revisión intermedia (semana 8 o posterior del periodo demo), referencial.
        $this->subir($p, 'alumno@portal.local', 'Avance', 8);
        $this->jurado($p, 'jurado2@portal.local', 'Tesis', 'referencial', [21 => 'no_cumple'], [21 => 'Faltan tablas interpretadas']);
        $this->assertEstado($p, Estados::EN_TESIS);

        // 9. Verificación final: asesor (25 criterios) + Turnitin → revisión final del mismo jurado → 3 conformes.
        $this->subir($p, 'alumno2@portal.local', 'Final', 12);
        $this->asesorConforme($p, 'Tesis', '12.5');
        $this->assertEstado($p, Estados::EN_REVISION_JURADO, Estados::ETAPA_TESIS);
        foreach (self::JURADOS as $j) {
            $this->jurado($p, $j, 'Tesis', 'conformidad');
        }
        $this->assertEstado($p, Estados::LISTO_SUSTENTAR);

        // 10. La Secretaría programa; jurados: 2 aprobatorios y 1 desaprobatorio → aprobado por mayoría.
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.sustentaciones.guardar', $p), [
            'fecha' => Carbon::today()->toDateString(), 'hora' => '08:00', 'ambiente' => 'INFO-5',
        ])->assertSessionHasNoErrors();
        $this->assertEstado($p, Estados::PROGRAMADO);
        $s = Sustentacion::query()->firstOrFail();
        foreach (['jurado1@portal.local' => 'Aprobatorio', 'jurado2@portal.local' => 'Desaprobatorio', 'jurado3@portal.local' => 'Aprobatorio'] as $j => $r) {
            $this->como($j)->post(route('tesis-proyecto-tesis.jurado.resultado.registrar', $s), ['resultado' => $r, 'hora_fin' => '10:00'])->assertSessionHasNoErrors();
        }
        $this->assertSame('Mayoria', $s->refresh()->modo_resultado);
        $this->assertEstado($p, Estados::SUSTENTADO);

        // 11. Acta generada, firmada e informada → Tesis aprobada.
        $acta = Acta::query()->where('tipo', 'Sustentacion')->firstOrFail();
        $this->assertSame('Aprobado por mayoría', $acta->resultado);
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.firmada', $acta), ['archivo' => $this->pdf('acta_firmada.pdf')])->assertSessionHasNoErrors();
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.actas.informar', $acta))->assertSessionHasNoErrors();
        $this->assertEstado($p, Estados::TESIS_APROBADA);

        // Trazabilidad completa en el historial.
        $this->assertSame([
            'Registrado', 'ConAsesor', 'EnDesarrollo', 'VerificadoAsesor', 'EnRevisionJurado', 'Aprobado', 'EnTesis',
            'EnRevisionJurado', 'ListoSustentar', 'Programado', 'Sustentado', 'TesisAprobada',
        ], $p->historial()->pluck('estado_nuevo')->all());
        $this->assertNotNull($dictamen->numero);

        // Paneles finales.
        $this->como('alumno2@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertOk()->assertSee('Tesis aprobada');
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.panel'))->assertOk()->assertSee($acta->numero);
    }

    private function subir(Proyecto $p, string $alumno, string $tipo, int $semana): Entrega
    {
        $this->como($alumno)->post(route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $p), [
            'tipo' => $tipo, 'numero_semana' => $semana, 'archivo' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        return Entrega::query()->where('id_proyecto', $p->id_proyecto)->latest('id_entrega')->firstOrFail();
    }

    private function asesorConforme(Proyecto $p, string $etapa, string $porcentaje): void
    {
        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), ['marcas' => $this->marcas($etapa), 'accion' => 'conformidad'])
            ->assertSessionHasNoErrors();
        $this->como('asesor@portal.local')->post(route('tesis-proyecto-tesis.asesor.similitud.registrar', $p), [
            'porcentaje' => $porcentaje, 'archivo' => $this->pdf('turnitin.pdf'), 'conformidad' => '1',
        ])->assertSessionHasNoErrors();
    }

    private function jurado(Proyecto $p, string $jurado, string $etapa, string $accion, array $cambios = [], array $obs = []): void
    {
        $this->como($jurado)->post(route('tesis-proyecto-tesis.jurado.verificar.guardar', $p), [
            'marcas' => $this->marcas($etapa, $cambios), 'obs' => $obs, 'accion' => $accion,
        ])->assertSessionHasNoErrors();
    }

    private function assertEstado(Proyecto $p, string $estado, ?string $etapa = null): void
    {
        $p->refresh();
        $this->assertSame($estado, $p->estado);
        if ($etapa !== null) {
            $this->assertSame($etapa, $p->etapa);
        }
    }
}
