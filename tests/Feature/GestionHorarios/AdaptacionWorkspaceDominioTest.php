<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\BorradorSemana;
use App\Modules\GestionHorarios\Services\PlanificarSeccionesPorDemanda;
use PHPUnit\Framework\TestCase;

final class AdaptacionWorkspaceDominioTest extends TestCase
{
    public function test_propone_solo_codigos_libres_y_conserva_secciones(): void
    {
        $secciones = [(object) ['codigo' => 'A', 'estado' => 'ACTIVA', 'capacidad' => 40], (object) ['codigo' => 'B', 'estado' => 'CERRADA', 'capacidad' => 40]];
        $propuesta = PlanificarSeccionesPorDemanda::propuesta(81, 40, $secciones, 'Estructura de datos');
        self::assertSame(['C', 'D'], $propuesta['codigos']);
        self::assertSame(41, $propuesta['faltantes']);
        self::assertSame(40, $secciones[0]->capacidad);
        self::assertSame([], PlanificarSeccionesPorDemanda::propuesta(30, 40, $secciones, 'Estructura de datos')['codigos']);
    }

    public function test_rechaza_grupos_especiales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanificarSeccionesPorDemanda::propuesta(20, 40, [], 'Proyecto de tesis');
    }

    public function test_rechaza_demanda_ausente(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanificarSeccionesPorDemanda::propuesta(null, 40, [], 'Programación');
    }

    public function test_mover_conserva_duracion_y_original(): void
    {
        $original = [['id_actividad' => 1, 'dia_semana' => 'LUNES', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00', 'curso_nombre' => 'Curso A', 'tipo_actividad' => 'TEORIA']];
        $bloques = [['hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00'], ['hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00']];
        $nuevo = BorradorSemana::mover($original, $bloques, '1', 'MARTES', '09:00:00');
        self::assertSame('11:00:00', $nuevo[0]['hora_fin']);
        self::assertSame('MARTES', $nuevo[0]['dia_semana']);
        self::assertSame('07:00:00', $original[0]['hora_inicio']);
        self::assertNotSame(BorradorSemana::huella($original, $bloques), BorradorSemana::huella($nuevo, $bloques));
    }

    public function test_no_cruza_receso(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BorradorSemana::mover([['id_actividad' => 1, 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00']], [['hora_inicio' => '12:00:00', 'hora_fin' => '13:00:00'], ['hora_inicio' => '14:00:00', 'hora_fin' => '15:00:00']], '1', 'MARTES', '12:00:00');
    }

    public function test_coincidencias_son_avisos_no_conflictos_definitivos(): void
    {
        $base = ['dia_semana' => 'LUNES', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:00:00', 'tipo_actividad' => 'LABORATORIO'];
        self::assertCount(1, BorradorSemana::coincidencias([$base + ['curso_nombre' => 'A'], $base + ['curso_nombre' => 'B']]));
    }
}
