<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ModularRouteTest extends TestCase
{
    #[DataProvider('modules')]
    public function test_each_module_exposes_a_named_web_route(
        string $slug,
        string $name,
        string $schema,
    ): void {
        $this->get('/'.$slug)
            ->assertOk()
            ->assertSee($name)
            ->assertSee($schema);

        $this->assertSame(url('/'.$slug), route($slug.'.index'));
    }

    #[DataProvider('modules')]
    public function test_each_module_exposes_an_api_route(
        string $slug,
        string $name,
        string $schema,
    ): void {
        $this->getJson('/api/'.$slug)
            ->assertOk()
            ->assertExactJson([
                'module' => $name,
                'schema' => $schema,
            ]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function modules(): array
    {
        return [
            'pre_matricula' => ['pre-matricula', 'Pre Matrícula', 'pre_matricula'],
            'bienestar' => ['bienestar', 'Bienestar', 'bienestar'],
            'investigacion_formativa' => ['investigacion-formativa', 'Investigación Formativa', 'investigacion_formativa'],
            'seguimiento_estudiante' => ['seguimiento-estudiante', 'Seguimiento al Estudiante', 'seguimiento_estudiante'],
            'web_institucional' => ['web-institucional', 'Web Institucional', 'web_institucional'],
            'calidad' => ['calidad', 'Calidad', 'calidad'],
            'gestion_horarios' => ['gestion-horarios', 'Gestión de Horarios', 'gestion_horarios'],
            'cotecu' => ['cotecu', 'COTECU', 'cotecu'],
            'gestion_practicas' => ['gestion-practicas', 'Gestión de Prácticas', 'gestion_practicas'],
            'tesis_proyecto_tesis' => ['tesis-proyecto-tesis', 'Tesis y Proyecto de Tesis', 'tesis_proyecto_tesis'],
            'gestion_matriculas' => ['gestion-matriculas', 'Gestión de Matrículas', 'gestion_matriculas'],
            'seguimiento_egresado' => ['seguimiento-egresado', 'Seguimiento al Egresado', 'seguimiento_egresado'],
        ];
    }
}
