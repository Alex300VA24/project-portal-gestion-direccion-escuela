<?php

namespace Tests\Unit\PreMatricula;

use App\Modules\PreMatricula\Services\ElegibilidadAcademica;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ElegibilidadAcademicaTest extends TestCase
{
    /** @param  list<array{int, string}>  $intentos */
    #[DataProvider('casos')]
    public function test_evalua_el_curso_segun_el_historial(array $intentos, bool $activo, string $estado, ?string $motivo): void
    {
        $curso = (object) [
            'id_curso_plan' => 2, 'codigo' => 'C2', 'nombre' => 'Curso 2', 'activo' => $activo,
            'prerrequisitos' => [['id_curso_plan' => 1, 'codigo' => 'C1', 'nombre' => 'Curso 1']],
        ];

        $resultado = app(ElegibilidadAcademica::class)->evaluar($curso, $this->historial($intentos));

        $this->assertSame($estado, $resultado['estado']);
        $this->assertSame($motivo, $resultado['motivo']);
    }

    /** @return array<string, array{list<array{int, string}>, bool, string, ?string}> */
    public static function casos(): array
    {
        return [
            'prerrequisito aprobado' => [[[1, 'APROBADO']], true, 'VALIDA', null],
            'prerrequisito en curso' => [[[1, 'EN_CURSO']], true, 'PENDIENTE_RESULTADOS', null],
            'prerrequisito desaprobado' => [[[1, 'DESAPROBADO']], true, 'REQUIERE_AJUSTE', 'Falta aprobar un prerrequisito.'],
            'sin historial' => [[], true, 'REQUIERE_AJUSTE', 'Falta aprobar un prerrequisito.'],
            'un aprobado anterior prevalece sobre un retiro posterior' => [[[1, 'RETIRADO'], [1, 'APROBADO']], true, 'VALIDA', null],
            'curso ya aprobado' => [[[1, 'APROBADO'], [2, 'APROBADO']], true, 'REQUIERE_AJUSTE', 'Ya aprobaste este curso.'],
            'curso en curso' => [[[1, 'APROBADO'], [2, 'EN_CURSO']], true, 'REQUIERE_AJUSTE', 'Estás llevando este curso.'],
            'curso inactivo' => [[[1, 'APROBADO']], false, 'REQUIERE_AJUSTE', 'El curso está inactivo.'],
        ];
    }

    /**
     * El historial llega ordenado del intento más reciente al más antiguo.
     *
     * @param  list<array{int, string}>  $intentos
     */
    private function historial(array $intentos): Collection
    {
        return collect($intentos)->map(fn (array $i): object => (object) ['id_curso_plan' => $i[0], 'estado' => $i[1], 'nota_final' => null, 'periodo' => 'P']);
    }
}
