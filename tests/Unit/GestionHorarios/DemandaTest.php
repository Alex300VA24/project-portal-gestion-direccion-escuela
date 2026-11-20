<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Contracts\CatalogoDemanda;
use App\Modules\GestionHorarios\Contracts\RepositorioDemanda;
use App\Modules\GestionHorarios\Demand\InstantaneaDemanda;
use App\Modules\GestionHorarios\Demand\ProcesarDemanda;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DemandaTest extends TestCase
{
    private function datos(int $version = 1): array
    {
        return ['periodo' => 1, 'version' => $version, 'cursos' => [['id_curso_plan' => 2, 'total' => 10, 'manana' => 4, 'tarde' => 3]]];
    }

    public function test_versiones_duplicados_y_conflictos(): void
    {
        $catalogo = $this->createStub(CatalogoDemanda::class);
        $catalogo->method('periodoExiste')->willReturn(true);
        $catalogo->method('cursoPlanExiste')->willReturn(true);
        $repo = new class implements RepositorioDemanda
        {
            public ?array $actual = null;

            public function conBloqueo(int $periodo, callable $operacion): mixed
            {
                return $operacion();
            }

            public function ultima(int $periodo): ?array
            {
                return $this->actual;
            }

            public function guardar(InstantaneaDemanda $instantanea): void
            {
                $this->actual = ['version' => $instantanea->version, 'hash' => $instantanea->hash()];
            }
        };
        $procesador = new ProcesarDemanda($catalogo, $repo);
        self::assertSame('aplicada', $procesador->procesar(InstantaneaDemanda::desdeArray($this->datos(2))));
        self::assertSame('duplicada', $procesador->procesar(InstantaneaDemanda::desdeArray($this->datos(2))));
        self::assertSame('antigua', $procesador->procesar(InstantaneaDemanda::desdeArray($this->datos())));
        $datos = $this->datos(2);
        $datos['cursos'][0]['total'] = 11;
        $this->expectException(DomainException::class);
        $procesador->procesar(InstantaneaDemanda::desdeArray($datos));
    }

    public function test_rechaza_cursos_duplicados(): void
    {
        $datos = $this->datos();
        $datos['cursos'][] = $datos['cursos'][0];
        $this->expectException(InvalidArgumentException::class);
        InstantaneaDemanda::desdeArray($datos);
    }

    public function test_rechaza_preferencias_inconsistentes(): void
    {
        $datos = $this->datos();
        $datos['cursos'][0]['tarde'] = 9;
        $this->expectException(InvalidArgumentException::class);
        InstantaneaDemanda::desdeArray($datos);
    }

    public function test_rechaza_periodo_desconocido(): void
    {
        $catalogo = $this->createStub(CatalogoDemanda::class);
        $catalogo->method('periodoExiste')->willReturn(false);
        $repo = $this->createMock(RepositorioDemanda::class);
        $repo->expects(self::never())->method('guardar');
        $this->expectException(DomainException::class);
        (new ProcesarDemanda($catalogo, $repo))->procesar(InstantaneaDemanda::desdeArray($this->datos()));
    }

    public function test_rechaza_curso_desconocido_antes_de_guardar(): void
    {
        $catalogo = $this->createStub(CatalogoDemanda::class);
        $catalogo->method('periodoExiste')->willReturn(true);
        $catalogo->method('cursoPlanExiste')->willReturn(false);
        $repo = $this->createMock(RepositorioDemanda::class);
        $repo->expects(self::never())->method('conBloqueo');
        $this->expectException(DomainException::class);
        (new ProcesarDemanda($catalogo, $repo))->procesar(InstantaneaDemanda::desdeArray($this->datos()));
    }

    public function test_huella_no_depende_del_orden_de_los_cursos(): void
    {
        $datos = $this->datos();
        $datos['cursos'][] = ['id_curso_plan' => 1, 'total' => 2, 'manana' => 1, 'tarde' => 1];
        $huella = InstantaneaDemanda::desdeArray($datos)->hash();
        $datos['cursos'] = array_reverse($datos['cursos']);
        self::assertSame($huella, InstantaneaDemanda::desdeArray($datos)->hash());
    }
}
