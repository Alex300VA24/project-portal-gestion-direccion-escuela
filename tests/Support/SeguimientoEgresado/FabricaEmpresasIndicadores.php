<?php

namespace Tests\Support\SeguimientoEgresado;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Crea registros ficticios mínimos con valores conocidos para comprobar fórmulas a mano. */
trait FabricaEmpresasIndicadores
{
    private int $secuencia = 0;

    /** Inicia sesión en el portal con un usuario que tiene el rol de ese perfil (`secretaria` o `director_escuela`). */
    protected function entrarComo(string $perfil = 'SECRETARIA'): void
    {
        $rol = $perfil === 'DIRECTOR' ? 'director_escuela' : 'secretaria';
        Role::findOrCreate($rol, 'web');

        $usuario = User::factory()->create();
        $usuario->assignRole($rol);
        $this->actingAs($usuario);
    }

    protected function usuario(): int
    {
        return (int) DB::table('seguimiento_egresado.usuario_interno')->orderBy('id')->value('id');
    }

    protected function catalogo(string $tabla, string $codigo): int
    {
        return (int) DB::table('seguimiento_egresado.'.$tabla)->where('codigo', $codigo)->value('id');
    }

    protected function plan(): int
    {
        return (int) DB::table('seguimiento_egresado.plan_curricular')->insertGetId([
            'codigo_version' => 'PLAN-'.++$this->secuencia,
            'vigencia_desde' => '2017-01-01',
            'estado' => 'VIGENTE',
        ]);
    }

    protected function cohorte(int $ingresantes = 10, int $anio = 2018, ?int $plan = null): int
    {
        return (int) DB::table('seguimiento_egresado.cohorte')->insertGetId([
            'codigo_promocion' => 'C-'.++$this->secuencia,
            'anio_ingreso' => $anio,
            'total_ingresantes' => $ingresantes,
            'duracion_esperada_anios' => 5,
            'id_plan_curricular' => $plan,
        ]);
    }

    protected function egresado(int $cohorte, ?string $estado = 'EGRESADO', array $cambios = []): int
    {
        $n = ++$this->secuencia;

        $id = (int) DB::table('seguimiento_egresado.egresado')->insertGetId(array_merge([
            'id_cohorte' => $cohorte,
            'codigo_matricula' => 'MAT'.$n,
            'tipo_documento' => 'DNI',
            'numero_documento' => 'DOC'.$n,
            'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia'.$n,
            'programa' => 'Ingeniería Informática',
            'correo_institucional' => 'privado'.$n.'@correo.test',
        ], $cambios));

        if ($estado !== null) {
            DB::table('seguimiento_egresado.historial_estado_academico')->insert([
                'id_egresado' => $id,
                'id_estado_academico' => $this->catalogo('cat_estado_academico', $estado),
                'vigente_desde' => '2020-01-01 00:00:00',
                'id_usuario_registro' => $this->usuario(),
            ]);
        }

        return $id;
    }

    protected function campania(array $cambios = []): int
    {
        return (int) DB::table('seguimiento_egresado.campania_seguimiento')->insertGetId(array_merge([
            'nombre' => 'Campaña '.++$this->secuencia,
            'tipo' => 'EMPRESAS',
            'finalidad' => 'SEGUIMIENTO',
            'horizonte_anios' => 3,
            'periodo' => '2026-II',
            'fecha_inicio' => now()->subDays(30)->toDateString(),
            'fecha_fin' => now()->addDays(30)->toDateString(),
            'estado' => 'ABIERTA',
            'id_usuario_creacion' => $this->usuario(),
        ], $cambios));
    }

    protected function invitacionSeguimiento(int $campania, int $egresado, array $cambios = []): int
    {
        return (int) DB::table('seguimiento_egresado.invitacion_egresado')->insertGetId(array_merge([
            'id_campania' => $campania,
            'id_egresado' => $egresado,
            'proposito' => 'SEGUIMIENTO',
            'token_hash' => hash('sha256', 'seg-'.++$this->secuencia),
            'expira_en' => now()->addDays(30)->format('Y-m-d H:i:sP'),
            'estado' => 'PENDIENTE',
        ], $cambios));
    }

    /** Registra una respuesta de seguimiento. $situacion: TRABAJA o NO_TRABAJA. */
    protected function responder(int $invitacion, string $situacion = 'TRABAJA', ?bool $relacionado = true, ?string $satisfaccion = 'SATISFECHO', array $cambios = []): int
    {
        $medicion = (int) DB::table('seguimiento_egresado.medicion_laboral')->insertGetId(array_merge([
            'id_invitacion' => $invitacion,
            'fecha_respuesta' => now()->subDays(5)->format('Y-m-d H:i:sP'),
            'id_situacion_laboral' => $this->catalogo('cat_situacion_laboral', $situacion),
            'trabajo_relacionado' => $relacionado,
            'id_pais_residencia' => $this->catalogo('cat_pais', 'PE'),
        ], $cambios));

        if ($satisfaccion !== null) {
            DB::table('seguimiento_egresado.satisfaccion_egresado')->insert([
                'id_medicion' => $medicion,
                'id_nivel_satisfaccion' => $this->catalogo('cat_nivel_satisfaccion', $satisfaccion),
            ]);
        }

        DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion)->update(['estado' => 'COMPLETADA']);

        return $medicion;
    }

    protected function invitacionEmpresa(int $campania, string $contexto = 'MERCADO', ?int $egresado = null, array $cambios = []): array
    {
        $token = 'tokenprueba'.str_pad((string) ++$this->secuencia, 24, '0', STR_PAD_LEFT);

        $id = (int) DB::table('seguimiento_egresado.invitacion_empresa')->insertGetId(array_merge([
            'id_campania' => $campania,
            'id_egresado' => $egresado,
            'contexto' => $contexto,
            'token_hash' => hash('sha256', $token),
            'expira_en' => now()->addDays(10)->format('Y-m-d H:i:sP'),
            'estado' => 'PENDIENTE',
        ], $cambios));

        return ['id' => $id, 'token' => $token];
    }

    protected function respuestaEmpresa(int $invitacion, ?string $nivel, array $cambios = []): int
    {
        DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $invitacion)->update(['estado' => 'COMPLETADA', 'respondido_en' => now()->subDays(2)->format('Y-m-d H:i:sP')]);

        return (int) DB::table('seguimiento_egresado.respuesta_empresa')->insertGetId(array_merge([
            'id_invitacion_empresa' => $invitacion,
            'fecha_respuesta' => now()->subDays(2)->format('Y-m-d H:i:sP'),
            'id_nivel_satisfaccion' => $nivel ? $this->catalogo('cat_nivel_satisfaccion', $nivel) : null,
        ], $cambios));
    }

    /** @return array<string, mixed> */
    protected function datosEncuesta(array $cambios = []): array
    {
        return array_merge([
            'organizacion' => 'Organización Ficticia',
            'sector' => 'SOFTWARE_TI',
            'cargo' => 'Líder técnico',
            'demandas' => ['BASES_DATOS', 'CLOUD'],
            'brechas' => ['INGLES'],
            'comentario' => 'Comentario ficticio',
        ], $cambios);
    }

    protected function mejora(array $cambios = []): void
    {
        $fila = array_merge([
            'codigo' => 'MC-'.++$this->secuencia,
            'id_plan_curricular' => $this->plan(),
            'titulo' => 'Mejora ficticia',
            'tipo' => 'PERFIL',
            'origen_seguimiento' => true,
            'descripcion' => 'Descripción ficticia',
            'dice' => 'Texto actual ficticio',
            'debe_decir' => 'Texto propuesto ficticio',
            'estado' => 'IMPLEMENTADA',
            'fecha_registro' => '2024-01-10',
            'fecha_implementacion' => '2024-06-15',
            'origen_evidencia' => 'Informe ficticio',
            'id_usuario_registro' => $this->usuario(),
        ], $cambios);

        // La BD exige fecha de implementación solo cuando la mejora está implementada.
        if ($fila['estado'] !== 'IMPLEMENTADA') {
            $fila['fecha_implementacion'] = null;
        }

        DB::table('seguimiento_egresado.mejora_curricular')->insert($fila);
    }

    protected function objetivo(int $plan, bool $vigente = true): int
    {
        $n = ++$this->secuencia;

        return (int) DB::table('seguimiento_egresado.objetivo_educacional')->insertGetId([
            'id_plan_curricular' => $plan,
            'codigo' => 'OE'.$n,
            'titulo' => 'Objetivo '.$n,
            'descripcion' => 'Descripción ficticia',
            'vigente' => $vigente,
        ]);
    }

    protected function evaluarObjetivo(int $objetivo, int $cohorte, string $nivel, string $estado = 'VALIDADA', string $periodo = '2025-II', string $fecha = '2025-12-10'): void
    {
        DB::table('seguimiento_egresado.evaluacion_oe')->insert([
            'id_objetivo' => $objetivo,
            'id_cohorte' => $cohorte,
            'periodo' => $periodo,
            'id_nivel_logro' => $this->catalogo('cat_nivel_logro', $nivel),
            'estado' => $estado,
            'fecha_evaluacion' => $fecha,
            'id_usuario_registro' => $this->usuario(),
        ]);
    }
}
