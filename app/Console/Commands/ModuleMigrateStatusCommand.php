<?php

namespace App\Console\Commands;

use App\Shared\Database\ModularMigrationRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ModuleMigrateStatusCommand extends Command
{
    protected $signature = 'module:migrate:status {module=all : Slug del módulo o all}';

    protected $description = 'Muestra el estado de las migraciones modulares por grupo.';

    public function handle(ModularMigrationRegistry $registry): int
    {
        try {
            $groups = $registry->groups((string) $this->argument('module'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($groups as $group) {
            $this->components->info("Estado: {$group['label']}");

            $exitCode = $this->call('migrate:status', [
                '--path' => [$group['path']],
                '--realpath' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }
}
