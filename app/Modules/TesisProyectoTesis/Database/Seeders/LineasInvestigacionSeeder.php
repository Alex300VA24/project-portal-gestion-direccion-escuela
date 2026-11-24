<?php

namespace App\Modules\TesisProyectoTesis\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Lista oficial de líneas de investigación de la Escuela (pendiente de recibir).
 *
 * Dónde cargarla: completar LINEAS con [código, nombre] y ejecutar
 *   php artisan db:seed --class="App\Modules\TesisProyectoTesis\Database\Seeders\LineasInvestigacionSeeder"
 *
 * - Las líneas viven en core.linea_investigacion (esquema compartido): en producción las carga el grupo de
 *   gestión; este seeder sirve en local/testing y como referencia de la lista.
 * - Inserta o actualiza por código (idempotente). Las líneas que ya no estén en la lista quedan «Inactivo»
 *   (no se borran: hay proyectos que las referencian), salvo las de los datos demo (LI-SW, LI-IA, LI-SI).
 *   Solo afecta a las líneas de la Escuela EPII.
 * - Mientras LINEAS esté vacía no hace nada.
 */
final class LineasInvestigacionSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string}> [código, nombre] — por completar con la lista oficial. */
    public const LINEAS = [];

    private const DEMO = ['LI-SW', 'LI-IA', 'LI-SI'];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('LineasInvestigacionSeeder se omite fuera de local/testing: en producción las líneas las carga gestión en core.');

            return;
        }
        if (self::LINEAS === []) {
            $this->command?->info('LineasInvestigacionSeeder: la lista oficial aún no se ha cargado (LINEAS está vacía).');

            return;
        }

        $idEscuela = DB::table('core.escuela_profesional')->where('codigo', 'EPII')->value('id_escuela');

        DB::transaction(function () use ($idEscuela) {
            foreach (self::LINEAS as [$codigo, $nombre]) {
                $datos = ['nombre' => $nombre, 'estado' => 'Activo', 'id_escuela' => $idEscuela];
                DB::table('core.linea_investigacion')->where('codigo', $codigo)->exists()
                    ? DB::table('core.linea_investigacion')->where('codigo', $codigo)->update($datos)
                    : DB::table('core.linea_investigacion')->insert(['codigo' => $codigo, ...$datos]);
            }
            DB::table('core.linea_investigacion')
                ->where('id_escuela', $idEscuela)
                ->whereNotIn('codigo', [...array_column(self::LINEAS, 0), ...self::DEMO])
                ->update(['estado' => 'Inactivo']);
        });
    }
}
