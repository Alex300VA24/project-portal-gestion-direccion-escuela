<?php

namespace App\Modules\TesisProyectoTesis\Database\Seeders;

use App\Modules\TesisProyectoTesis\Models\CriterioRubrica;
use App\Modules\TesisProyectoTesis\Models\Rubrica;
use Illuminate\Database\Seeder;

/**
 * Rúbrica única de verificación v1 (borrador v0.1 de .tesis-kit/04_rubrica.md). Idempotente.
 */
final class RubricaSeeder extends Seeder
{
    public const NOMBRE = 'Rúbrica única de verificación';

    /** @var list<array{0: string, 1: string, 2: string}> sección, descripción, aplica (A/P/T) */
    private const CRITERIOS = [
        ['Título', 'El título indica la variable o la solución propuesta, la unidad de análisis y el contexto, en no más de 20 palabras.', 'A'],
        ['Problema', 'Describe la situación problemática de forma objetiva y contextualizada, con datos o estudios que la respalden.', 'A'],
        ['Problema', 'Expone la relevancia y la justificación de la investigación.', 'A'],
        ['Problema', 'Enuncia el problema de forma interrogativa y coherente con la situación descrita.', 'A'],
        ['Matriz de consistencia', 'El objetivo general y los específicos son coherentes con el problema.', 'A'],
        ['Matriz de consistencia', 'La hipótesis, si corresponde, es coherente con el problema y los objetivos.', 'A'],
        ['Matriz de consistencia', 'Las variables están definidas con sus dimensiones e indicadores.', 'A'],
        ['Matriz de consistencia', 'Los indicadores son medibles y se indica cómo se validan.', 'A'],
        ['Marco teórico', 'Presenta antecedentes relacionados con los objetivos.', 'A'],
        ['Marco teórico', 'Fundamenta teóricamente las variables y las tecnologías, métodos o estándares que emplea.', 'A'],
        ['Marco teórico', 'Usa citas y referencias de forma correcta según la norma adoptada.', 'A'],
        ['Material y método', 'Precisa el tipo, el enfoque y el diseño de la investigación.', 'A'],
        ['Material y método', 'Define la población, la muestra, la unidad de análisis y los criterios de inclusión.', 'A'],
        ['Material y método', 'Describe las técnicas e instrumentos de recolección de datos y su validación.', 'A'],
        ['Material y método', 'Describe el procedimiento y el análisis de los datos.', 'A'],
        ['Material y método', 'Expone las consideraciones éticas.', 'A'],
        ['Solución tecnológica', 'Justifica la metodología de desarrollo o el marco de trabajo empleado (por ejemplo, Scrum, RUP o CRISP-DM).', 'A'],
        ['Solución tecnológica', 'Presenta los requerimientos y el diseño de la solución (arquitectura, modelos y datos).', 'T'],
        ['Solución tecnológica', 'Describe la implementación y las pruebas o la validación de la solución.', 'T'],
        ['Aspectos administrativos', 'Detalla el cronograma, los recursos, el presupuesto y el financiamiento.', 'P'],
        ['Resultados', 'Presenta los resultados en el orden de los objetivos, con tablas o figuras interpretadas.', 'T'],
        ['Resultados', 'Contrasta la hipótesis o compara los indicadores antes y después, si corresponde.', 'T'],
        ['Discusión', 'Compara los resultados con los antecedentes y las teorías, y señala las limitaciones.', 'T'],
        ['Conclusiones y recomendaciones', 'Formula una conclusión por cada objetivo.', 'T'],
        ['Conclusiones y recomendaciones', 'Plantea recomendaciones derivadas de las conclusiones.', 'T'],
        ['Resumen', 'El resumen y el abstract incluyen el objetivo, el método, los resultados principales y las palabras clave.', 'T'],
    ];

    /** Criterio que puede marcarse «no aplica» (hipótesis en investigaciones no cuantitativas). */
    public const CRITERIO_NO_APLICA = 6;

    public function run(): void
    {
        $rubrica = Rubrica::query()->firstOrCreate(
            ['nombre' => self::NOMBRE, 'version' => 1],
            ['etapa' => 'Ambas', 'estado' => 'Vigente'],
        );

        $aplica = ['A' => 'Ambas', 'P' => 'Proyecto', 'T' => 'Tesis'];

        foreach (self::CRITERIOS as $i => [$seccion, $descripcion, $a]) {
            CriterioRubrica::query()->updateOrCreate(
                ['id_rubrica' => $rubrica->id_rubrica, 'orden' => $i + 1],
                [
                    'seccion' => $seccion,
                    'descripcion' => $descripcion,
                    'aplica_a' => $aplica[$a],
                    'estado' => 'Activo',
                    'permite_no_aplica' => $i + 1 === self::CRITERIO_NO_APLICA,
                    'puntaje_maximo' => null,
                ],
            );
        }
    }
}
