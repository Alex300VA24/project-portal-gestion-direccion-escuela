<?php

namespace Tests\Unit\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAlcanceRecursosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Services\AmbitoRecursosCompartidos;
use RuntimeException;
use Tests\TestCase;

final class AmbitoRecursosCompartidosTest extends TestCase
{
    public function test_dos_escuelas_solo_reciben_recursos_de_su_ambito_incluidos_los_compartidos(): void
    {
        $escuelas = $this->escuelas([1 => [7], 2 => [8], 3 => [7, 8]]);
        $recursos = $this->recursos([
            7 => ['docentes' => [41, 99, 41], 'ambientes' => [101, 900]],
            8 => ['docentes' => [42, 99], 'ambientes' => [102, 900]],
        ]);
        $ambito = new AmbitoRecursosCompartidos($escuelas, $recursos);
        $a = $this->usuario(1);
        $b = $this->usuario(2);
        $ambas = $this->usuario(3);

        self::assertSame([41, 99], $ambito->consultar($a, 7, 1)['docentes']);
        self::assertSame([101, 900], $ambito->consultar($a, 7, 1)['ambientes']);
        self::assertSame([42, 99], $ambito->consultar($b, 8, 1)['docentes']);
        self::assertSame([102, 900], $ambito->consultar($b, 8, 1)['ambientes']);
        self::assertNull($ambito->consultar($a, 8, 1));
        self::assertNull($ambito->consultar($b, 7, 1));
        self::assertTrue($ambito->incluyeIdentificadorDocente($a, 7, 1, 99));
        self::assertFalse($ambito->incluyeIdentificadorDocente($a, 7, 1, 42));
        self::assertTrue($ambito->incluyeIdentificadorAmbiente($a, 7, 1, 900));
        self::assertFalse($ambito->incluyeIdentificadorAmbiente($a, 7, 1, 102));
        self::assertSame([42, 99], $ambito->consultar($ambas, 8, 1)['docentes']);
        self::assertNull($ambito->consultar($a, 7, 0));
        self::assertNull($ambito->consultar($a, 0, 1));
    }

    public function test_no_basta_con_un_proveedor_de_escuelas_sin_contrato_de_recursos(): void
    {
        $usuario = $this->usuario(1);
        $escuelas = $this->escuelas([1 => [7]]);

        self::assertNull((new AmbitoRecursosCompartidos($escuelas))->consultar($usuario, 7, 1));
        self::assertNull((new AmbitoRecursosCompartidos)->consultar($usuario, 7, 1));
        self::assertNull((new AmbitoRecursosCompartidos($escuelas, $this->recursos([])))->consultar($this->usuarioSinRol(), 7, 1));

        $sinRecursos = (new AmbitoRecursosCompartidos($escuelas, $this->recursos([])))->consultar($usuario, 7, 1);
        self::assertNotNull($sinRecursos);
        self::assertSame([], $sinRecursos['docentes']);
        self::assertSame([], $sinRecursos['ambientes']);
    }

    public function test_rechaza_respuestas_incompletas_ajenas_o_malformadas(): void
    {
        $usuario = $this->usuario(1);
        $escuelas = $this->escuelas([1 => [7]]);
        foreach ([
            ['escuela_id' => 8, 'periodo_id' => 1, 'docentes' => [41], 'ambientes' => [101]],
            ['escuela_id' => 7, 'periodo_id' => 2, 'docentes' => [41], 'ambientes' => [101]],
            ['escuela_id' => 7, 'periodo_id' => 1, 'docentes' => ['41'], 'ambientes' => [101]],
            ['escuela_id' => 7, 'periodo_id' => 1, 'docentes' => [0], 'ambientes' => [101]],
            ['escuela_id' => 7, 'periodo_id' => 1, 'docentes' => [1 => 41], 'ambientes' => [101]],
            ['escuela_id' => 7, 'periodo_id' => 1, 'docentes' => [41]],
            ['escuela_id' => 7, 'periodo_id' => 1, 'docentes' => [41], 'ambientes' => ['101']],
        ] as $respuesta) {
            $proveedor = new class($respuesta) implements ProveedorAlcanceRecursosAcademicos
            {
                public function __construct(private readonly array $respuesta) {}

                public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
                {
                    return $this->respuesta;
                }
            };

            self::assertNull((new AmbitoRecursosCompartidos($escuelas, $proveedor))->consultar($usuario, 7, 1));
        }
    }

    public function test_falla_cerrado_si_el_proveedor_falla_o_se_revoca_la_adscripcion(): void
    {
        $usuario = $this->usuario(1);
        $escuelas = $this->escuelas([1 => [7]]);
        $proveedorInvalido = new class implements ProveedorAlcanceRecursosAcademicos
        {
            public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
            {
                throw new RuntimeException('Catálogo de recursos indisponible.');
            }
        };
        self::assertNull((new AmbitoRecursosCompartidos($escuelas, $proveedorInvalido))->consultar($usuario, 7, 1));

        $recursos = $this->recursos([7 => ['docentes' => [41], 'ambientes' => [101]]]);
        $ambito = new AmbitoRecursosCompartidos($escuelas, $recursos);
        self::assertNotNull($ambito->consultar($usuario, 7, 1));
        $escuelas->adscripciones[1] = [];
        self::assertNull($ambito->consultar($usuario, 7, 1));
    }

    public function test_falla_cerrado_si_el_contenedor_no_resuelve_el_proveedor_de_recursos(): void
    {
        $usuario = $this->usuario(1);
        $this->app->bind(ProveedorAlcanceRecursosAcademicos::class, function (): never {
            throw new RuntimeException('No se pudo resolver el contrato de recursos.');
        });

        self::assertNull((new AmbitoRecursosCompartidos($this->escuelas([1 => [7]])))->consultar($usuario, 7, 1));
    }

    private function usuario(int $id): User
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
        })->forceFill(['id' => $id]);
    }

    private function usuarioSinRol(): User
    {
        return new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return false;
            }

            public function can($abilities, $arguments = []): bool
            {
                return false;
            }
        };
    }

    private function escuelas(array $adscripciones): ProveedorEscuelasAutorizadas
    {
        return new class($adscripciones) implements ProveedorEscuelasAutorizadas
        {
            public function __construct(public array $adscripciones) {}

            public function escuelasVigentes(User $usuario): array
            {
                return $this->adscripciones[(int) $usuario->getKey()] ?? [];
            }
        };
    }

    private function recursos(array $datos): ProveedorAlcanceRecursosAcademicos
    {
        return new class($datos) implements ProveedorAlcanceRecursosAcademicos
        {
            public function __construct(private readonly array $datos) {}

            public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
            {
                return ['escuela_id' => $escuela, 'periodo_id' => $periodo] + ($this->datos[$escuela] ?? ['docentes' => [], 'ambientes' => []]);
            }
        };
    }
}
