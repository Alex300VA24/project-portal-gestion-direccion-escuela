<?php

namespace Tests\Unit\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\ConsultaDisponibilidadPropia;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ResilienciaIdentidadInstitucionalTest extends TestCase
{
    private function usuarioConPermiso(): User
    {
        return (new class extends User
        {
            public function can($abilities, $arguments = []): bool
            {
                return $abilities === 'gestion-horarios.view';
            }
        })->forceFill(['id' => 15]);
    }

    public function test_escuelas_falla_cerrado_si_el_proveedor_lanza_una_excepcion(): void
    {
        $usuario = $this->usuarioConPermiso();
        $proveedor = new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                throw new RuntimeException('Fuente institucional temporalmente indisponible.');
            }
        };
        $ambito = new AmbitoConsultaAdministrativa($proveedor);

        self::assertSame([], $ambito->escuelas($usuario));
        try {
            $ambito->exigirEscuela($usuario);
            self::fail('El error de identidad no debe autorizar una escuela.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    public function test_error_al_resolver_el_proveedor_del_contenedor_tambien_deniega(): void
    {
        $usuario = $this->usuarioConPermiso();
        $this->app->bind(ProveedorEscuelasAutorizadas::class, function (): never {
            throw new RuntimeException('No se pudo resolver el proveedor.');
        });

        self::assertSame([], (new AmbitoConsultaAdministrativa)->escuelas($usuario));
    }

    public function test_no_acepta_listas_de_escuelas_ambiguas_o_malformadas(): void
    {
        $usuario = $this->usuarioConPermiso();
        foreach ([[], [0], [-1], ['7'], [7, '8'], [null], [true], [1 => 7]] as $lista) {
            $proveedor = new class($lista) implements ProveedorEscuelasAutorizadas
            {
                public function __construct(private readonly array $escuelas) {}

                public function escuelasVigentes(User $usuario): array
                {
                    return $this->escuelas;
                }
            };
            self::assertSame([], (new AmbitoConsultaAdministrativa($proveedor))->escuelas($usuario));
        }
    }

    public function test_la_adscripcion_se_vuelve_a_consultar_despues_de_revocarse(): void
    {
        $usuario = $this->usuarioConPermiso();
        $proveedor = new class implements ProveedorEscuelasAutorizadas
        {
            public bool $vigente = true;

            public function escuelasVigentes(User $usuario): array
            {
                return $this->vigente ? [7, 8, 7] : [];
            }
        };
        $ambito = new AmbitoConsultaAdministrativa($proveedor);

        self::assertSame([7, 8], $ambito->escuelas($usuario));
        $proveedor->vigente = false;
        self::assertSame([], $ambito->escuelas($usuario));
    }

    public function test_error_de_identidad_docente_no_activa_consultas_a_la_base(): void
    {
        $usuario = (new User)->forceFill(['id' => 15]);
        $proveedor = new class implements ProveedorIdentidadDocente
        {
            public function docenteId(User $usuario): ?int
            {
                throw new RuntimeException('No se pudo acreditar la identidad docente.');
            }
        };
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldNotReceive('table');

        $respuesta = (new ConsultaDisponibilidadPropia($db, $proveedor))->consultar($usuario, 1);
        self::assertFalse($respuesta['acceso']);
        self::assertFalse($respuesta['periodo_valido']);
        self::assertTrue($respuesta['periodos']->isEmpty());
    }
}
