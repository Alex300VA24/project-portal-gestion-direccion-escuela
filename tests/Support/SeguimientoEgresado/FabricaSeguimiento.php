<?php

namespace Tests\Support\SeguimientoEgresado;

use Illuminate\Support\Facades\DB;

/** Crea filas ficticias mínimas sobre el esquema vigente (los catálogos ya vienen cargados por el esquema). */
trait FabricaSeguimiento
{
    private int $secuenciaFabrica = 0;

    protected function crearUsuarioInterno(string $rol = 'SECRETARIA', array $cambios = []): int
    {
        $n = ++$this->secuenciaFabrica;

        return DB::table('seguimiento_egresado.usuario_interno')->insertGetId(array_merge([
            'id_rol' => DB::table('seguimiento_egresado.rol_interno')->where('codigo', $rol)->value('id'),
            'nombre_completo' => "Usuario {$n}",
            'correo_institucional' => "usuario{$n}@prueba.test",
            'password_hash' => 'hash-de-prueba',
            'activo' => true,
        ], $cambios));
    }

    protected function crearCohorte(): int
    {
        $planId = DB::table('seguimiento_egresado.plan_curricular')->insertGetId(['codigo_version' => 'PC-'.++$this->secuenciaFabrica, 'vigencia_desde' => '2018-01-01', 'estado' => 'VIGENTE']);

        return DB::table('seguimiento_egresado.cohorte')->insertGetId([
            'codigo_promocion' => '2019-I-'.$this->secuenciaFabrica, 'anio_ingreso' => 2019, 'semestre_ingreso' => 'I',
            'total_ingresantes' => 60, 'id_plan_curricular' => $planId,
        ]);
    }

    protected function crearEgresado(array $cambios = []): int
    {
        $n = ++$this->secuenciaFabrica;

        return DB::table('seguimiento_egresado.egresado')->insertGetId(array_merge([
            'id_cohorte' => $this->crearCohorte(),
            'codigo_matricula' => 'M'.str_pad((string) $n, 9, '0', STR_PAD_LEFT),
            'tipo_documento' => 'DNI', 'numero_documento' => str_pad((string) $n, 8, '7', STR_PAD_LEFT),
            'nombres' => 'Persona', 'apellido_paterno' => "Ficticia{$n}", 'programa' => 'Ingeniería Informática',
        ], $cambios));
    }

    protected function crearCampania(array $cambios = []): int
    {
        return DB::table('seguimiento_egresado.campania_seguimiento')->insertGetId(array_merge([
            'nombre' => 'Seguimiento '.++$this->secuenciaFabrica, 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'horizonte_anios' => 3,
            'periodo' => '2026-II', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-12-31', 'estado' => 'ABIERTA',
            'id_usuario_creacion' => $this->crearUsuarioInterno(),
        ], $cambios));
    }

    protected function crearInvitacion(int $egresadoId, ?int $campaniaId, array $cambios = []): int
    {
        return DB::table('seguimiento_egresado.invitacion_egresado')->insertGetId(array_merge([
            'id_campania' => $campaniaId, 'id_egresado' => $egresadoId,
            'proposito' => $campaniaId === null ? 'CONTACTO_INICIAL' : 'SEGUIMIENTO',
            'token_hash' => hash('sha256', 'token-'.++$this->secuenciaFabrica.'-'.uniqid('', true)),
            'expira_en' => now()->addDays(30), 'estado' => 'PENDIENTE',
        ], $cambios));
    }

    /** Datos de encuesta válidos para quien trabaja; $cambios los ajusta. */
    protected function datosDeEncuesta(array $cambios = []): array
    {
        return array_merge([
            'situacion' => 'TRABAJA', 'pais_residencia' => 'PE', 'ciudad_residencia' => 'Trujillo', 'area_profesional' => 'SOFTWARE',
            'nivel_ingles' => 'INTERMEDIO', 'otros_idiomas' => 'Portugués básico',
            'trabajo_relacionado' => '1', 'modalidad_laboral' => 'DEPENDIENTE', 'modalidad_trabajo' => 'HIBRIDA', 'sector_laboral' => 'PRIVADO',
            'nombre_empleador_cliente' => 'Empresa Ficticia S.A.C.', 'pais_empleador' => 'PE', 'region_empleo' => 'LA_LIBERTAD',
            'autoriza_contacto_empresa' => '1', 'contacto_jefe' => 'jefe@empresa.test',
            'satisfaccion' => 'SATISFECHO', 'comentario' => 'Comentario ficticio',
            'brechas' => ['CLOUD', 'OTRA'], 'detalle_brecha_otra' => 'Gestión ágil', 'fortalezas' => ['BACKEND'],
            'formaciones' => [['tipo' => 'DIPLOMADO', 'estado' => 'EN_CURSO', 'area_formacion' => 'Datos', 'institucion' => 'Universidad Ficticia', 'fecha_inicio' => '2026-03-01']],
        ], $cambios);
    }
}
