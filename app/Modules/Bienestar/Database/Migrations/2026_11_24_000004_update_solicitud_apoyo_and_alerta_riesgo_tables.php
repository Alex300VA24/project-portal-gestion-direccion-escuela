<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Modificar bienestar.alerta_riesgo
        Schema::table('bienestar.alerta_riesgo', function (Blueprint $table) {
            $table->timestampTz('fecha_filtro_secretaria')->nullable();
            $table->bigInteger('filtrado_por')->nullable();

            $table->foreign('filtrado_por', 'bie_alerta_filtrado_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->nullOnDelete();
        });

        // Actualizar alertas previas en 'pendiente' a 'en_revision' para el flujo de Secretaría
        DB::statement("UPDATE bienestar.alerta_riesgo SET estado = 'en_revision' WHERE estado = 'pendiente';");
        DB::statement("ALTER TABLE bienestar.alerta_riesgo ALTER COLUMN estado SET DEFAULT 'en_revision';");

        // 2. Modificar bienestar.solicitud_apoyo
        DB::statement("ALTER TABLE bienestar.solicitud_apoyo ALTER COLUMN id_beneficio DROP NOT NULL;");

        Schema::table('bienestar.solicitud_apoyo', function (Blueprint $table) {
            $table->bigInteger('id_tipo_solicitud')->nullable();
            $table->boolean('folio_externo_confirmado')->default(false);
            $table->bigInteger('revisado_por_secretaria')->nullable();
            $table->timestampTz('fecha_revision_secretaria')->nullable();
            $table->timestampTz('fecha_remision_bienestar')->nullable();
            $table->text('observacion_general_secretaria')->nullable();

            $table->foreign('id_tipo_solicitud', 'bie_solicitud_tipo_fk')
                ->references('id_tipo_solicitud')->on('bienestar.tipo_solicitud')
                ->nullOnDelete();

            $table->foreign('revisado_por_secretaria', 'bie_solicitud_rev_sec_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->nullOnDelete();

            $table->index('id_tipo_solicitud', 'bie_solicitud_tipo_idx');
        });

        // 3. Crear tipo catalogado 'General (migrado)' y requisito base para preservar datos
        $idTipoMigrado = DB::table('bienestar.tipo_solicitud')->insertGetId([
            'nombre' => 'General (migrado)',
            'descripcion' => 'Tipo asignado automáticamente a solicitudes históricas preexistentes a la catalogación formal UNT.',
            'unidad_responsable' => 'Dirección de Bienestar Universitario',
            'plazo_dias_habiles' => 5,
            'costo_referencial' => 0.00,
            'base_normativa_fuente' => 'Procedimientos históricos preexistentes',
            'estado_verificacion' => 'pendiente_verificar',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_tipo_solicitud');

        $idReqMigrado = DB::table('bienestar.tipo_solicitud_requisito')->insertGetId([
            'id_tipo_solicitud' => $idTipoMigrado,
            'nombre' => 'Documentación adjunta migrada',
            'descripcion' => 'Expediente histórico digitalizado',
            'es_obligatorio' => false,
            'formatos_permitidos' => 'pdf,jpg,png',
            'tamano_maximo_mb' => 10,
            'orden' => 1,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_requisito');

        // 4. Migración de datos existentes de solicitudes
        DB::table('bienestar.solicitud_apoyo')
            ->whereNull('id_tipo_solicitud')
            ->update([
                'id_tipo_solicitud' => $idTipoMigrado,
                'folio_externo_confirmado' => true,
            ]);

        // Mapeo claro de estados históricos
        DB::statement("UPDATE bienestar.solicitud_apoyo SET estado = 'enviada' WHERE lower(estado) = 'pendiente';");
        DB::statement("UPDATE bienestar.solicitud_apoyo SET estado = 'aprobada' WHERE lower(estado) in ('aprobado', 'aprobada');");
        DB::statement("UPDATE bienestar.solicitud_apoyo SET estado = 'rechazada' WHERE lower(estado) in ('rechazado', 'rechazada');");
        DB::statement("ALTER TABLE bienestar.solicitud_apoyo ALTER COLUMN estado SET DEFAULT 'enviada';");

        // Migrar documentos antiguos a la nueva tabla solicitud_requisito_documento
        $documentosAntiguos = DB::table('bienestar.solicitud_documento')->get();
        foreach ($documentosAntiguos as $doc) {
            DB::table('bienestar.solicitud_requisito_documento')->insertOrIgnore([
                'id_solicitud' => $doc->id_solicitud,
                'id_requisito' => $idReqMigrado,
                'id_archivo' => $doc->id_archivo,
                'estado_revision' => 'conforme',
                'comentario_revision' => 'Documento migrado automáticamente',
                'subido_en' => now(),
                'actualizado_en' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('bienestar.solicitud_apoyo', function (Blueprint $table) {
            $table->dropForeign('bie_solicitud_tipo_fk');
            $table->dropForeign('bie_solicitud_rev_sec_fk');
            $table->dropColumn([
                'id_tipo_solicitud',
                'folio_externo_confirmado',
                'revisado_por_secretaria',
                'fecha_revision_secretaria',
                'fecha_remision_bienestar',
                'observacion_general_secretaria',
            ]);
        });

        Schema::table('bienestar.alerta_riesgo', function (Blueprint $table) {
            $table->dropForeign('bie_alerta_filtrado_fk');
            $table->dropColumn([
                'fecha_filtro_secretaria',
                'filtrado_por',
            ]);
        });
    }
};
