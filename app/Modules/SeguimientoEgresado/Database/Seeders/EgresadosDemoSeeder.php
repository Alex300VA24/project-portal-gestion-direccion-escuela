<?php

namespace App\Modules\SeguimientoEgresado\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tres egresados inventados para mostrar el área del egresado (contacto, encuesta e historial).
 * Cada uno entra con el enlace personal de su invitación: /seguimiento-egresado/acceso/{token}
 *
 *   1. Contacto pendiente ............ Valeria Quispe Mendoza     demo-contacto-valeria-4F7K2M9Q
 *   2. Contacto completo, encuesta ... Renzo Paredes Villanueva    demo-encuesta-renzo-8H3T5P1W
 *      pendiente
 *   3. Todo completo ................. Camila Sánchez Ulloa        demo-historial-camila-6B9X2D7R
 *
 * Además: tres egresados con contacto y sin invitación (Joaquín, Fiorella y Luis Alberto, en la cohorte de la campaña) y
 * tres sin contacto de otra cohorte, PROM-2020-I (Daniela, Sebastián y Marisol), para mostrar el contacto inicial por cohorte.
 *
 * En la BD solo se guarda el SHA-256 de cada token. Se puede ejecutar más de una vez sin duplicar datos.
 * Requiere los catálogos (vienen en la migración del módulo).
 */
class EgresadosDemoSeeder extends Seeder
{
    public const TOKEN_CONTACTO = 'demo-contacto-valeria-4F7K2M9Q';

    public const TOKEN_ENCUESTA = 'demo-encuesta-renzo-8H3T5P1W';

    public const TOKEN_HISTORIAL = 'demo-historial-camila-6B9X2D7R';

    private const TOKEN_HISTORIAL_ANTERIOR = 'demo-historial-camila-2025-II';

    public function run(): void
    {
        DB::transaction(function () {
            $usuario = $this->usuarioInterno();
            $cohorte = $this->cohorte();

            $valeria = $this->egresado($cohorte, '2019100231', '72145830', 'Valeria', 'Quispe', 'Mendoza', 'vquispe@correo.test', null, null);
            $andres = $this->egresado($cohorte, '2019100264', '73641850', 'Andrés', 'Torres', 'Vega', 'atorres@correo.test', null, null);
            $renzo = $this->egresado($cohorte, '2019100187', '73920415', 'Renzo', 'Paredes', 'Villanueva', 'rparedes@unitru.edu.pe', '2024-07-19', '2024-I');
            $camila = $this->egresado($cohorte, '2019100094', '70388261', 'Camila', 'Sánchez', 'Ulloa', 'csanchez@unitru.edu.pe', '2023-12-15', '2023-II');

            // Valeria está por egresar (si el catálogo no trae «PROXIMO», queda como egresada).
            $this->estadoAcademico($valeria, $this->idDe('cat_estado_academico', 'PROXIMO') ?? $this->idDe('cat_estado_academico', 'EGRESADO'), false, $usuario);
            $this->estadoAcademico($andres, $this->idDe('cat_estado_academico', 'PROXIMO') ?? $this->idDe('cat_estado_academico', 'EGRESADO'), false, $usuario);
            $this->estadoAcademico($renzo, $this->idDe('cat_estado_academico', 'EGRESADO'), true, $usuario);
            $this->estadoAcademico($camila, $this->idDe('cat_estado_academico', 'TITULADO'), true, $usuario);

            $this->contactoDeRenzo($renzo);
            $this->contactoDeCamila($camila);

            $this->egresadosSinInvitar($cohorte, $usuario);
            $this->egresadosSinContacto($usuario);

            $actual = $this->campania('Seguimiento a egresados 2026-II', '2026-II', '2026-10-05', '2026-12-31', 'ABIERTA', $usuario, $cohorte);
            $anterior = $this->campania('Seguimiento a egresados 2025-II', '2025-II', '2025-10-06', '2025-12-15', 'CERRADA', $usuario, $cohorte);

            // Enlace entregado a mano: no consta como enviado por correo (enviado_en solo se marca con un envío real).
            $this->invitacion(null, $valeria, 'CONTACTO_INICIAL', self::TOKEN_CONTACTO, null, 'PENDIENTE', now()->addDays(90), null);
            $this->invitacion($actual, $renzo, 'SEGUIMIENTO', self::TOKEN_ENCUESTA, 'CORREO_INSTITUCIONAL', 'PENDIENTE', now()->addDays(90), now());
            $this->invitacion($anterior, $camila, 'SEGUIMIENTO', self::TOKEN_HISTORIAL_ANTERIOR, 'CORREO_INSTITUCIONAL', 'EXPIRADA', '2025-12-15 23:59:59-05:00', '2025-10-06 09:00:00-05:00');
            $respondida = $this->invitacion($actual, $camila, 'SEGUIMIENTO', self::TOKEN_HISTORIAL, 'CORREO_INSTITUCIONAL', 'COMPLETADA', now()->addDays(90), '2026-10-05 09:00:00-05:00', '2026-10-18 20:25:00-05:00');

            $this->respuestaDeCamila($respondida, $camila);
        });
    }

    private function idDe(string $tabla, string $codigo): ?int
    {
        $id = DB::table('seguimiento_egresado.'.$tabla)->where('codigo', $codigo)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function usuarioInterno(): int
    {
        $id = DB::table('seguimiento_egresado.usuario_interno')->min('id');

        return $id !== null ? (int) $id : (int) DB::table('seguimiento_egresado.usuario_interno')->insertGetId([
            'id_rol' => $this->idDe('rol_interno', 'SECRETARIA'),
            'nombre_completo' => 'Secretaría Académica',
            'correo_institucional' => 'secretaria.demo@unitru.edu.pe',
            'password_hash' => '!sin-acceso',
        ]);
    }

    /** La promoción de ese año: si ya existe (por ejemplo la de los datos base) se reutiliza, para no repetir promociones. */
    private function cohorte(string $codigo = 'PROM-2019-I', int $anio = 2019): int
    {
        $existente = DB::table('seguimiento_egresado.cohorte')->where('anio_ingreso', $anio)->orderBy('id')->value('id');
        if ($existente !== null) {
            return (int) $existente;
        }

        $plan = DB::table('seguimiento_egresado.plan_curricular')->where('estado', 'VIGENTE')->orderBy('id')->value('id')
            ?? DB::table('seguimiento_egresado.plan_curricular')->insertGetId(['codigo_version' => 'Plan 2018', 'vigencia_desde' => '2018-03-01', 'estado' => 'VIGENTE']);

        return (int) DB::table('seguimiento_egresado.cohorte')->insertGetId([
            'codigo_promocion' => $codigo, 'anio_ingreso' => $anio, 'semestre_ingreso' => 'I', 'total_ingresantes' => 60, 'id_plan_curricular' => $plan,
            'observacion' => "Promoción {$anio}.",
        ]);
    }

    private function egresado(int $cohorte, string $matricula, string $dni, string $nombres, string $paterno, string $materno, string $correo, ?string $egreso, ?string $periodo): int
    {
        $existente = DB::table('seguimiento_egresado.egresado')->where('codigo_matricula', $matricula)->value('id');

        return (int) ($existente ?? DB::table('seguimiento_egresado.egresado')->insertGetId([
            'id_cohorte' => $cohorte, 'codigo_matricula' => $matricula, 'tipo_documento' => 'DNI', 'numero_documento' => $dni,
            'nombres' => $nombres, 'apellido_paterno' => $paterno, 'apellido_materno' => $materno, 'programa' => 'Ingeniería Informática',
            'correo_institucional' => $correo, 'sede' => 'Sede Central (Trujillo)', 'fecha_ingreso' => '2019-03-18',
            'fecha_egreso' => $egreso, 'periodo_egreso' => $periodo,
        ]));
    }

    private function estadoAcademico(int $egresado, int $estado, bool $apto, int $usuario): void
    {
        if (! DB::table('seguimiento_egresado.historial_estado_academico')->where('id_egresado', $egresado)->exists()) {
            DB::table('seguimiento_egresado.historial_estado_academico')->insert([
                'id_egresado' => $egresado, 'id_estado_academico' => $estado, 'apto_graduarse' => $apto, 'id_usuario_registro' => $usuario,
            ]);
        }
    }

    /**
     * Tres egresados más, con contacto registrado y SIN invitación: al pulsar «Emitir invitaciones» en la campaña
     * «Seguimiento a egresados 2026-II», la página de Campañas muestra un enlace personal para cada uno (y otro para
     * Valeria, que todavía no tiene invitación de seguimiento).
     */
    private function egresadosSinInvitar(int $cohorte, int $usuario): void
    {
        $personas = [
            ['2019100142', '74106238', 'Joaquín', 'Rojas', 'Castañeda', 'jrojas@unitru.edu.pe', '2024-07-19', '2024-I', 'alvaroenrique080199@gmail.com', '+51 951 220 764', 'Av. Mansiche 812, Trujillo'],
            ['2019100158', '71853942', 'Fiorella', 'Mendoza', 'Aguilar', 'fmendoza@unitru.edu.pe', '2023-12-15', '2023-II', 'arimanuelg544@gmail.com', '+51 966 438 105', 'Calle Bolívar 233, Trujillo'],
            ['2019100203', '75290614', 'Luis Alberto', 'Cabrera', 'Neyra', 'lcabrera@unitru.edu.pe', '2024-07-19', '2024-I', 'luis.cabrera.n@correo.test', '+51 974 081 556', 'Urb. Las Quintanas, Mz. B Lt. 12, Trujillo'],
        ];

        foreach ($personas as [$matricula, $dni, $nombres, $paterno, $materno, $institucional, $egreso, $periodo, $correo, $celular, $direccion]) {
            $egresado = $this->egresado($cohorte, $matricula, $dni, $nombres, $paterno, $materno, $institucional, $egreso, $periodo);
            $this->estadoAcademico($egresado, $this->idDe('cat_estado_academico', 'EGRESADO'), true, $usuario);

            if (! DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->exists()) {
                DB::table('seguimiento_egresado.contacto_egresado')->insert([
                    'id_egresado' => $egresado, 'correo_personal' => $correo, 'celular_whatsapp' => $celular, 'telefono_fijo' => null,
                    'direccion' => $direccion, 'vigente_desde' => '2024-08-12 10:00:00-05:00', 'origen_registro' => 'SECRETARIA',
                ]);
            }
        }
    }

    /**
     * Tres egresados de otra cohorte (PROM-2020-I, que ninguna campaña convoca) sin contacto y sin enlace: en
     * Padrón → «Contacto inicial de egresados», «Invitar a la cohorte» genera un enlace personal para cada uno.
     */
    private function egresadosSinContacto(int $usuario): void
    {
        $cohorte = $this->cohorte('PROM-2020-I', 2020);

        foreach ([
            ['2020100031', '76402815', 'Daniela', 'Vásquez', 'Lozano', 'dvasquez@unitru.edu.pe', '2025-07-18', '2025-I'],
            ['2020100077', '72915364', 'Sebastián', 'Alva', 'Rodríguez', 'salva@unitru.edu.pe', '2025-07-18', '2025-I'],
            ['2020100112', '77830249', 'Marisol', 'Chávez', 'Terrones', 'mchavez@unitru.edu.pe', '2025-12-12', '2025-II'],
        ] as [$matricula, $dni, $nombres, $paterno, $materno, $correo, $egreso, $periodo]) {
            $egresado = $this->egresado($cohorte, $matricula, $dni, $nombres, $paterno, $materno, $correo, $egreso, $periodo);
            $this->estadoAcademico($egresado, $this->idDe('cat_estado_academico', 'EGRESADO'), true, $usuario);
        }
    }

    private function contactoDeRenzo(int $egresado): void
    {
        if (! DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->exists()) {
            DB::table('seguimiento_egresado.contacto_egresado')->insert([
                'id_egresado' => $egresado, 'correo_personal' => 'renzo.paredes92@correo.test', 'celular_whatsapp' => '+51 944 120 387',
                'telefono_fijo' => null, 'direccion' => 'Jr. Pizarro 540, Trujillo', 'vigente_desde' => '2024-08-05 09:30:00-05:00', 'origen_registro' => 'SECRETARIA',
            ]);
        }
    }

    /** Una versión anterior (cerrada) y la vigente: el historial de contacto no se sobrescribe. */
    private function contactoDeCamila(int $egresado): void
    {
        if (DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->exists()) {
            return;
        }

        DB::table('seguimiento_egresado.contacto_egresado')->insert([
            'id_egresado' => $egresado, 'correo_personal' => 'camila.sanchez@correo.test', 'celular_whatsapp' => '+51 987 654 321',
            'telefono_fijo' => '044 245817', 'direccion' => 'Av. España 1120, Trujillo',
            'vigente_desde' => '2024-01-10 11:00:00-05:00', 'vigente_hasta' => '2026-10-12 08:45:00-05:00', 'origen_registro' => 'FORMULARIO',
        ]);
        DB::table('seguimiento_egresado.contacto_egresado')->insert([
            'id_egresado' => $egresado, 'correo_personal' => 'camila.sanchez.u@correo.test', 'celular_whatsapp' => '+51 987 654 321',
            'telefono_fijo' => '044 245817', 'direccion' => 'Calle Los Cedros 275, Urb. San Andrés, Trujillo',
            'vigente_desde' => '2026-10-12 08:45:00-05:00', 'vigente_hasta' => null, 'origen_registro' => 'FORMULARIO',
        ]);
    }

    private function campania(string $nombre, string $periodo, string $inicio, string $fin, string $estado, int $usuario, int $cohorte): int
    {
        $id = DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', $nombre)->value('id') ?? DB::table('seguimiento_egresado.campania_seguimiento')->insertGetId([
            'nombre' => $nombre, 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'horizonte_anios' => 3, 'descripcion' => 'Seguimiento a egresados',
            'periodo' => $periodo, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'estado' => $estado, 'id_usuario_creacion' => $usuario,
        ]);

        if (! DB::table('seguimiento_egresado.campania_cohorte')->where('id_campania', $id)->where('id_cohorte', $cohorte)->exists()) {
            DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $id, 'id_cohorte' => $cohorte]);
        }

        return (int) $id;
    }

    private function invitacion(?int $campania, int $egresado, string $proposito, string $token, ?string $canal, string $estado, mixed $expira, mixed $enviado, mixed $respondido = null): int
    {
        $hash = hash('sha256', $token);

        return (int) (DB::table('seguimiento_egresado.invitacion_egresado')->where('token_hash', $hash)->value('id') ?? DB::table('seguimiento_egresado.invitacion_egresado')->insertGetId([
            'id_campania' => $campania, 'id_egresado' => $egresado, 'proposito' => $proposito, 'token_hash' => $hash,
            'expira_en' => $expira, 'enviado_en' => $enviado, 'respondido_en' => $respondido,
            'id_canal_envio' => $canal === null ? null : $this->idDe('cat_canal_envio', $canal), 'estado' => $estado,
        ]));
    }

    /** La encuesta 2026-II ya respondida por Camila, con todo lo que guarda el formulario. */
    private function respuestaDeCamila(int $invitacion, int $egresado): void
    {
        if (DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion)->exists()) {
            return;
        }

        $pais = $this->idDe('cat_pais', 'PE');
        $medicion = DB::table('seguimiento_egresado.medicion_laboral')->insertGetId([
            'id_invitacion' => $invitacion, 'fecha_respuesta' => '2026-10-18 20:25:00-05:00',
            'id_situacion_laboral' => $this->idDe('cat_situacion_laboral', 'TRABAJA'), 'trabajo_relacionado' => true,
            'id_modalidad_laboral' => $this->idDe('cat_modalidad_laboral', 'DEPENDIENTE'), 'id_modalidad_trabajo' => $this->idDe('cat_modalidad_trabajo', 'HIBRIDA'),
            'id_sector_laboral' => $this->idDe('cat_sector_laboral', 'PRIVADO'), 'id_area_profesional' => $this->idDe('cat_area_profesional', 'SOFTWARE'),
            'id_pais_residencia' => $pais, 'ciudad_residencia' => 'Trujillo', 'nombre_empleador_cliente' => 'Andes Software Labs S.A.C.',
            'id_pais_empleador' => $pais, 'region_empleo' => 'LA_LIBERTAD', 'nivel_ingles' => 'INTERMEDIO', 'otros_idiomas' => 'Portugués básico',
            'autoriza_contacto_empresa' => true, 'contacto_jefe' => '+51 944 118 205',
        ]);

        DB::table('seguimiento_egresado.satisfaccion_egresado')->insert([
            'id_medicion' => $medicion, 'id_nivel_satisfaccion' => $this->idDe('cat_nivel_satisfaccion', 'SATISFECHO'),
            'comentario' => 'Muy buena base teórica; faltaron más prácticas con servicios en la nube.',
        ]);

        $fortaleza = DB::table('seguimiento_egresado.cat_habilidad')->whereNotIn('codigo', ['OTRA', 'CLOUD'])->orderBy('id')->value('id');
        foreach ([
            [$this->idDe('cat_habilidad', 'CLOUD'), 'BRECHA', null],
            [$this->idDe('cat_habilidad', 'OTRA'), 'BRECHA', 'Gestión ágil de proyectos'],
            [$fortaleza, 'FORTALEZA', null],
        ] as [$habilidad, $tipo, $detalle]) {
            if ($habilidad !== null) {
                DB::table('seguimiento_egresado.medicion_habilidad')->insert(['id_medicion' => $medicion, 'id_habilidad' => $habilidad, 'tipo' => $tipo, 'detalle_otro' => $detalle]);
            }
        }

        DB::table('seguimiento_egresado.formacion_posterior')->insert([
            'id_egresado' => $egresado, 'id_medicion_origen' => $medicion,
            'id_tipo_formacion' => $this->idDe('cat_tipo_formacion', 'DIPLOMADO'), 'id_estado_formacion' => $this->idDe('cat_estado_formacion', 'CULMINADA'),
            'area_formacion' => 'Ciencia de datos', 'institucion' => 'Universidad Nacional de Ingeniería', 'fecha_inicio' => '2025-03-03', 'fecha_fin' => '2025-08-29',
        ]);
    }
}
