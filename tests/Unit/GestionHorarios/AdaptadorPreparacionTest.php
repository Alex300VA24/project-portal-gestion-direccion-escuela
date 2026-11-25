<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Engine\AdaptadorPreparacion;
use App\Modules\GestionHorarios\Engine\AnalizarAsociacionesOfertaSeccion;
use App\Modules\GestionHorarios\Engine\MapeoAcademicoParcial;
use App\Modules\GestionHorarios\Engine\ValidarEntradaPreparacion;
use DomainException;
use PHPUnit\Framework\TestCase;

final class AdaptadorPreparacionTest extends TestCase
{
    private function datos(): array
    {
        return [
            'periodo' => ['id_periodo' => 1], 'cursos' => [['id_curso_plan' => 10]],
            'ofertas' => [['id_oferta' => 20, 'id_periodo' => 1, 'id_curso_plan' => 10, 'tipo_oferta' => 'REGULAR']],
            'secciones' => [['id_seccion' => 30, 'id_periodo' => 1, 'id_curso_plan' => 10, 'capacidad' => 30], ['id_seccion' => 31, 'id_periodo' => 1, 'id_curso_plan' => 10, 'capacidad' => 30]],
            'actividades' => [
                ['id_actividad' => 40, 'id_seccion' => 30, 'tipo_actividad' => 'TEORIA', 'hora_inicio' => '08:00:00', 'hora_fin' => '09:30:00'],
                ['id_actividad' => 41, 'id_seccion' => 30, 'tipo_actividad' => 'PRACTICA', 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00'],
                ['id_actividad' => 42, 'id_seccion' => 31, 'tipo_actividad' => 'LABORATORIO', 'hora_inicio' => '11:00:00', 'hora_fin' => '13:00:00'],
            ],
            'asignaciones' => [['id_asignacion' => 50, 'id_actividad' => 40, 'id_docente' => 60, 'estado' => 'CONFIRMADA']],
            'docentes' => [['id_docente' => 60]], 'declaraciones' => [['id_declaracion' => 70, 'id_periodo' => 1, 'id_docente' => 60]],
            'disponibilidades' => [['id_disponibilidad' => 80, 'id_periodo' => 1, 'id_docente' => 60, 'id_declaracion' => 70, 'id_bloque' => 90, 'dia_semana' => 'LUNES']],
            'bloques' => [['id_bloque' => 90, 'id_periodo' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00']],
            'ambientes' => [['id_ambiente' => 100, 'tipo' => 'LAB', 'capacidad' => 30]], 'bloqueos' => [['id_bloqueo' => 110, 'id_periodo' => 1, 'id_ambiente' => 100]],
            'restricciones' => [['id_restriccion' => 120, 'severidad' => 'DURA']],
            'demanda' => ['version' => 1, 'cursos' => [['id_curso_plan' => 10, 'total' => 20, 'manana' => 12, 'tarde' => 5]]], 'horarios' => [],
        ];
    }

    private function instantanea(array $datos): array
    {
        return ['contrato' => 'INSTANTANEA_INSTITUCIONAL_V1', 'huella_sha256' => hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'datos' => $datos];
    }

    public function test_conversion_preserva_recursos_y_duracion_y_es_determinista(): void
    {
        $adaptador = new AdaptadorPreparacion;
        $i = $this->instantanea($this->datos());
        $r = $adaptador->convertir($i);
        self::assertSame(json_encode($r), json_encode($adaptador->convertir($i)));
        self::assertSame([90, 60, 120], array_column($r['datos']['actividades'], 'duracion_minutos'));
        self::assertSame([40, 41, 42], array_column($r['datos']['actividades'], 'id_actividad'));
        self::assertSame(12, $r['datos']['demanda']['cursos'][0]['manana']);
        self::assertFalse($r['listo_para_motor']);
        self::assertCount(3, $r['diagnosticos']);
    }

    public function test_mapeo_parcial_conserva_identidades_y_no_inventa_reglas(): void
    {
        $datos = $this->datos();
        $datos['secciones'][0]['codigo'] = 'A';
        $datos['secciones'][1]['codigo'] = 'B';
        $datos['ofertas'][] = ['id_oferta' => 21, 'id_periodo' => 1, 'id_curso_plan' => 10, 'tipo_oferta' => 'REPETICION'];
        $datos['docentes'][] = ['id_docente' => 61];
        $datos['docentes'][] = ['id_docente' => 62];
        $datos['declaraciones'][] = ['id_declaracion' => 71, 'id_periodo' => 1, 'id_docente' => 61, 'estado' => 'PRESENTADA'];
        $preparado = (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
        $mapeo = (new MapeoAcademicoParcial)->convertir($preparado);
        self::assertFalse($mapeo['ejecutable']);
        self::assertSame(['30', '31'], array_column($mapeo['secciones'], 'id'));
        self::assertSame(['T', 'P', 'LAB'], array_column($mapeo['actividades'], 'tipo'));
        self::assertSame([90, 60, 120], array_column($mapeo['actividades'], 'duracion_minutos'));
        self::assertSame($datos['asignaciones'], $mapeo['asignaciones']);
        self::assertSame($datos['bloqueos'], $mapeo['bloqueos']);
        self::assertSame($datos['demanda'], $mapeo['demanda']);
        self::assertContains('OFERTA_NO_CONFIRMADA', array_column($mapeo['diagnosticos'], 'codigo'));
        self::assertContains('DECLARACION_SIN_FRANJAS', array_column($mapeo['diagnosticos'], 'codigo'));
        self::assertContains('SIN_DECLARACION', array_column($mapeo['diagnosticos'], 'codigo'));
        self::assertSame(json_encode($mapeo), json_encode((new MapeoAcademicoParcial)->convertir($preparado)));
    }

    public function test_propuesta_asociaciones_sin_migracion_detecta_conflictos(): void
    {
        $datos = $this->datos();
        $datos['ofertas'][] = ['id_oferta' => 21, 'id_periodo' => 1, 'id_curso_plan' => 10, 'tipo_oferta' => 'REPETICION'];
        $analizador = new AnalizarAsociacionesOfertaSeccion;
        $asociaciones = [['id_oferta' => 20, 'id_seccion' => 30, 'estado' => 'VIGENTE'], ['id_oferta' => 20, 'id_seccion' => 31, 'estado' => 'VIGENTE'], ['id_oferta' => 21, 'id_seccion' => 30, 'estado' => 'CERRADA']];
        self::assertSame([], $analizador->evaluar($datos['ofertas'], $datos['secciones'], $asociaciones));
        $asociaciones[2]['estado'] = 'VIGENTE';
        self::assertContains('SECCION_CON_VARIAS_VIGENTES', array_column($analizador->evaluar($datos['ofertas'], $datos['secciones'], $asociaciones), 'codigo'));
        $datos['secciones'][1]['id_periodo'] = 2;
        $datos['secciones'][1]['id_curso_plan'] = 99;
        $errores = array_column($analizador->evaluar($datos['ofertas'], $datos['secciones'], $asociaciones), 'codigo');
        self::assertContains('PERIODO_DISTINTO', $errores);
        self::assertContains('CURSO_PLAN_DISTINTO', $errores);
        $asociaciones[] = ['id_oferta' => 999, 'id_seccion' => 31, 'estado' => 'VIGENTE'];
        self::assertContains('REFERENCIA_O_ESTADO_INVALIDO', array_column($analizador->evaluar($datos['ofertas'], $datos['secciones'], $asociaciones), 'codigo'));
    }

    public function test_mapeo_aprovecha_asociacion_valida_y_rechaza_inconsistencia(): void
    {
        $datos = $this->datos();
        $datos['oferta_seccion'] = [['id_oferta_seccion' => 200, 'id_seccion' => 30, 'id_oferta' => 20, 'estado' => 'VIGENTE']];
        $preparacion = (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
        $mapeo = (new MapeoAcademicoParcial)->convertir($preparacion);
        self::assertSame('20', $mapeo['secciones'][0]['oferta_id']);
        self::assertSame('REGULAR', $mapeo['secciones'][0]['tipo_oferta']);
        self::assertNull($mapeo['secciones'][1]['oferta_id']);
        $datos['oferta_seccion'][0]['id_oferta'] = 999;
        $preparacion = (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
        $mapeo = (new MapeoAcademicoParcial)->convertir($preparacion);
        self::assertNull($mapeo['secciones'][0]['oferta_id']);
        self::assertContains('OFERTA_INCONSISTENTE', array_column($mapeo['diagnosticos'], 'codigo'));
    }

    public function test_mapeo_informa_asociacion_huerfana_y_no_usa_historial(): void
    {
        $datos = $this->datos();
        $datos['oferta_seccion'] = [
            ['id_oferta_seccion' => 201, 'id_seccion' => 999, 'id_oferta' => 20, 'estado' => 'VIGENTE'],
            ['id_oferta_seccion' => 202, 'id_seccion' => 30, 'id_oferta' => 20, 'estado' => 'CERRADA'],
        ];
        $preparacion = (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
        $mapeo = (new MapeoAcademicoParcial)->convertir($preparacion);
        self::assertNull($mapeo['secciones'][0]['oferta_id']);
        self::assertContains(['entidad' => 'asociacion', 'id' => '201', 'codigo' => 'REFERENCIA_O_ESTADO_INVALIDO'], $mapeo['diagnosticos']);
        self::assertFalse($mapeo['ejecutable']);
    }

    public function test_motor_permanece_cerrado(): void
    {
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->convertir($this->instantanea($this->datos()));
        $this->expectException(DomainException::class);
        $adaptador->paraMotor($entrada);
    }

    public function test_preparacion_institucional_incompleta_devuelve_motivos_sin_entrada(): void
    {
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->convertir($this->instantanea($this->datos()));
        $resultado = $adaptador->diagnosticarMotor($entrada);
        self::assertFalse($resultado['compatible']);
        self::assertNull($resultado['entrada']);
        self::assertContains('POLITICAS_INSTITUCIONALES_PENDIENTES', array_column($resultado['diagnosticos'], 'codigo'));
        self::assertContains('POBLACION_NO_CONFIRMADA', array_column($resultado['diagnosticos'], 'codigo'));
        self::assertContains('CALENDARIO_COMPLETO_AUSENTE', array_column($resultado['diagnosticos'], 'codigo'));
        self::assertContains('INSTANTANEA_NO_VINCULADA', array_column($resultado['diagnosticos'], 'codigo'));
    }

    public function test_los_complementos_de_otro_periodo_o_huella_no_acreditan_el_contrato(): void
    {
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->convertir($this->instantanea($this->datos()));
        $resultado = $adaptador->diagnosticarMotor($entrada, [
            'fuente' => 'prueba', 'version' => '1', 'periodo' => '2', 'huella_instantanea' => str_repeat('0', 64),
            'poblacion' => [], 'participantes_actividad' => [], 'particiones' => [], 'sesiones_fisicas' => [],
            'calendario' => [], 'disponibilidad' => [], 'restricciones' => [], 'patrones' => [],
        ]);
        self::assertFalse($resultado['compatible']);
        self::assertNull($resultado['entrada']);
        self::assertContains('PERIODO_NO_VINCULADO', array_column($resultado['diagnosticos'], 'codigo'));
        self::assertContains('INSTANTANEA_NO_VINCULADA', array_column($resultado['diagnosticos'], 'codigo'));
    }

    public function test_rechaza_actividad_duplicada(): void
    {
        $datos = $this->datos();
        $datos['actividades'][] = $datos['actividades'][0];
        $this->expectException(DomainException::class);
        (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
    }

    public function test_rechaza_referencia_inexistente_y_datos_faltantes(): void
    {
        $datos = $this->datos();
        $datos['asignaciones'][0]['id_docente'] = 999;
        $this->expectException(DomainException::class);
        (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
    }

    public function test_rechaza_duracion_fraccionaria(): void
    {
        $datos = $this->datos();
        $datos['actividades'][0]['hora_fin'] = '09:30:01';
        $this->expectException(DomainException::class);
        (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
    }

    public function test_rechaza_carga_duplicada(): void
    {
        $datos = $this->datos();
        $datos['asignaciones'][] = ['id_asignacion' => 51, 'id_actividad' => 40, 'id_docente' => 60, 'estado' => 'CONFIRMADA'];
        $this->expectException(DomainException::class);
        (new AdaptadorPreparacion)->convertir($this->instantanea($datos));
    }

    public function test_rechaza_formato_faltante_y_huella_alterada(): void
    {
        $adaptador = new AdaptadorPreparacion;
        $datos = $this->datos();
        unset($datos['ambientes']);
        try {
            $adaptador->convertir($this->instantanea($datos));
            self::fail('Debe rechazar datos faltantes.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $entrada = $adaptador->convertir($this->instantanea($this->datos()));
        $entrada['listo_para_motor'] = true;
        try {
            (new ValidarEntradaPreparacion)->validar($entrada);
            self::fail('Debe rechazar la habilitación.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $i = $this->instantanea($this->datos());
        $i['datos']['periodo']['id_periodo'] = 2;
        $this->expectException(DomainException::class);
        $adaptador->convertir($i);
    }
}
