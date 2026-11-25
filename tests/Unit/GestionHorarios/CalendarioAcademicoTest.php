<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Services\CalendarioAcademico;
use App\Modules\GestionHorarios\Services\SesionesCandidatas;
use PHPUnit\Framework\TestCase;

final class CalendarioAcademicoTest extends TestCase
{
    private function periodo(): array
    {
        return ['id_periodo' => 1, 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-07-01'];
    }

    private function configuracion(): array
    {
        return ['validacion' => 'CONFIRMADA', 'dias' => ['LUNES', 'SABADO'], 'recesos' => [['inicio' => '10:00', 'fin' => '11:00']], 'feriados' => [], 'sabado_regular' => false, 'sabado_repeticion' => true];
    }

    private function bloques(): array
    {
        return [['hora_inicio' => '08:00', 'hora_fin' => '10:00'], ['hora_inicio' => '10:00', 'hora_fin' => '12:00']];
    }

    public function test_calendario_inexistente_parcial_y_completo_ficticio(): void
    {
        $s = new CalendarioAcademico;
        self::assertSame('INEXISTENTE', $s->evaluar(null, [], [])['estado']);
        self::assertSame('INEXISTENTE', $s->evaluar($this->periodo(), [], [])['estado']);
        self::assertSame('PARCIAL', $s->evaluar($this->periodo(), $this->bloques(), [['id_actividad' => 1]])['estado']);
        $r = $s->evaluar($this->periodo(), $this->bloques(), [], $this->configuracion());
        self::assertSame('COMPLETO', $r['estado']);
        self::assertSame([], $r['candidatos']);
    }

    public function test_detecta_solapamiento_duplicado_y_franja_invalida(): void
    {
        $bloques = [...$this->bloques(), ['hora_inicio' => '09:00', 'hora_fin' => '11:00'], ['hora_inicio' => '08:00', 'hora_fin' => '10:00'], ['hora_inicio' => '12:00', 'hora_fin' => '11:00']];
        $r = (new CalendarioAcademico)->evaluar($this->periodo(), $bloques, [], $this->configuracion());
        self::assertSame('INCONSISTENTE', $r['estado']);
        foreach (['BLOQUES_SUPERPUESTOS', 'BLOQUE_DUPLICADO', 'BLOQUE_INVALIDO'] as $codigo) {
            self::assertContains($codigo, $r['diagnosticos']);
        }
    }

    public function test_ensayo_exige_patron_y_declaracion_y_descarta_receso_y_sabado_regular(): void
    {
        $cal = (new CalendarioAcademico)->evaluar($this->periodo(), $this->bloques(), [], $this->configuracion());
        $actividad = ['id_actividad' => 1, 'id_seccion' => 2, 'id_docente' => 3, 'tipo' => 'T', 'repeticion' => false, 'duraciones_sesion' => [60]];
        $disp = ['id_periodo' => 1, 'id_docente' => 3, 'estado' => 'PRESENTADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => '08:00', 'fin' => '12:00'], ['dia' => 'SABADO', 'inicio' => '08:00', 'fin' => '12:00']]];
        $s = new SesionesCandidatas;
        self::assertSame('NO_VERIFICABLE', $s->ensayar($cal, $actividad, null, [])['estado']);
        self::assertSame([], $s->ensayar($cal, $actividad, ['id_periodo' => 1, 'id_docente' => 3, 'estado' => 'PRESENTADA', 'franjas' => []], [])['candidatos']);
        $resultado = $s->ensayar($cal, $actividad, $disp, []);
        self::assertSame('PARCIAL', $resultado['estado']);
        self::assertCount(1, $resultado['candidatos']);
        self::assertSame('LUNES', $resultado['candidatos'][0]['dia']);
        $otraDeclaracion = $disp;
        $otraDeclaracion['id_docente'] = 99;
        self::assertSame('NO_VERIFICABLE', $s->ensayar($cal, $actividad, $otraDeclaracion, [])['estado']);
        $actividad['repeticion'] = true;
        self::assertCount(2, $s->ensayar($cal, $actividad, $disp, [])['candidatos']);
        self::assertSame('NO_VERIFICABLE', $s->ensayar($cal, $actividad, $disp, [['dia' => 'LUNES']])['estado']);
        $actividad['duraciones_sesion'] = [0];
        self::assertSame('NO_VERIFICABLE', $s->ensayar($cal, $actividad, $disp, [])['estado']);
        unset($actividad['duraciones_sesion']);
        self::assertSame('NO_VERIFICABLE', $s->ensayar($cal, $actividad, $disp, [])['estado']);
    }
}
