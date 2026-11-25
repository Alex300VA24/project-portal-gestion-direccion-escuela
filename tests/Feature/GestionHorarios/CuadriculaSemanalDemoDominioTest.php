<?php
namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\CuadriculaSemanalDemo;
use PHPUnit\Framework\TestCase;

final class CuadriculaSemanalDemoDominioTest extends TestCase
{
    public function test_carriles_paralelos_y_reutilizacion(): void
    {
        $a = [['id'=>1],['id'=>2],['id'=>3]];
        $s = [1=>['dia'=>'LUNES','inicio'=>420,'fin'=>540],2=>['dia'=>'LUNES','inicio'=>480,'fin'=>540],3=>['dia'=>'LUNES','inicio'=>540,'fin'=>600]];
        $g = CuadriculaSemanalDemo::construir($a,$s,[[420,480],[480,540],[540,600]]);
        self::assertSame(2,$g['dias']['LUNES']['carriles']);
        self::assertSame([0,1,0],array_column($g['dias']['LUNES']['filas'],'carril'));
        self::assertSame(420,$g['desde']); self::assertSame(600,$g['hasta']);
    }
    public function test_sabado_y_pendientes(): void
    {
        $g = CuadriculaSemanalDemo::construir([['id'=>1],['id'=>2]], [1=>['dia'=>'SABADO','inicio'=>540,'fin'=>660]], [[420,480],[1140,1200]]);
        self::assertCount(1,$g['dias']['SABADO']['filas']);
        self::assertSame([],$g['dias']['LUNES']['filas']);
        self::assertSame(1200,$g['hasta']);
    }
    public function test_calendario_vacio(): void
    {
        $g = CuadriculaSemanalDemo::construir([],[],[]);
        self::assertSame(420,$g['desde']); self::assertSame(1200,$g['hasta']);
        self::assertCount(6,$g['dias']);
    }
}
