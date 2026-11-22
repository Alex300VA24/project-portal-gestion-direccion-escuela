<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La lista de cotejo del plan curricular no es plana: el currículo
 * agrupa sus ítems por secciones (CARÁTULA, PRESENTACIÓN, 1. BASES
 * GENERALES…). La columna guarda la ruta de la sección —p. ej.
 * «1. BASES GENERALES › 1.1. BASES NORMATIVAS»— para que la revisión
 * pinte un encabezado por bloque consecutivo. Nullable: la lista de
 * sílabos y cualquier lista plana siguen funcionando igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('cotecu.lista_cotejo_items', function (Blueprint $table) {
            $table->string('seccion', 200)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('cotecu.lista_cotejo_items', function (Blueprint $table) {
            $table->dropColumn('seccion');
        });
    }
};
