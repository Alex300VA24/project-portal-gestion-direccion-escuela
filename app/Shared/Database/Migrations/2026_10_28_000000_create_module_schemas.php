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

        foreach (config('modules') as $module) {
            if ($module['schema'] === 'tesis' && $this->legacyTesisSchemaMustBeRenamed()) {
                continue;
            }

            DB::statement(sprintf('CREATE SCHEMA IF NOT EXISTS "%s"', $module['schema']));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(config('modules')) as $module) {
            DB::statement(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $module['schema']));
        }
    }

    private function legacyTesisSchemaMustBeRenamed(): bool
    {
        $schemas = DB::selectOne(<<<'SQL'
            SELECT
                EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'tesis') AS canonical_exists,
                EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'tesis_proyecto_tesis') AS legacy_exists
            SQL);

        return ! $schemas->canonical_exists && $schemas->legacy_exists;
    }
};
