<?php

namespace Tests\Unit;

use App\Shared\Database\ModularMigrationRegistry;
use Tests\TestCase;

final class ModularMigrationRegistryTest extends TestCase
{
    public function test_all_resolves_only_the_seven_enabled_modules(): void
    {
        $registry = app(ModularMigrationRegistry::class);

        $this->assertSame([
            'pre-matricula',
            'calidad',
            'gestion-horarios',
            'cotecu',
            'gestion-practicas',
            'tesis-proyecto-tesis',
            'gestion-matriculas',
        ], $registry->targets('all'));
    }

    public function test_single_module_keeps_dependency_groups_first(): void
    {
        $groups = app(ModularMigrationRegistry::class)->groups('calidad');

        $this->assertSame(
            ['infraestructura', 'shared', 'calidad'],
            array_column($groups, 'label'),
        );
    }

    public function test_bienestar_is_not_migrable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ModularMigrationRegistry::class)->targets('bienestar');
    }
}
