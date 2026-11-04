<?php

namespace App\Console\Commands;

use App\Shared\Database\ModularMigrationRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ModuleMigrateCommand extends Command
{
    protected $signature = 'module:migrate
        {module=all : Slug del módulo o all}
        {--force : Ejecutar en producción}
        {--pretend : Mostrar SQL sin ejecutarlo}
        {--step : Crear un lote por migración}';

    protected $description = 'Ejecuta migraciones en orden: infraestructura, shared y módulo(s).';

    public function handle(ModularMigrationRegistry $registry): int
    {
        try {
            $groups = $registry->groups((string) $this->argument('module'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($groups as $group) {
            $this->components->info("Migrando {$group['label']}");

            $exitCode = $this->call('migrate', [
                '--path' => [$group['path']],
                '--realpath' => true,
                '--force' => (bool) $this->option('force'),
                '--pretend' => (bool) $this->option('pretend'),
                '--step' => (bool) $this->option('step'),
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }
}
