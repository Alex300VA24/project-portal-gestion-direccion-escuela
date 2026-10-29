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

        $this->ensureReferenceTablesAreEmpty();
        $this->createIdentityAndAcademicStructure();
        $this->completeExistingReferenceTables();
        $this->createPrerequisites();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->ensureReferenceTablesAreEmpty();

        Schema::dropIfExists('core.curso_prerequisito');
        DB::statement('DROP FUNCTION IF EXISTS core.validar_curso_prerequisito_mismo_plan()');

        if (Schema::hasColumn('seguridad.usuario', 'id_persona')) {
            Schema::table('seguridad.usuario', function (Blueprint $table) {
                $table->dropForeign('seg_usuario_persona_fk');
                $table->dropUnique('seg_usuario_persona_uq');
                $table->dropColumn(['id_persona', 'debe_cambiar_contrasena', 'ultimo_acceso']);
            });
        }

        if (Schema::hasColumn('core.seccion', 'id_docente_responsable')) {
            if ($this->constraintExists('core', 'seccion', 'core_seccion_docente_fk')) {
                Schema::table('core.seccion', function (Blueprint $table) {
                    $table->dropForeign('core_seccion_docente_fk');
                });
            }
            DB::statement('DROP INDEX IF EXISTS core.core_seccion_docente_idx');
            Schema::table('core.seccion', function (Blueprint $table) {
                $table->dropColumn('id_docente_responsable');
            });
        }

        Schema::table('core.curso_plan', function (Blueprint $table) {
            $table->dropForeign('core_curso_plan_plan_fk');
            $table->dropForeign('core_curso_plan_curso_fk');
            $table->dropForeign('core_curso_plan_area_fk');
            $table->dropUnique('core_curso_plan_plan_curso_uq');
            $table->dropColumn(['id_plan', 'id_curso', 'tipo', 'id_area', 'creditos_requeridos']);
            $table->string('codigo', 50)->unique('core_curso_plan_codigo_uq');
            $table->decimal('creditos', 5, 2)->nullable();
            $table->string('estado', 30);
        });
        DB::statement('DROP INDEX IF EXISTS core.core_curso_plan_curso_idx');
        DB::statement('DROP INDEX IF EXISTS core.core_curso_plan_area_idx');
        DB::statement('ALTER TABLE core.curso_plan ALTER COLUMN ciclo DROP NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE core.curso_plan
            ADD CONSTRAINT core_curso_plan_creditos_ck
            CHECK (creditos IS NULL OR creditos >= 0)
            SQL);

        Schema::table('core.estudiante', function (Blueprint $table) {
            $table->dropForeign('core_estudiante_persona_fk');
            $table->dropForeign('core_estudiante_escuela_fk');
            $table->dropForeign('core_estudiante_plan_fk');
            $table->dropForeign('core_estudiante_periodo_fk');
            $table->dropForeign('core_estudiante_modalidad_fk');
            $table->dropUnique('core_estudiante_persona_escuela_uq');
            $table->dropColumn([
                'id_persona',
                'id_escuela',
                'id_plan',
                'id_periodo_ingreso',
                'id_modalidad_ingreso',
                'fecha_egreso',
            ]);
            $table->renameColumn('codigo_universitario', 'codigo_estudiante');
            $table->timestampTz('creado_en')->useCurrent();
        });
        DB::statement('DROP INDEX IF EXISTS core.core_estudiante_plan_idx');
        DB::statement('DROP INDEX IF EXISTS core.core_estudiante_periodo_idx');
        DB::statement('DROP INDEX IF EXISTS core.core_estudiante_modalidad_idx');

        Schema::table('core.periodo_academico', function (Blueprint $table) {
            $table->dropUnique('core_periodo_anio_tipo_uq');
            $table->dropColumn(['anio', 'tipo', 'numero_semanas']);
            $table->string('nombre', 100);
        });

        Schema::dropIfExists('core.docente');
        Schema::dropIfExists('core.curso');
        Schema::dropIfExists('core.area_formacion');
        Schema::dropIfExists('core.plan_estudios');
        Schema::dropIfExists('core.modalidad_ingreso');
        Schema::dropIfExists('core.departamento_academico');
        Schema::dropIfExists('core.escuela_profesional');
        Schema::dropIfExists('core.facultad');
        Schema::dropIfExists('core.persona');
        Schema::dropIfExists('core.ubigeo_distrito');
        Schema::dropIfExists('core.ubigeo_provincia');
        Schema::dropIfExists('core.ubigeo_departamento');
        Schema::dropIfExists('core.pais');
    }

    private function createIdentityAndAcademicStructure(): void
    {
        Schema::create('core.pais', function (Blueprint $table) {
            $table->bigIncrements('id_pais');
            $table->string('nombre', 120)->unique('core_pais_nombre_uq');
        });

        Schema::create('core.ubigeo_departamento', function (Blueprint $table) {
            $table->bigIncrements('id_departamento');
            $table->string('nombre', 120)->unique('core_ubigeo_dep_nombre_uq');
        });

        Schema::create('core.ubigeo_provincia', function (Blueprint $table) {
            $table->bigIncrements('id_provincia');
            $table->bigInteger('id_departamento');
            $table->string('nombre', 120);
            $table->unique(['id_departamento', 'nombre'], 'core_ubigeo_prov_dep_nombre_uq');
            $table->foreign('id_departamento', 'core_ubigeo_prov_dep_fk')
                ->references('id_departamento')->on('core.ubigeo_departamento')->restrictOnDelete();
        });

        Schema::create('core.ubigeo_distrito', function (Blueprint $table) {
            $table->bigIncrements('id_distrito');
            $table->bigInteger('id_provincia');
            $table->string('nombre', 120);
            $table->unique(['id_provincia', 'nombre'], 'core_ubigeo_dist_prov_nombre_uq');
            $table->foreign('id_provincia', 'core_ubigeo_dist_prov_fk')
                ->references('id_provincia')->on('core.ubigeo_provincia')->restrictOnDelete();
        });

        Schema::create('core.persona', function (Blueprint $table) {
            $table->bigIncrements('id_persona');
            $table->string('tipo_documento', 20);
            $table->string('numero_documento', 30);
            $table->string('nombres', 120);
            $table->string('apellido_paterno', 80);
            $table->string('apellido_materno', 80)->nullable();
            $table->string('sexo', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('correo_personal', 190)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->bigInteger('id_pais_nacionalidad')->nullable();
            $table->bigInteger('id_distrito_domicilio')->nullable();
            $table->text('direccion')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->unique(['tipo_documento', 'numero_documento'], 'core_persona_documento_uq');
            $table->index('id_pais_nacionalidad', 'core_persona_pais_idx');
            $table->index('id_distrito_domicilio', 'core_persona_distrito_idx');
            $table->foreign('id_pais_nacionalidad', 'core_persona_pais_fk')
                ->references('id_pais')->on('core.pais')->restrictOnDelete();
            $table->foreign('id_distrito_domicilio', 'core_persona_distrito_fk')
                ->references('id_distrito')->on('core.ubigeo_distrito')->restrictOnDelete();
        });

        Schema::create('core.facultad', function (Blueprint $table) {
            $table->bigIncrements('id_facultad');
            $table->string('codigo', 30)->unique('core_facultad_codigo_uq');
            $table->string('nombre', 160)->unique('core_facultad_nombre_uq');
        });

        Schema::create('core.escuela_profesional', function (Blueprint $table) {
            $table->bigIncrements('id_escuela');
            $table->bigInteger('id_facultad');
            $table->string('codigo', 30)->unique('core_escuela_codigo_uq');
            $table->string('nombre', 160);
            $table->string('estado', 30);
            $table->foreign('id_facultad', 'core_escuela_facultad_fk')
                ->references('id_facultad')->on('core.facultad')->restrictOnDelete();
        });

        Schema::create('core.departamento_academico', function (Blueprint $table) {
            $table->bigIncrements('id_departamento');
            $table->bigInteger('id_facultad');
            $table->string('codigo', 30)->unique('core_dep_acad_codigo_uq');
            $table->string('nombre', 160);
            $table->foreign('id_facultad', 'core_dep_acad_facultad_fk')
                ->references('id_facultad')->on('core.facultad')->restrictOnDelete();
        });

        Schema::create('core.modalidad_ingreso', function (Blueprint $table) {
            $table->bigIncrements('id_modalidad');
            $table->string('codigo', 30)->unique('core_modalidad_codigo_uq');
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('core.plan_estudios', function (Blueprint $table) {
            $table->bigIncrements('id_plan');
            $table->bigInteger('id_escuela');
            $table->string('codigo', 40)->unique('core_plan_codigo_uq');
            $table->string('nombre', 160);
            $table->string('version', 30);
            $table->smallInteger('anio_aprobacion');
            $table->string('resolucion_aprobacion', 100)->nullable();
            $table->date('fecha_vigencia_inicio');
            $table->date('fecha_vigencia_fin')->nullable();
            $table->boolean('es_predeterminado')->default(false);
            $table->string('estado', 30);
            $table->unique(['id_escuela', 'version'], 'core_plan_escuela_version_uq');
            $table->foreign('id_escuela', 'core_plan_escuela_fk')
                ->references('id_escuela')->on('core.escuela_profesional')->restrictOnDelete();
        });

        Schema::create('core.area_formacion', function (Blueprint $table) {
            $table->bigIncrements('id_area');
            $table->string('codigo', 30)->unique('core_area_codigo_uq');
            $table->string('nombre', 120);
        });

        Schema::create('core.curso', function (Blueprint $table) {
            $table->bigIncrements('id_curso');
            $table->string('codigo', 40)->unique('core_curso_codigo_uq');
            $table->string('nombre', 180);
            $table->decimal('creditos', 5, 2);
            $table->smallInteger('horas_teoria')->default(0);
            $table->smallInteger('horas_practica')->default(0);
            $table->smallInteger('horas_laboratorio')->default(0);
            $table->text('sumilla')->nullable();
            $table->string('estado', 30);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE core.curso
            ADD CONSTRAINT core_curso_horas_creditos_ck
            CHECK (creditos >= 0 AND horas_teoria >= 0 AND horas_practica >= 0 AND horas_laboratorio >= 0)
            SQL);

        Schema::create('core.docente', function (Blueprint $table) {
            $table->bigIncrements('id_docente');
            $table->bigInteger('id_persona')->unique('core_docente_persona_uq');
            $table->string('codigo_docente', 30)->unique('core_docente_codigo_uq');
            $table->bigInteger('id_departamento');
            $table->string('categoria', 50)->nullable();
            $table->string('condicion', 50)->nullable();
            $table->string('dedicacion', 50)->nullable();
            $table->string('grado_academico', 80)->nullable();
            $table->smallInteger('horas_maximas')->nullable();
            $table->string('orcid', 30)->nullable()->unique('core_docente_orcid_uq');
            $table->string('codigo_renacyt', 40)->nullable();
            $table->string('estado', 30);
            $table->foreign('id_persona', 'core_docente_persona_fk')
                ->references('id_persona')->on('core.persona')->restrictOnDelete();
            $table->foreign('id_departamento', 'core_docente_departamento_fk')
                ->references('id_departamento')->on('core.departamento_academico')->restrictOnDelete();
        });
    }

    private function completeExistingReferenceTables(): void
    {
        Schema::table('core.periodo_academico', function (Blueprint $table) {
            $table->smallInteger('anio');
            $table->string('tipo', 20);
            $table->smallInteger('numero_semanas');
            $table->unique(['anio', 'tipo'], 'core_periodo_anio_tipo_uq');
            $table->dropColumn('nombre');
        });

        Schema::table('core.estudiante', function (Blueprint $table) {
            $table->renameColumn('codigo_estudiante', 'codigo_universitario');
            $table->bigInteger('id_persona');
            $table->bigInteger('id_escuela');
            $table->bigInteger('id_plan');
            $table->bigInteger('id_periodo_ingreso');
            $table->bigInteger('id_modalidad_ingreso');
            $table->date('fecha_egreso')->nullable();
            $table->dropColumn('creado_en');
            $table->unique(['id_persona', 'id_escuela'], 'core_estudiante_persona_escuela_uq');
            $table->index('id_plan', 'core_estudiante_plan_idx');
            $table->index('id_periodo_ingreso', 'core_estudiante_periodo_idx');
            $table->index('id_modalidad_ingreso', 'core_estudiante_modalidad_idx');
            $table->foreign('id_persona', 'core_estudiante_persona_fk')
                ->references('id_persona')->on('core.persona')->restrictOnDelete();
            $table->foreign('id_escuela', 'core_estudiante_escuela_fk')
                ->references('id_escuela')->on('core.escuela_profesional')->restrictOnDelete();
            $table->foreign('id_plan', 'core_estudiante_plan_fk')
                ->references('id_plan')->on('core.plan_estudios')->restrictOnDelete();
            $table->foreign('id_periodo_ingreso', 'core_estudiante_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
            $table->foreign('id_modalidad_ingreso', 'core_estudiante_modalidad_fk')
                ->references('id_modalidad')->on('core.modalidad_ingreso')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE core.curso_plan DROP CONSTRAINT core_curso_plan_creditos_ck');
        Schema::table('core.curso_plan', function (Blueprint $table) {
            $table->dropUnique('core_curso_plan_codigo_uq');
            $table->dropColumn(['codigo', 'creditos', 'estado']);
            $table->bigInteger('id_plan');
            $table->bigInteger('id_curso');
            $table->string('tipo', 30);
            $table->bigInteger('id_area');
            $table->decimal('creditos_requeridos', 5, 2)->nullable();
            $table->unique(['id_plan', 'id_curso'], 'core_curso_plan_plan_curso_uq');
            $table->index('id_curso', 'core_curso_plan_curso_idx');
            $table->index('id_area', 'core_curso_plan_area_idx');
            $table->foreign('id_plan', 'core_curso_plan_plan_fk')
                ->references('id_plan')->on('core.plan_estudios')->restrictOnDelete();
            $table->foreign('id_curso', 'core_curso_plan_curso_fk')
                ->references('id_curso')->on('core.curso')->restrictOnDelete();
            $table->foreign('id_area', 'core_curso_plan_area_fk')
                ->references('id_area')->on('core.area_formacion')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE core.curso_plan ALTER COLUMN ciclo SET NOT NULL');

        Schema::table('core.seccion', function (Blueprint $table) {
            $table->bigInteger('id_docente_responsable')->nullable();
            $table->index('id_docente_responsable', 'core_seccion_docente_idx');
            $table->foreign('id_docente_responsable', 'core_seccion_docente_fk')
                ->references('id_docente')->on('core.docente')->restrictOnDelete();
        });

        Schema::table('seguridad.usuario', function (Blueprint $table) {
            $table->bigInteger('id_persona')->unique('seg_usuario_persona_uq');
            $table->boolean('debe_cambiar_contrasena')->default(true);
            $table->timestampTz('ultimo_acceso')->nullable();
            $table->foreign('id_persona', 'seg_usuario_persona_fk')
                ->references('id_persona')->on('core.persona')->restrictOnDelete();
        });
    }

    private function createPrerequisites(): void
    {
        Schema::create('core.curso_prerequisito', function (Blueprint $table) {
            $table->bigInteger('id_curso_plan');
            $table->bigInteger('id_curso_plan_requisito');
            $table->primary(['id_curso_plan', 'id_curso_plan_requisito'], 'core_curso_prerequisito_pk');
            $table->index('id_curso_plan_requisito', 'core_curso_prerequisito_req_idx');
            $table->foreign('id_curso_plan', 'core_curso_prerequisito_curso_fk')
                ->references('id_curso_plan')->on('core.curso_plan')->restrictOnDelete();
            $table->foreign('id_curso_plan_requisito', 'core_curso_prerequisito_req_fk')
                ->references('id_curso_plan')->on('core.curso_plan')->restrictOnDelete();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE core.curso_prerequisito
            ADD CONSTRAINT core_curso_prerequisito_distinto_ck
            CHECK (id_curso_plan <> id_curso_plan_requisito)
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION core.validar_curso_prerequisito_mismo_plan()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                plan_curso BIGINT;
                plan_requisito BIGINT;
            BEGIN
                SELECT id_plan INTO plan_curso
                FROM core.curso_plan
                WHERE id_curso_plan = NEW.id_curso_plan;

                SELECT id_plan INTO plan_requisito
                FROM core.curso_plan
                WHERE id_curso_plan = NEW.id_curso_plan_requisito;

                IF plan_curso IS DISTINCT FROM plan_requisito THEN
                    RAISE EXCEPTION 'El curso y su prerrequisito deben pertenecer al mismo plan de estudios'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER core_curso_prerequisito_mismo_plan_trg
            BEFORE INSERT OR UPDATE ON core.curso_prerequisito
            FOR EACH ROW
            EXECUTE FUNCTION core.validar_curso_prerequisito_mismo_plan();
            SQL);
    }

    private function ensureReferenceTablesAreEmpty(): void
    {
        foreach ([
            'prematricula.detalle_solicitud',
            'prematricula.solicitud_prematricula',
            'prematricula.cronograma_prematricula',
            'core.seccion',
            'core.curso_plan',
            'core.estudiante',
            'core.periodo_academico',
            'seguridad.usuario',
        ] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("La migración requiere que {$table} esté vacía.");
            }
        }
    }

    private function constraintExists(string $schema, string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }
};
