<?php

namespace App\Modules\SeguimientoEgresado\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos ficticios para probar la encuesta empresarial y el tablero I1–I6: campañas de 3, 5 y 10 años,
 * historial de una misma cohorte y respuestas de empresas. Todo queda etiquetado como [DEMO].
 * Se ejecuta después de DatosPruebaSeeder y no modifica sus registros.
 * Solo para pruebas: no se ejecuta en producción, ni siquiera con --force.
 */
class EmpresasIndicadoresDemoSeeder extends Seeder
{
    private int $usuario;

    private int $secuenciaEgresado = 0;

    private CarbonImmutable $hoy;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('EmpresasIndicadoresDemoSeeder es solo para pruebas; no se carga en producción.');

            return;
        }

        if (DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'like', '[DEMO]%')->exists()) {
            return;
        }

        $this->hoy = CarbonImmutable::now();
        $this->usuario = (int) DB::table('seguimiento_egresado.usuario_interno')->orderBy('id')->value('id');
        $anio = $this->hoy->year;

        $cohorte2018 = (int) DB::table('seguimiento_egresado.cohorte')->where('codigo_promocion', 'PROM-2018')->value('id');
        $cohorte2019 = (int) DB::table('seguimiento_egresado.cohorte')->where('codigo_promocion', 'PROM-2019')->value('id');
        $plan = (int) DB::table('seguimiento_egresado.cohorte')->where('id', $cohorte2018)->value('id_plan_curricular');

        $cohorte2016 = $this->crearCohorte('PROM-2016-DEMO', 2016, 15, $plan, 11);
        $cohorte2011 = $this->crearCohorte('PROM-2011-DEMO', 2011, 12, $plan, 9);

        $egresados2018 = DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $cohorte2018)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $egresados2016 = DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $cohorte2016)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $egresados2011 = DB::table('seguimiento_egresado.egresado')->where('id_cohorte', $cohorte2011)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $inicioAbierta = $this->hoy->subDays(60)->toDateString();
        $finAbierta = $this->hoy->addDays(60)->toDateString();

        // Campañas de egresados: la misma cohorte responde dos años seguidos sin sobrescribir su historial.
        $anterior = $this->crearCampania('[DEMO] Seguimiento a 3 años '.($anio - 1), 'EGRESADOS', 3, ($anio - 1).'-II', ($anio - 1).'-09-01', ($anio - 1).'-11-30', 'CERRADA', [$cohorte2018]);
        $this->responderCampania($anterior, $egresados2018, CarbonImmutable::create($anio - 1, 10, 10), 'EXPIRADA', 4, 0);

        $actual3 = $this->crearCampania('[DEMO] Seguimiento a 3 años '.$anio, 'EGRESADOS', 3, $anio.'-II', $inicioAbierta, $finAbierta, 'ABIERTA', [$cohorte2018]);
        $this->responderCampania($actual3, $egresados2018, $this->hoy->subDays(30), 'PENDIENTE', 5, 1);

        $actual5 = $this->crearCampania('[DEMO] Seguimiento a 5 años '.$anio, 'EGRESADOS', 5, $anio.'-II', $inicioAbierta, $finAbierta, 'ABIERTA', [$cohorte2016]);
        $this->responderCampania($actual5, $egresados2016, $this->hoy->subDays(25), 'PENDIENTE', 4, 2);

        $actual10 = $this->crearCampania('[DEMO] Seguimiento a 10 años '.$anio, 'EGRESADOS', 10, $anio.'-II', $inicioAbierta, $finAbierta, 'ABIERTA', [$cohorte2011]);
        $this->responderCampania($actual10, $egresados2011, $this->hoy->subDays(20), 'PENDIENTE', 3, 3);

        $this->evaluacionesOe($cohorte2019);
        $this->encuestasEmpresa($anio, $inicioAbierta, $finAbierta, $egresados2018, $egresados2016);
    }

    private function crearCohorte(string $codigo, int $anio, int $ingresantes, int $plan, int $egresados): int
    {
        $cohorte = (int) DB::table('seguimiento_egresado.cohorte')->insertGetId([
            'codigo_promocion' => $codigo,
            'anio_ingreso' => $anio,
            'semestre_ingreso' => 'I',
            'total_ingresantes' => $ingresantes,
            'duracion_esperada_anios' => 5,
            'id_plan_curricular' => $plan,
            'observacion' => 'Cohorte ficticia para demostración.',
        ]);

        $estados = DB::table('seguimiento_egresado.cat_estado_academico')->whereIn('codigo', ['EGRESADO', 'BACHILLER', 'TITULADO'])->pluck('id')->values();

        for ($i = 0; $i < $egresados; $i++) {
            $n = ++$this->secuenciaEgresado;
            $egresado = DB::table('seguimiento_egresado.egresado')->insertGetId([
                'id_cohorte' => $cohorte,
                'codigo_matricula' => 'DEMO'.$anio.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'tipo_documento' => 'DNI',
                'numero_documento' => '9'.$anio.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'nombres' => 'Persona DEMO',
                'apellido_paterno' => 'Ficticia '.$anio,
                'apellido_materno' => str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'programa' => 'Ingeniería Informática',
                'fecha_ingreso' => $anio.'-04-01',
                'fecha_egreso' => ($anio + 5).'-03-31',
                'periodo_egreso' => ($anio + 5).'-I',
            ]);

            DB::table('seguimiento_egresado.contacto_egresado')->insert([
                'id_egresado' => $egresado,
                'correo_personal' => 'demo'.$n.'@correo.test',
                'celular_whatsapp' => '900000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'origen_registro' => 'SECRETARIA',
            ]);

            DB::table('seguimiento_egresado.historial_estado_academico')->insert([
                'id_egresado' => $egresado,
                'id_estado_academico' => $estados[$i % $estados->count()],
                'id_usuario_registro' => $this->usuario,
            ]);
        }

        return $cohorte;
    }

    /** @param  list<int>  $cohortes */
    private function crearCampania(string $nombre, string $tipo, ?int $horizonte, string $periodo, string $inicio, string $fin, string $estado, array $cohortes = []): int
    {
        $id = (int) DB::table('seguimiento_egresado.campania_seguimiento')->insertGetId([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'finalidad' => 'SEGUIMIENTO',
            'horizonte_anios' => $horizonte,
            'descripcion' => 'Campaña ficticia para demostración.',
            'periodo' => $periodo,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => $estado,
            'id_usuario_creacion' => $this->usuario,
        ]);

        foreach ($cohortes as $cohorte) {
            DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $id, 'id_cohorte' => $cohorte]);
        }

        return $id;
    }

    /**
     * Invita a todos; responden todos menos cada persona en la posición indicada por $saltoSinRespuesta.
     * $variante cambia el patrón para que las campañas no den el mismo resultado.
     *
     * @param  list<int>  $egresados
     */
    private function responderCampania(int $campania, array $egresados, CarbonImmutable $fecha, string $estadoSinRespuesta, int $saltoSinRespuesta, int $variante): void
    {
        $paises = DB::table('seguimiento_egresado.cat_pais')->pluck('id', 'codigo');
        $extranjero = $paises->except('PE')->first() ?? $paises['PE'];
        $satisfaccion = DB::table('seguimiento_egresado.cat_nivel_satisfaccion')->pluck('id', 'codigo');
        $patronSatisfaccion = ['SATISFECHO', 'MUY_SATISFECHO', 'NEUTRAL', 'SATISFECHO', 'INSATISFECHO', 'SATISFECHO', 'MUY_SATISFECHO'];
        $modalidades = ['DEPENDIENTE', 'FREELANCE', 'DEPENDIENTE', 'INDEPENDIENTE', 'EMPRENDIMIENTO'];
        $areas = ['SOFTWARE', 'DATOS_IA', 'SOFTWARE', 'CIBERSEGURIDAD', 'CLOUD_DEVOPS'];

        foreach ($egresados as $i => $egresado) {
            $responde = ($i + 1) % $saltoSinRespuesta !== 0;
            $momento = $fecha->addDays($i);

            $invitacion = DB::table('seguimiento_egresado.invitacion_egresado')->insertGetId([
                'id_campania' => $campania,
                'id_egresado' => $egresado,
                'proposito' => 'SEGUIMIENTO',
                'token_hash' => hash('sha256', 'demo-'.bin2hex(random_bytes(16))),
                'expira_en' => $estadoSinRespuesta === 'EXPIRADA' ? $fecha->addDays(60)->format('Y-m-d H:i:sP') : $this->hoy->addDays(60)->format('Y-m-d H:i:sP'),
                'enviado_en' => $fecha->subDays(5)->format('Y-m-d H:i:sP'),
                'respondido_en' => $responde ? $momento->format('Y-m-d H:i:sP') : null,
                'estado' => $responde ? 'COMPLETADA' : $estadoSinRespuesta,
            ]);

            if (! $responde) {
                continue;
            }

            $posicion = $i + $variante;
            $trabaja = $posicion % 6 !== 5;
            $relacionado = $trabaja && $posicion % 4 !== 3;

            $medicion = DB::table('seguimiento_egresado.medicion_laboral')->insertGetId([
                'id_invitacion' => $invitacion,
                'fecha_respuesta' => $momento->format('Y-m-d H:i:sP'),
                'id_situacion_laboral' => $this->catalogo('cat_situacion_laboral', $trabaja ? 'TRABAJA' : 'NO_TRABAJA'),
                'trabajo_relacionado' => $trabaja ? $relacionado : null,
                'id_modalidad_laboral' => $trabaja ? $this->catalogo('cat_modalidad_laboral', $modalidades[$posicion % 5]) : null,
                'id_modalidad_trabajo' => $trabaja ? $this->catalogo('cat_modalidad_trabajo', $posicion % 2 ? 'REMOTA' : 'HIBRIDA') : null,
                'id_sector_laboral' => $trabaja ? $this->catalogo('cat_sector_laboral', 'PRIVADO') : null,
                'id_area_profesional' => $trabaja ? $this->catalogo('cat_area_profesional', $areas[$posicion % 5]) : null,
                'id_pais_residencia' => $posicion % 7 === 6 ? $extranjero : $paises['PE'],
                'nombre_empleador_cliente' => $trabaja ? 'Empresa DEMO '.($posicion % 4 + 1) : null,
                'autoriza_contacto_empresa' => $trabaja,
            ]);

            DB::table('seguimiento_egresado.satisfaccion_egresado')->insert([
                'id_medicion' => $medicion,
                'id_nivel_satisfaccion' => $satisfaccion[$patronSatisfaccion[$posicion % 7]],
            ]);
        }
    }

    private function evaluacionesOe(int $cohorte): void
    {
        $plan = DB::table('seguimiento_egresado.cohorte')->where('id', $cohorte)->value('id_plan_curricular');
        $objetivos = DB::table('seguimiento_egresado.objetivo_educacional')->where('id_plan_curricular', $plan)->where('vigente', true)->orderBy('id')->pluck('id');
        $niveles = ['BUENO', 'MUY_BUENO', 'BUENO', 'REGULAR'];

        foreach ($objetivos as $i => $objetivo) {
            DB::table('seguimiento_egresado.evaluacion_oe')->insert([
                'id_objetivo' => $objetivo,
                'id_cohorte' => $cohorte,
                'periodo' => ($this->hoy->year - 1).'-II',
                'id_nivel_logro' => $this->catalogo('cat_nivel_logro', $niveles[$i % 4]),
                'estado' => 'VALIDADA',
                'observacion' => 'Evaluación ficticia para demostración.',
                'fecha_evaluacion' => ($this->hoy->year - 1).'-12-10',
                'id_usuario_registro' => $this->usuario,
            ]);
        }
    }

    /**
     * @param  list<int>  $egresados2018
     * @param  list<int>  $egresados2016
     */
    private function encuestasEmpresa(int $anio, string $inicio, string $fin, array $egresados2018, array $egresados2016): void
    {
        $campania3 = $this->crearCampania('[DEMO] Evaluación de empleadores a 3 años '.$anio, 'EMPRESAS', 3, $anio.'-II', $inicio, $fin, 'ABIERTA');
        $campania5 = $this->crearCampania('[DEMO] Evaluación de empleadores a 5 años '.$anio, 'EMPRESAS', 5, $anio.'-II', $inicio, $fin, 'ABIERTA');
        $mercado = $this->crearCampania('[DEMO] Demanda de habilidades '.$anio, 'EMPRESAS', null, $anio.'-II', $inicio, $fin, 'ABIERTA');

        $niveles3 = ['SATISFECHO', 'MUY_SATISFECHO', 'SATISFECHO', 'NEUTRAL', 'INSATISFECHO'];
        foreach (array_slice($egresados2018, 0, 5) as $i => $egresado) {
            $this->respuestaEmpresa($campania3, 'DESEMPENO', $egresado, 'Empresa DEMO '.($i + 1), $niveles3[$i], $i);
        }
        $this->invitacionSinResponder($campania3, 'DESEMPENO', $egresados2018[5] ?? null, 'Empresa DEMO pendiente', $this->hoy->addDays(30), 'PENDIENTE');

        $niveles5 = ['MUY_SATISFECHO', 'SATISFECHO', 'SATISFECHO'];
        foreach (array_slice($egresados2016, 0, 3) as $i => $egresado) {
            $this->respuestaEmpresa($campania5, 'DESEMPENO', $egresado, 'Empresa DEMO '.($i + 6), $niveles5[$i], $i + 5);
        }

        for ($i = 0; $i < 4; $i++) {
            $this->respuestaEmpresa($mercado, 'MERCADO', null, 'Organización DEMO '.chr(65 + $i), null, $i + 2);
        }
        $this->invitacionSinResponder($mercado, 'MERCADO', null, 'Organización DEMO vencida', $this->hoy->subDays(3), 'EXPIRADA');
        $this->invitacionSinResponder($mercado, 'MERCADO', null, 'Organización DEMO pendiente', $this->hoy->addDays(20), 'PENDIENTE');
    }

    private function respuestaEmpresa(int $campania, string $contexto, ?int $egresado, string $organizacion, ?string $nivel, int $posicion): void
    {
        $fecha = $this->hoy->subDays(15 - $posicion % 10);

        $invitacion = DB::table('seguimiento_egresado.invitacion_empresa')->insertGetId([
            'id_campania' => $campania,
            'id_egresado' => $egresado,
            'contexto' => $contexto,
            'organizacion' => $organizacion,
            'token_hash' => hash('sha256', 'demo-'.bin2hex(random_bytes(16))),
            'expira_en' => $this->hoy->addDays(30)->format('Y-m-d H:i:sP'),
            'enviado_en' => $fecha->subDays(3)->format('Y-m-d H:i:sP'),
            'respondido_en' => $fecha->format('Y-m-d H:i:sP'),
            'estado' => 'COMPLETADA',
        ]);

        $sectores = ['SOFTWARE_TI', 'BANCA_FINANZAS', 'SECTOR_PUBLICO', 'TELECOMUNICACIONES', 'CONSULTORIA_TI'];

        $respuesta = DB::table('seguimiento_egresado.respuesta_empresa')->insertGetId([
            'id_invitacion_empresa' => $invitacion,
            'fecha_respuesta' => $fecha->format('Y-m-d H:i:sP'),
            'id_nivel_satisfaccion' => $nivel ? $this->catalogo('cat_nivel_satisfaccion', $nivel) : null,
            'id_sector_empresa' => $this->catalogo('cat_sector_empresa', $sectores[$posicion % 5]),
            'cargo_informante' => 'Responsable ficticio',
            'organizacion_informada' => $organizacion,
        ]);

        $demandas = ['DESARROLLO_SOFTWARE', 'BASES_DATOS', 'CLOUD', 'CIBERSEGURIDAD', 'DATA_SCIENCE', 'TRABAJO_EQUIPO'];
        $brechas = ['INGLES', 'PRUEBAS_CALIDAD', 'ARQUITECTURA_SOFTWARE', 'COMUNICACION'];

        // La primera opción se repite en todas las respuestas para que el ranking tenga un claro primer lugar.
        foreach (array_unique([$demandas[0], $demandas[$posicion % 6], $demandas[($posicion + 2) % 6]]) as $codigo) {
            $this->habilidad($respuesta, $codigo, 'DEMANDA');
        }
        foreach (array_unique([$brechas[0], $brechas[$posicion % 4]]) as $codigo) {
            $this->habilidad($respuesta, $codigo, 'BRECHA');
        }
    }

    private function habilidad(int $respuesta, string $codigo, string $tipo): void
    {
        DB::table('seguimiento_egresado.respuesta_empresa_habilidad')->insert([
            'id_respuesta' => $respuesta,
            'id_habilidad' => $this->catalogo('cat_habilidad', $codigo),
            'tipo' => $tipo,
        ]);
    }

    private function invitacionSinResponder(int $campania, string $contexto, ?int $egresado, string $organizacion, CarbonImmutable $expira, string $estado): void
    {
        DB::table('seguimiento_egresado.invitacion_empresa')->insert([
            'id_campania' => $campania,
            'id_egresado' => $egresado,
            'contexto' => $contexto,
            'organizacion' => $organizacion,
            'token_hash' => hash('sha256', 'demo-'.bin2hex(random_bytes(16))),
            'expira_en' => $expira->format('Y-m-d H:i:sP'),
            'enviado_en' => $contexto === 'DESEMPENO' ? $this->hoy->subDays(2)->format('Y-m-d H:i:sP') : null,
            'estado' => $estado,
        ]);
    }

    private function catalogo(string $tabla, string $codigo): int
    {
        return (int) DB::table('seguimiento_egresado.'.$tabla)->where('codigo', $codigo)->value('id');
    }
}
