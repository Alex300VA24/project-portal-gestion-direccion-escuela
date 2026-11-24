<?php

namespace App\Modules\TesisProyectoTesis\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * php artisan db:seed --class="App\Modules\TesisProyectoTesis\Database\Seeders\TesisDatabaseSeeder"
 *
 * Catálogos del módulo siempre; datos demo solo en local/testing.
 */
final class TesisDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RubricaSeeder::class, TipoSolicitudSeeder::class]);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoTesisSeeder::class);
        }
    }
}
