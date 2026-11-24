<?php

namespace App\Modules\InvestigacionFormativa\Commands;

use App\Modules\InvestigacionFormativa\Support\CsvImportReader;
use Illuminate\Console\Command;
use InvalidArgumentException;

abstract class AbstractImportCommand extends Command
{
    protected function reader(): CsvImportReader
    {
        return new CsvImportReader;
    }

    protected function applyRequested(): bool
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            throw new InvalidArgumentException('Use solo una opción: --apply o --dry-run.');
        }

        return (bool) $this->option('apply');
    }

    protected function parseInt(string $value, int $line, string $field): int
    {
        if ($value === '' || ! ctype_digit($value)) {
            throw new InvalidArgumentException("Línea {$line}: {$field} debe ser un entero positivo.");
        }

        return (int) $value;
    }

    protected function parseBool(string $value, int $line, string $field): bool
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'si', 'sí', 'activo', 'activa' => true,
            '0', 'false', 'no', 'inactivo', 'inactiva' => false,
            default => throw new InvalidArgumentException(
                "Línea {$line}: {$field} debe ser un valor booleano (1/0, si/no, true/false).",
            ),
        };
    }

    protected function requiredText(string $value, int $line, string $field, int $maxLength): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException("Línea {$line}: {$field} no puede estar vacío.");
        }

        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException(
                "Línea {$line}: {$field} supera los {$maxLength} caracteres.",
            );
        }

        return $trimmed;
    }

    /**
     * @param  list<string>  $errors
     */
    protected function reportErrors(array $errors): int
    {
        $this->components->error('No se aplicaron cambios. Corrija el archivo y vuelva a intentarlo.');

        foreach (array_slice($errors, 0, 50) as $error) {
            $this->line(" - {$error}");
        }

        if (count($errors) > 50) {
            $this->line(' - ... y '.count($errors).' errores más.');
        }

        return self::FAILURE;
    }
}
