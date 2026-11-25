<?php

namespace Tests\Unit\GestionHorarios;

use Tests\Support\GestionHorarios\Engine\PrevalidarSesionesCompartidasFicticias;
use PHPUnit\Framework\TestCase;

final class PrevalidarSesionesCompartidasFicticiasTest extends TestCase
{
    private function datos(): array
    {
        $contexto = [
            'origen' => 'PRUEBA_FICTICIA', 'periodo' => 'PERIODO-FICTICIO', 'escuela_id' => 'ESCUELA-FICTICIA', 'planes' => ['PLAN-A', 'PLAN-B'],
            'secciones' => [
                ['id' => 'A', 'periodo' => 'PERIODO-FICTICIO', 'escuela_id' => 'ESCUELA-FICTICIA', 'plan_id' => 'PLAN-A', 'curso_plan_id' => 'CP1', 'oferta_id' => 'O1', 'estudiantes' => ['X', 'Y']],
                ['id' => 'B', 'periodo' => 'PERIODO-FICTICIO', 'escuela_id' => 'ESCUELA-FICTICIA', 'plan_id' => 'PLAN-A', 'curso_plan_id' => 'CP1', 'oferta_id' => 'O1', 'estudiantes' => ['Y', 'Z']],
            ],
            'actividades' => [['id' => 'ACT1', 'secciones' => ['A', 'B'], 'docente_id' => 'D1', 'tipo' => 'T', 'duracion_minutos' => 120]],
            'docentes' => [['id' => 'D1']],
            'ambientes' => [['id' => 'R1', 'activo' => true, 'tipo' => 'AULA', 'capacidad' => 3]],
        ];
        $sesion = ['id' => 'F1', 'actividad_id' => 'ACT1', 'docente_id' => 'D1', 'ambiente_id' => 'R1', 'tipo' => 'T', 'duracion_minutos' => 120, 'participantes' => [['seccion_id' => 'A', 'estudiantes' => ['X', 'Y']], ['seccion_id' => 'B', 'estudiantes' => ['Y', 'Z']]]];

        return [$contexto, $sesion];
    }

    public function test_poblacion_comun_se_cuenta_una_vez_y_no_es_ejecutable(): void
    {
        [$contexto, $sesion] = $this->datos();
        $resultado = (new PrevalidarSesionesCompartidasFicticias)->comprobar($contexto, [$sesion]);
        self::assertSame('PREVALIDADA', $resultado['estado']);
        self::assertFalse($resultado['motor_ejecutable']);
        self::assertSame(3, $resultado['sesiones'][0]['poblacion_unica']);
        self::assertSame(['A', 'B'], $resultado['sesiones'][0]['secciones']);
        self::assertSame('F1', $resultado['sesiones'][0]['id']);
    }

    public function test_rechaza_capacidad_identidad_y_relaciones_incompatibles(): void
    {
        [$contexto, $sesion] = $this->datos();
        $casos = [];
        $c = $contexto;
        $c['ambientes'][0]['capacidad'] = 2;
        $casos[] = [$c, [$sesion], 'AMBIENTE_INCOMPATIBLE'];
        $casos[] = [$contexto, [$sesion, $sesion], 'ID_SESION_INVALIDO_O_DUPLICADO'];
        $c = $contexto;
        $c['secciones'][1]['periodo'] = 'OTRO';
        $casos[] = [$c, [$sesion], 'REFERENCIA_O_POBLACION_INVALIDA'];
        $c = $contexto;
        $c['secciones'][1]['plan_id'] = 'PLAN-B';
        $casos[] = [$c, [$sesion], 'IDENTIDAD_ENTRE_PLANES_NO_RESUELTA'];
        $c = $contexto;
        $c['actividades'][0]['secciones'] = ['A'];
        $casos[] = [$c, [$sesion], 'ACTIVIDAD_O_PARTICIPANTES_INCONSISTENTES'];
        $s = $sesion;
        $s['participantes'][1]['estudiantes'][] = 'AJENO';
        $casos[] = [$contexto, [$s], 'REFERENCIA_O_POBLACION_INVALIDA'];
        $s = $sesion;
        $s['docente_id'] = 'DESCONOCIDO';
        $casos[] = [$contexto, [$s], 'SESION_INCOMPLETA'];
        foreach ($casos as [$c, $ss, $codigo]) {
            $resultado = (new PrevalidarSesionesCompartidasFicticias)->comprobar($c, $ss);
            self::assertSame('INPUT_INVALID', $resultado['estado']);
            self::assertFalse($resultado['motor_ejecutable']);
            self::assertSame([], $resultado['sesiones']);
            self::assertContains($codigo, array_column($resultado['diagnosticos'], 'codigo'));
        }
    }
}
