<?php

namespace Tests\Unit;

use App\Shared\Database\ModularMigrationRegistry;
use Tests\TestCase;

final class ModularMigrationRegistryTest extends TestCase
{
    public function test_all_resolves_the_eleven_enabled_modules(): void
    {
        $registry = app(ModularMigrationRegistry::class);

        $this->assertSame([
            'pre-matricula',
            'bienestar',
            'investigacion-formativa',
            'seguimiento-estudiante',
            'calidad',
            'gestion-horarios',
            'cotecu',
            'gestion-practicas',
            'tesis-proyecto-tesis',
            'gestion-matriculas',
            'seguimiento-egresado',
        ], $registry->targets('all'));
    }

    public function test_all_migrates_the_public_surface_without_counting_it_as_a_module(): void
    {
        $registry = app(ModularMigrationRegistry::class);

        $this->assertNotContains('web-institucional', $registry->targets('all'));
        $this->assertContains('superficie:web-institucional', array_column($registry->groups('all'), 'label'));
    }

    public function test_single_module_keeps_dependency_groups_first(): void
    {
        $groups = app(ModularMigrationRegistry::class)->groups('calidad');

        $this->assertSame(
            ['infraestructura', 'shared', 'calidad'],
            array_column($groups, 'label'),
        );
    }

    public function test_unknown_module_is_not_migrable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ModularMigrationRegistry::class)->targets('inexistente');
    }
}
