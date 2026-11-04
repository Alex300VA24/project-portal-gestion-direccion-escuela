<?php

namespace App\Shared\Database;

use InvalidArgumentException;

final class ModularMigrationRegistry
{
    public const ALL = 'all';

    /** @return array<string, array<string, mixed>> */
    public function enabledModules(): array
    {
        return array_filter(
            config('modules'),
            static fn (array $module): bool => (bool) ($module['migrations_enabled'] ?? false),
        );
    }

    /** @return list<string> */
    public function targets(string $target): array
    {
        if ($target === self::ALL) {
            return array_keys($this->enabledModules());
        }

        if (! isset($this->enabledModules()[$target])) {
            throw new InvalidArgumentException("Módulo no migrable: {$target}");
        }

        return [$target];
    }

    /** @return list<array{label: string, path: string}> */
    public function groups(string $target): array
    {
        $groups = [
            ['label' => 'infraestructura', 'path' => database_path('migrations')],
            ['label' => 'shared', 'path' => app_path('Shared/Database/Migrations')],
        ];

        foreach ($this->targets($target) as $slug) {
            $module = $this->enabledModules()[$slug];
            $groups[] = [
                'label' => $slug,
                'path' => base_path($module['migration_path']),
            ];
        }

        return $groups;
    }
}
