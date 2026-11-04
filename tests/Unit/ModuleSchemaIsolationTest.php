<?php

namespace Tests\Unit;

use App\Modules\Bienestar\Models\Registro as BienestarRegistro;
use App\Modules\Calidad\Models\Registro as CalidadRegistro;
use App\Modules\Cotecu\Models\Registro as CotecuRegistro;
use App\Modules\GestionHorarios\Models\Registro as GestionHorariosRegistro;
use App\Modules\GestionMatriculas\Models\Registro as GestionMatriculasRegistro;
use App\Modules\GestionPracticas\Models\Registro as GestionPracticasRegistro;
use App\Modules\InvestigacionFormativa\Models\Registro as InvestigacionFormativaRegistro;
use App\Modules\PreMatricula\Models\Registro as PreMatriculaRegistro;
use App\Modules\SeguimientoEgresado\Models\Registro as SeguimientoEgresadoRegistro;
use App\Modules\SeguimientoEstudiante\Models\Registro as SeguimientoEstudianteRegistro;
use App\Modules\TesisProyectoTesis\Models\Registro as TesisProyectoTesisRegistro;
use App\Modules\WebInstitucional\Models\Registro as WebInstitucionalRegistro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleSchemaIsolationTest extends TestCase
{
    #[DataProvider('moduleTables')]
    public function test_each_module_model_uses_its_own_postgresql_schema(
        string $model,
        string $table,
    ): void {
        $this->assertSame($table, (new $model)->getTable());
    }

    public function test_all_module_schemas_are_unique(): void
    {
        $schemas = array_map(
            static fn (array $module): string => $module['schema'],
            require dirname(__DIR__, 2).'/config/modules.php',
        );

        $this->assertCount(12, $schemas);
        $this->assertCount(12, array_unique($schemas));
        $this->assertNotContains('public', $schemas);
    }

    /** @return array<string, array{class-string, string}> */
    public static function moduleTables(): array
    {
        return [
            'prematricula' => [PreMatriculaRegistro::class, 'prematricula.solicitud_prematricula'],
            'bienestar' => [BienestarRegistro::class, 'bienestar.registros'],
            'investigacion_formativa' => [InvestigacionFormativaRegistro::class, 'investigacion_formativa.registros'],
            'seguimiento_estudiante' => [SeguimientoEstudianteRegistro::class, 'seguimiento_estudiante.registros'],
            'web_institucional' => [WebInstitucionalRegistro::class, 'web_institucional.registros'],
            'calidad' => [CalidadRegistro::class, 'calidad.indicador'],
            'gestion_horarios' => [GestionHorariosRegistro::class, 'gestion_horarios.horario'],
            'cotecu' => [CotecuRegistro::class, 'cotecu.planes_curriculares'],
            'gestion_practicas' => [GestionPracticasRegistro::class, 'gestion_practicas.practica'],
            'tesis' => [TesisProyectoTesisRegistro::class, 'tesis.proyecto_tesis'],
            'gestion_matriculas' => [GestionMatriculasRegistro::class, 'gestion_matriculas.matricula'],
            'seguimiento_egresado' => [SeguimientoEgresadoRegistro::class, 'seguimiento_egresado.registros'],
        ];
    }
}
