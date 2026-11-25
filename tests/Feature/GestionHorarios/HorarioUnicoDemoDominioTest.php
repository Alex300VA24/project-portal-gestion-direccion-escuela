<?php
namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\HorarioUnicoDemo as Horario;
use PHPUnit\Framework\TestCase;

final class HorarioUnicoDemoDominioTest extends TestCase
{
    public function test_version_consecutiva(): void
    {
        self::assertSame(2, Horario::siguienteVersion(1,1));
        self::assertSame(18, Horario::siguienteVersion(17,17));
    }
    public function test_rechaza_version_obsoleta(): void
    {
        $this->expectException(\RuntimeException::class);
        Horario::siguienteVersion(1,2);
    }
    public function test_rechaza_horario_sin_version(): void
    {
        $this->expectException(\RuntimeException::class);
        Horario::siguienteVersion(0,0);
    }
    public function test_limite_de_version(): void
    {
        $this->expectException(\RuntimeException::class);
        Horario::siguienteVersion(PHP_INT_MAX,PHP_INT_MAX);
    }
    public function test_nombre_no_cambia_identidad(): void
    {
        self::assertSame('Horario 2026-II', Horario::nombre('  Horario 2026-II  '));
    }
}
