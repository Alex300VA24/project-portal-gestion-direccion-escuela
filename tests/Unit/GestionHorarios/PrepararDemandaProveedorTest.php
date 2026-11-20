<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Contracts\IdentidadCursoPlan;
use App\Modules\GestionHorarios\Contracts\ProveedorDemandaAcademica;
use App\Modules\GestionHorarios\Demand\PrepararDemandaProveedor;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PrepararDemandaProveedorTest extends TestCase
{
    private function ejecutar(array $datos): array
    {
        $fuente = new class($datos) implements ProveedorDemandaAcademica
        {
            public function __construct(private readonly array $datos) {}

            public function obtener(int $escuela, int $periodo): array
            {
                return $this->datos;
            }
        };
        $catalogo = new class implements IdentidadCursoPlan
        {
            public function coincide(int $escuela, int $plan, int $curso, int $cursoPlan): bool
            {
                return [$escuela, $plan, $curso, $cursoPlan] === [7, 10, 20, 30];
            }
        };

        return (new PrepararDemandaProveedor($fuente, $catalogo))->preparar(7, 1);
    }

    private function datos(): array
    {
        return ['escuela' => 7, 'periodo' => 1, 'completa' => true, 'fuente' => 'proveedor-ficticio', 'version' => 3, 'capturada_en' => '2026-11-19T10:00:00-05:00', 'cursos' => [['id_plan' => 10, 'id_curso' => 20, 'id_curso_plan' => 30, 'total' => 8, 'manana' => 3, 'tarde' => 4]]];
    }

    public function test_prepara_captura_con_procedencia_sin_persistir(): void
    {
        $resultado = $this->ejecutar($this->datos());
        self::assertSame('VALIDADA_SIN_PERSISTIR', $resultado['estado']);
        self::assertSame(3, $resultado['instantanea']->version);
        self::assertSame(8, $resultado['instantanea']->cursos[0]['total']);
        self::assertSame('proveedor-ficticio', $resultado['procedencia']['fuente']);
        self::assertSame(7, $resultado['procedencia']['escuela']);
    }

    public function test_rechaza_captura_incompleta_o_ambito_ajeno(): void
    {
        foreach ([['completa' => false], ['escuela' => 8], ['periodo' => 2], ['version' => 0], ['capturada_en' => '2026-02-30T10:00:00-05:00']] as $cambio) {
            try {
                $this->ejecutar(array_replace($this->datos(), $cambio));
                self::fail('Debe rechazar una captura sin garantías.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_rechaza_relaciones_ambiguas_y_cursos_duplicados(): void
    {
        $datos = $this->datos();
        $datos['cursos'][0]['id_plan'] = 11;
        try {
            $this->ejecutar($datos);
            self::fail('No debe aceptar un curso-plan de otro plan.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $datos = $this->datos();
        $datos['cursos'][] = $datos['cursos'][0];
        $this->expectException(InvalidArgumentException::class);
        $this->ejecutar($datos);
    }
}
