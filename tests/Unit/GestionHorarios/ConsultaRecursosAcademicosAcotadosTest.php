<?php

namespace Tests\Unit\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Services\ConsultaRecursosAcademicosAcotados;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Tests\TestCase;

final class ConsultaRecursosAcademicosAcotadosTest extends TestCase
{
    public function test_sin_contratos_no_consulta_la_base_de_datos(): void
    {
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldNotReceive('table');
        $usuario = $this->usuario();

        $this->expectException(AuthorizationException::class);
        (new ConsultaRecursosAcademicosAcotados($db))->consultar($usuario, 7, 1, 10);
    }

    public function test_parametros_no_validos_se_rechazan_antes_de_consultar(): void
    {
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldNotReceive('table');
        $usuario = $this->usuario();

        foreach ([[0, 1, 10], [7, 0, 10], [7, 1, 0]] as [$escuela, $periodo, $plan]) {
            try {
                (new ConsultaRecursosAcademicosAcotados($db))->consultar($usuario, $escuela, $periodo, $plan);
                self::fail('No debe consultarse un ámbito sin identificadores válidos.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
    }

    private function usuario(): User
    {
        return (new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        })->forceFill(['id' => 1]);
    }
}
