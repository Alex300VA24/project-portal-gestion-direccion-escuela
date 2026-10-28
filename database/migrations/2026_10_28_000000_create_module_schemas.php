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
};
