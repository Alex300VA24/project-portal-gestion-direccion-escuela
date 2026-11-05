<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Garantiza que el código de cada módulo viva dentro de su carpeta y que los
 * módulos sean independientes entre sí. Lo compartido va en app/Shared.
 */
final class ModuleStructureTest extends TestCase
{
    private const BASE_PATH_DEPTH = 2;

    public function test_app_models_only_contains_the_laravel_auth_user(): void
    {
        $files = $this->phpFiles($this->path('app/Models'));

        $this->assertSame(
            ['User.php'],
            array_map('basename', $files),
            'Los modelos de módulo van en app/Modules/<Modulo>/Models; los compartidos en app/Shared/Models.',
        );
    }

    public function test_no_module_specific_code_lives_outside_modules(): void
    {
        foreach (['Controllers', 'Models', 'Services', 'Policies', 'Requests'] as $layer) {
            foreach ($this->moduleSlugs() as $module) {
                $outside = array_filter(
                    $this->phpFiles($this->path("app/Http/{$layer}")),
                    static fn (string $file): bool => stripos($file, $module) !== false,
                );

                $this->assertSame([], array_values($outside), "Código de {$module} fuera de su módulo.");
            }
        }
    }

    public function test_modules_do_not_import_classes_from_other_modules(): void
    {
        $violations = [];

        foreach ($this->moduleDirectories() as $module => $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $source = (string) file_get_contents($file);

                preg_match_all('/App\\\\Modules\\\\([A-Za-z]+)\\\\/', $source, $matches);

                foreach (array_unique($matches[1]) as $referenced) {
                    if ($referenced !== $module) {
                        $violations[] = $this->relative($file)." usa el módulo {$referenced}";
                    }
                }
            }
        }

        $this->assertSame([], $violations, 'Un módulo no puede depender de otro; use app/Shared.');
    }

    public function test_module_namespaces_match_their_directory(): void
    {
        $violations = [];

        foreach ($this->moduleDirectories() as $module => $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                $source = (string) file_get_contents($file);

                if (str_contains($file, DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR)) {
                    continue; // las migraciones son clases anónimas
                }

                if (preg_match('/^namespace\s+([^;]+);/m', $source, $match) !== 1) {
                    continue; // archivos de rutas sin namespace
                }

                if (! str_starts_with($match[1], "App\\Modules\\{$module}\\")) {
                    $violations[] = $this->relative($file)." declara {$match[1]}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_module_migrations_stay_inside_their_module_directory(): void
    {
        $this->assertSame(
            [],
            $this->phpFiles($this->path('database/migrations'), '/(prematricula|bienestar|investigacion|seguimiento|web_institucional|calidad|horarios|cotecu|practicas|tesis|matricula)/i'),
            'Las migraciones de módulo van en app/Modules/<Modulo>/Database/Migrations.',
        );

        foreach ($this->moduleDirectories() as $module => $directory) {
            $this->assertDirectoryExists("{$directory}/Database/Migrations", "Módulo {$module} sin carpeta de migraciones.");
        }
    }

    /** @return array<string, string> nombre de carpeta => ruta absoluta */
    private function moduleDirectories(): array
    {
        $directories = [];

        foreach (glob($this->path('app/Modules/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            if (basename($directory) === 'WebInstitucional') {
                continue; // Es una superficie pública, no un módulo seleccionable.
            }

            $directories[basename($directory)] = $directory;
        }

        $this->assertCount(11, $directories);

        return $directories;
    }

    /** @return list<string> */
    private function moduleSlugs(): array
    {
        return array_keys($this->moduleDirectories());
    }

    /** @return list<string> */
    private function phpFiles(string $directory, ?string $filter = null): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && ($filter === null || preg_match($filter, $file->getFilename()) === 1)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, self::BASE_PATH_DEPTH).'/'.$relative;
    }

    private function relative(string $file): string
    {
        return str_replace('\\', '/', substr($file, strlen($this->path('')) ));
    }
}
