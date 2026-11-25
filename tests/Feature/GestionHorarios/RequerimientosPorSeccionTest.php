<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\RequerimientosPorSeccion;
use PHPUnit\Framework\TestCase;

final class RequerimientosPorSeccionTest extends TestCase
{
    public function test_cada_subgrupo_recibe_la_carga_completa(): void
    {
        $r = RequerimientosPorSeccion::construir(['horas_teoria'=>2,'horas_practica'=>1,'horas_laboratorio'=>2], 1, 44, [20,20,4]);
        self::assertSame([44,44,20,20,4], array_column($r,'poblacion_estimada'));
        self::assertSame([120,60,120,120,120], array_column($r,'minutos_semanales'));
        self::assertSame([null,null,1,2,3], array_column($r,'subgrupo'));
        self::assertCount(5,array_unique(array_column($r,'clave')));
    }
    public function test_no_crea_laboratorios_sin_horas(): void
    {
        $r = RequerimientosPorSeccion::construir(['horas_teoria'=>2,'horas_practica'=>0,'horas_laboratorio'=>0], 1, 1, []);
        self::assertSame(['TEORIA'],array_column($r,'tipo_actividad'));
    }
    public function test_rechaza_cobertura_incompleta(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RequerimientosPorSeccion::construir(['horas_teoria'=>2,'horas_practica'=>0,'horas_laboratorio'=>2],1,44,[20,20]);
    }
}
