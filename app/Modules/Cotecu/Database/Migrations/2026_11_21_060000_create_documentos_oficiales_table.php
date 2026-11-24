<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos oficiales de COTECU: el respaldo en papel de los flujos
 * del módulo (p. ej. la lista de cotejo para evaluar currículo),
 * guardado como enlace a la fuente oficial en Google Docs/Sheets.
 *
 * `permiso` es el nombre completo del permiso que decide quién ve cada
 * documento (cotecu.documento.<clave>): habilitar un perfil para un
 * documento es asignar ese permiso en CotecuPermissionsSeeder, sin
 * tocar la tabla ni la vista. Un documento = un permiso (unique).
 *
 * No hay columna de tipo: el lector de solo lectura de la pantalla
 * muestra la fuente oficial embebida, así que el formato (Docs o
 * Sheets) no se guarda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('cotecu.documentos_oficiales', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('url', 500);
            $table->string('permiso', 100)->unique('cotecu_documento_permiso_uq');
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('cotecu.documentos_oficiales');
    }
};
