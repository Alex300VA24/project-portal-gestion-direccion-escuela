<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $schemas = ['core', 'seguridad'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->schemas as $schema) {
            if (! $this->schemaExists($schema)) {
                DB::statement(sprintf('CREATE SCHEMA "%s"', $schema));
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse($this->schemas) as $schema) {
            if ($this->schemaExists($schema)) {
                DB::statement(sprintf('DROP SCHEMA "%s"', $schema));
            }
        }
    }

    private function schemaExists(string $schema): bool
    {
        return DB::table('information_schema.schemata')
            ->where('schema_name', $schema)
            ->exists();
    }
};
