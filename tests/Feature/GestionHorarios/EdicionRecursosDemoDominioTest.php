<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\EditarDisponibilidadDemo;
use App\Modules\GestionHorarios\Services\PrioridadDocenteDemo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EdicionRecursosDemoDominioTest extends TestCase
{
    public function test_normaliza_franjas_validas(): void
    {
        self::assertSame(['1:LUNES', '2:SABADO'], EditarDisponibilidadDemo::normalizar('PRESENTADA', 'ESTRICTA', ['2:SABADO','1:LUNES'], [1,2]));
    }
    public function test_permite_declaracion_presentada_sin_franjas(): void
    {
        self::assertSame([], EditarDisponibilidadDemo::normalizar('PRESENTADA', 'FLEXIBLE', [], [1]));
    }
    public function test_rechaza_franjas_sin_presentacion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EditarDisponibilidadDemo::normalizar('NO_PRESENTADA', 'ESTRICTA', ['1:LUNES'], [1]);
    }
    public function test_rechaza_bloque_ajeno(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EditarDisponibilidadDemo::normalizar('PRESENTADA', 'ESTRICTA', ['2:LUNES'], [1]);
    }
    public function test_rechaza_dia_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EditarDisponibilidadDemo::normalizar('PRESENTADA', 'ESTRICTA', ['1:DOMINGO'], [1]);
    }
    public function test_rechaza_franja_duplicada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EditarDisponibilidadDemo::normalizar('PRESENTADA', 'ESTRICTA', ['1:LUNES','1:LUNES'], [1]);
    }
    public function test_conserva_orden_y_permite_cola_vacia(): void
    {
        self::assertSame([3,1], PrioridadDocenteDemo::validarOrden(['3',1], [1,2,3]));
        self::assertSame([], PrioridadDocenteDemo::validarOrden([], [1,2,3]));
    }
    public function test_rechaza_docente_no_autorizado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PrioridadDocenteDemo::validarOrden([4], [1,2,3]);
    }
    public function test_rechaza_docente_duplicado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PrioridadDocenteDemo::validarOrden([1,'1'], [1,2]);
    }
}
