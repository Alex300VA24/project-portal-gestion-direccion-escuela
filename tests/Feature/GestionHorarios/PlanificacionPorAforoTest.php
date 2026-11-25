<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\PlanificacionPorAforo;
use PHPUnit\Framework\TestCase;

final class PlanificacionPorAforoTest extends TestCase
{
    private function ambientes(): array
    {
        return [
            ['tipo' => 'AULA', 'estado' => 'ACTIVO', 'capacidad' => 63],
            ['tipo' => 'AULA', 'estado' => 'DISPONIBLE', 'capacidad' => 52],
            ['tipo' => 'AULA', 'estado' => 'INACTIVO', 'capacidad' => 100],
            ['tipo' => 'LABORATORIO', 'estado' => 'Activo', 'capacidad' => 20],
        ];
    }

    public function test_aforo_y_reparto_equilibrado(): void
    {
        $p = PlanificacionPorAforo::calcular(87, true, true, $this->ambientes());
        self::assertSame(63, $p['aforo_seccion']);
        self::assertSame(2, $p['minimo']);
        self::assertSame([44, 43], array_column($p['secciones'], 'poblacion_estimada'));
        self::assertSame([[20,20,4],[20,20,3]], array_column($p['secciones'], 'grupos_lab'));
    }

    public function test_conserva_secciones_adicionales_y_limite_lab(): void
    {
        $p = PlanificacionPorAforo::calcular(47, true, true, $this->ambientes(), 2, 15);
        self::assertSame(1, $p['minimo']);
        self::assertSame([24,23], array_column($p['secciones'], 'poblacion_estimada'));
        self::assertSame([[15,9],[15,8]], array_column($p['secciones'], 'grupos_lab'));
    }

    public function test_sin_demanda_no_crea_secciones(): void
    {
        self::assertSame([], PlanificacionPorAforo::calcular(0, true, false, [])['secciones']);
    }

    public function test_sin_aulas_no_inventa_capacidad(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanificacionPorAforo::calcular(47, true, false, []);
    }

    public function test_no_elimina_secciones_sobrantes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanificacionPorAforo::calcular(1, true, false, $this->ambientes(), 2);
    }
}
