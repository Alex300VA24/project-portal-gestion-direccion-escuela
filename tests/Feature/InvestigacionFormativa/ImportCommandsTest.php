<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class ImportCommandsTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_importacion_de_identidades_es_atomica_ante_fila_invalida(): void
    {
        $user = User::factory()->create(['email' => 'import.valid@example.test']);
        $this->crearUsuarioAcademico(8899, 9099);

        $csv = $this->csv(
            ['user_id', 'id_usuario_seguridad', 'activo'],
            [
                [$user->id, 8899, 1],
                [999999, 8899, 1],
            ],
        );

        try {
            $this->artisan('investigacion:importar-identidades', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(1);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseMissing('investigacion_formativa.identidad_usuario', [
            'user_id' => $user->id,
        ]);
    }

    public function test_importacion_rechaza_vinculacion_academica_duplicada(): void
    {
        $user = User::factory()->create(['email' => 'import.duplicate@example.test']);

        $csv = $this->csv(
            ['user_id', 'id_usuario_seguridad', 'activo'],
            [[$user->id, 8802, 1]],
        );

        try {
            $this->artisan('investigacion:importar-identidades', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(1);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseMissing('investigacion_formativa.identidad_usuario', [
            'user_id' => $user->id,
        ]);
    }

    public function test_importacion_de_directores_agrega_alcance_sin_borrar_otros(): void
    {
        $csv = $this->csv(
            ['user_id', 'id_escuela', 'activo'],
            [[$this->datos['users']['director']->id, 921, 1]],
        );

        try {
            $this->artisan('investigacion:importar-directores', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(0);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseHas('investigacion_formativa.director_escuela', [
            'user_id' => $this->datos['users']['director']->id,
            'id_escuela' => 921,
            'activo' => true,
        ]);
        $this->assertDatabaseHas('investigacion_formativa.director_escuela', [
            'user_id' => $this->datos['users']['director']->id,
            'id_escuela' => 920,
            'activo' => true,
        ]);
    }

    public function test_importacion_de_participantes_actualiza_estado_sin_borrar_filas_ausentes(): void
    {
        $csv = $this->csv(
            ['id_seccion', 'id_estudiante', 'estado', 'fuente', 'referencia_origen'],
            [
                [9901, 9901, 'retirado', 'matricula', 'corte-2026-2'],
                [9901, 9902, 'activo', 'matricula', 'corte-2026-2'],
            ],
        );

        try {
            $this->artisan('investigacion:importar-participantes', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(0);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseHas('investigacion_formativa.participante_seccion', [
            'id_seccion' => 9901,
            'id_estudiante' => 9901,
            'estado' => 'retirado',
        ]);
        $this->assertDatabaseHas('investigacion_formativa.participante_seccion', [
            'id_seccion' => 9901,
            'id_estudiante' => 9904,
            'estado' => 'retirado',
        ]);
        $this->assertDatabaseHas('investigacion_formativa.participante_seccion', [
            'id_seccion' => 9901,
            'id_estudiante' => 9905,
            'estado' => 'activo',
        ]);
    }

    public function test_importacion_de_competencias_preserva_seleccion_existente(): void
    {
        DB::table('investigacion_formativa.competencia_seccion')->insert([
            'id_seccion' => 9901,
            'codigo' => 'C1',
            'descripcion' => 'Descripción anterior',
            'fuente' => 'Sílabo anterior',
            'seleccionada' => true,
        ]);

        $csv = $this->csv(
            ['id_seccion', 'codigo', 'descripcion', 'fuente', 'seleccionada'],
            [
                [9901, 'C1', 'Descripción nueva', 'Sílabo nuevo', 0],
                [9901, 'C2', 'Competencia nueva', 'Sílabo nuevo', 1],
            ],
        );

        try {
            $this->artisan('investigacion:importar-competencias', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(0);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseHas('investigacion_formativa.competencia_seccion', [
            'id_seccion' => 9901,
            'codigo' => 'C1',
            'descripcion' => 'Descripción nueva',
            'seleccionada' => true,
        ]);
        $this->assertDatabaseHas('investigacion_formativa.competencia_seccion', [
            'id_seccion' => 9901,
            'codigo' => 'C2',
            'seleccionada' => true,
        ]);
    }

    public function test_importacion_de_competencias_rechaza_seccion_con_entregas(): void
    {
        $this->crearEntregaParaSeccion(9901);

        $csv = $this->csv(
            ['id_seccion', 'codigo', 'descripcion', 'fuente', 'seleccionada'],
            [[9901, 'C3', 'Competencia tardía', 'Sílabo nuevo', 0]],
        );

        try {
            $this->artisan('investigacion:importar-competencias', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(1);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseMissing('investigacion_formativa.competencia_seccion', [
            'id_seccion' => 9901,
            'codigo' => 'C3',
        ]);
    }

    public function test_importacion_de_secretarias_solo_valida_con_dry_run(): void
    {
        $csv = $this->csv(
            ['user_id', 'id_escuela', 'activo'],
            [[$this->datos['users']['secretaria_sin_asignar']->id, 920, 1]],
        );

        try {
            $this->artisan('investigacion:importar-secretarias', ['csv' => $csv, '--dry-run' => true])
                ->assertExitCode(0);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseMissing('investigacion_formativa.secretaria_escuela', [
            'user_id' => $this->datos['users']['secretaria_sin_asignar']->id,
            'id_escuela' => 920,
        ]);
    }

    public function test_importacion_de_secretarias_es_idempotente(): void
    {
        $csv = $this->csv(
            ['user_id', 'id_escuela', 'activo'],
            [[$this->datos['users']['secretaria_sin_asignar']->id, 920, 1]],
        );

        try {
            $this->artisan('investigacion:importar-secretarias', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(0);
            $this->artisan('investigacion:importar-secretarias', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(0);
        } finally {
            unlink($csv);
        }

        $this->assertSame(
            1,
            DB::table('investigacion_formativa.secretaria_escuela')
                ->where('user_id', $this->datos['users']['secretaria_sin_asignar']->id)
                ->where('id_escuela', 920)
                ->count(),
        );
    }

    public function test_importacion_de_secretarias_rechaza_usuarios_sin_rol_o_escuela_inexistente(): void
    {
        $csv = $this->csv(
            ['user_id', 'id_escuela', 'activo'],
            [
                [$this->datos['users']['director']->id, 920, 1],
                [$this->datos['users']['secretaria_sin_asignar']->id, 999999, 1],
            ],
        );

        try {
            $this->artisan('investigacion:importar-secretarias', ['csv' => $csv, '--apply' => true])
                ->assertExitCode(1);
        } finally {
            unlink($csv);
        }

        $this->assertDatabaseMissing('investigacion_formativa.secretaria_escuela', [
            'user_id' => $this->datos['users']['secretaria_sin_asignar']->id,
        ]);
    }

    private function crearUsuarioAcademico(int $idUsuario, int $idPersona): void
    {
        DB::table('core.persona')->insert([
            'id_persona' => $idPersona,
            'tipo_documento' => 'DNI',
            'numero_documento' => '72'.str_pad((string) $idPersona, 6, '0', STR_PAD_LEFT),
            'nombres' => 'Importado',
            'apellido_paterno' => 'Prueba',
        ]);

        DB::table('seguridad.usuario')->insert([
            'id_usuario' => $idUsuario,
            'id_persona' => $idPersona,
            'correo_institucional' => "import{$idUsuario}@if.test",
            'contrasena_hash' => '$2y$12$prueba.funcional.sin.uso.real',
            'debe_cambiar_contrasena' => false,
            'estado' => 'Activo',
        ]);
    }

    private function crearEntregaParaSeccion(int $idSeccion): void
    {
        DB::table('investigacion_formativa.tipo_producto')->insert([
            'id_tipo_producto' => 9961,
            'nombre' => 'Proyecto',
        ]);

        DB::table('investigacion_formativa.grupo')->insert([
            'id_grupo' => 9955,
            'id_seccion' => $idSeccion,
            'nombre' => 'Grupo con entrega',
        ]);

        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => 9963,
            'id_grupo' => 9955,
            'id_linea' => 9801,
            'id_tipo_producto' => 9961,
            'titulo' => 'Proyecto con entrega',
        ]);

        DB::table('investigacion_formativa.entregable')->insert([
            'id_entregable' => 9962,
            'id_seccion' => $idSeccion,
            'nombre' => 'Avance 1',
            'orden' => 1,
        ]);

        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');

        $idArchivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => 'avance.pdf',
            'ruta_almacenamiento' => 'investigacion-formativa/test/avance.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => str_repeat('a', 64),
            'subido_por' => 8802,
        ], 'id_archivo');

        DB::table('investigacion_formativa.entrega')->insert([
            'id_entrega' => 9964,
            'id_proyecto' => 9963,
            'id_entregable' => 9962,
            'version' => 1,
            'id_archivo' => $idArchivo,
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<int|string>>  $rows
     */
    private function csv(array $headers, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'if_import_');
        $handle = fopen($path, 'wb');

        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        fclose($handle);

        return $path;
    }
}
