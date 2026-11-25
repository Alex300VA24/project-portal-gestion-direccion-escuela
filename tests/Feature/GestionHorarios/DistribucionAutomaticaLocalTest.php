<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\DistribucionAutomaticaLocal;
use PHPUnit\Framework\TestCase;

final class DistribucionAutomaticaLocalTest extends TestCase
{
    private function ambientes(): array
    {
        return [['tipo'=>'AULA','capacidad'=>63,'estado'=>'ACTIVO'], ['tipo'=>'LABORATORIO','capacidad'=>20,'estado'=>'ACTIVO']];
    }

    public function test_crea_secciones_equilibradas_sin_capacidad_manual(): void
    {
        $p = DistribucionAutomaticaLocal::calcular(87, true, true, $this->ambientes(), [], 'Programación');
        self::assertSame(2, $p['minimo']);
        self::assertSame(['A','B'], array_column($p['secciones'],'codigo'));
        self::assertSame([44,43], array_column($p['secciones'],'poblacion_estimada'));
        self::assertSame([[20,20,4],[20,20,3]], array_column($p['secciones'],'grupos_lab'));
        self::assertSame(['crear','crear'], array_column($p['secciones'],'accion'));
    }

    public function test_conserva_identidades_y_no_duplica_secciones(): void
    {
        $s = [['id_seccion'=>91,'codigo'=>'A','capacidad'=>63,'estado'=>'ACTIVA']];
        $p = DistribucionAutomaticaLocal::calcular(64, true, false, $this->ambientes(), $s, 'Cálculo');
        self::assertSame([91,null], array_column($p['secciones'],'id_seccion'));
        self::assertSame(['conservar','crear'], array_column($p['secciones'],'accion'));
        self::assertSame(['A','B'], array_column($p['secciones'],'codigo'));
    }

    public function test_ajusta_capacidad_sin_crear_duplicados(): void
    {
        $p = DistribucionAutomaticaLocal::calcular(1, true, false, $this->ambientes(), [['id_seccion'=>7,'codigo'=>'A','capacidad'=>40,'estado'=>'ACTIVA']], 'Cálculo');
        self::assertSame('ajustar', $p['secciones'][0]['accion']);
        self::assertSame(63, $p['secciones'][0]['capacidad']);
    }

    public function test_demanda_cero_sin_secciones(): void
    {
        self::assertSame([], DistribucionAutomaticaLocal::calcular(0, true, false, [], [], 'Cálculo')['secciones']);
    }

    public function test_rechaza_reduccion_que_dejaria_secciones_vacias(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DistribucionAutomaticaLocal::calcular(0, true, false, $this->ambientes(), [['id_seccion'=>7,'codigo'=>'A','capacidad'=>63,'estado'=>'ACTIVA']], 'Cálculo');
    }

    public function test_rechaza_grupos_especiales(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DistribucionAutomaticaLocal::calcular(10, true, false, $this->ambientes(), [], 'Proyecto de Tesis');
    }
}
