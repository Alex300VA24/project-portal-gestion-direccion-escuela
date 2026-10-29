<?php

namespace Tests\Unit;

use App\Models\Core\Curso;
use App\Models\Core\CursoPlan;
use App\Models\Core\Estudiante;
use App\Models\Core\PeriodoAcademico;
use App\Models\Core\Persona;
use App\Models\Core\PlanEstudios;
use App\Models\Core\Seccion;
use App\Models\GestionMatriculas\HistorialAcademico;
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
            'persona' => [Persona::class, 'core.persona', 'id_persona'],
            'plan_estudios' => [PlanEstudios::class, 'core.plan_estudios', 'id_plan'],
            'curso' => [Curso::class, 'core.curso', 'id_curso'],
            'historial' => [HistorialAcademico::class, 'gestion_matriculas.v_historial_academico', 'id'],
        ];
    }
}
