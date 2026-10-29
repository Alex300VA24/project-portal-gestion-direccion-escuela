<?php

namespace Tests\Unit;

use App\Models\Core\CursoPlan;
use App\Models\Core\Estudiante;
use App\Models\Core\PeriodoAcademico;
use App\Models\Core\Seccion;
use App\Models\Seguridad\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrematriculaReferenceModelTest extends TestCase
{
    #[DataProvider('models')]
    public function test_reference_models_use_the_expected_table_and_primary_key(
        string $modelClass,
        string $table,
        string $primaryKey,
    ): void {
        $model = new $modelClass;

        $this->assertSame($table, $model->getTable());
        $this->assertSame($primaryKey, $model->getKeyName());
    }

    /** @return array<string, array{class-string, string, string}> */
    public static function models(): array
    {
        return [
            'periodo' => [PeriodoAcademico::class, 'core.periodo_academico', 'id_periodo'],
            'estudiante' => [Estudiante::class, 'core.estudiante', 'id_estudiante'],
            'curso_plan' => [CursoPlan::class, 'core.curso_plan', 'id_curso_plan'],
            'seccion' => [Seccion::class, 'core.seccion', 'id_seccion'],
            'usuario' => [Usuario::class, 'seguridad.usuario', 'id_usuario'],
        ];
    }
}
