<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $legacyExists = $this->schemaExists('pre_matricula');
        $currentExists = $this->schemaExists('prematricula');

        if ($legacyExists && $currentExists) {
            throw new RuntimeException(
                'Existen simultáneamente los esquemas pre_matricula y prematricula; se requiere una conciliación manual.',
            );
        }

        if ($legacyExists) {
            DB::statement('ALTER SCHEMA "pre_matricula" RENAME TO "prematricula"');

            return;
        }

        if (! $currentExists) {
            DB::statement('CREATE SCHEMA "prematricula"');
        }
    }

    public function down(): void
    {
        // No se revierte el nombre: el esquema puede contener datos preexistentes.
    }

    private function schemaExists(string $schema): bool
    {
        return DB::table('information_schema.schemata')
            ->where('schema_name', $schema)
            ->exists();
    }
};
