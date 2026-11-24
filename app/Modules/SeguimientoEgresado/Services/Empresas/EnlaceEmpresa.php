<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Emite y comprueba los enlaces de la encuesta empresarial (invitacion_empresa).
 * Solo se guarda el hash del token: el enlace completo se muestra una vez, al generarlo.
 * La empresa no tiene cuenta; el token, su vencimiento y su uso único controlan el acceso.
 */
final class EnlaceEmpresa
{
    public const DIAS_POR_DEFECTO = 30;

    public const DIAS_MAXIMOS = 90;

    /** Misma regla que las invitaciones de egresados: token_hash = SHA-256 en hexadecimal. */
    public static function hashDe(string $tokenPlano): string
    {
        return hash('sha256', $tokenPlano);
    }

    /**
     * @param  array{id_campania:int, contexto:string, id_egresado?:int|null, organizacion?:string|null, contacto_empresa?:string|null, dias_validez?:int|null}  $datos
     * @return array{id:int, token:string, expira_en:CarbonImmutable}
     *
     * @throws EnlaceNoGenerable
     */
    public function generar(array $datos): array
    {
        $ahora = CarbonImmutable::now();
        $campania = DB::table('seguimiento_egresado.campania_seguimiento')->where('id', $datos['id_campania'])->first();

        if ($campania === null) {
            throw new EnlaceNoGenerable('La campaña seleccionada no existe.');
        }
        if (! in_array($campania->tipo, ['EMPRESAS', 'MIXTA'], true)) {
            throw new EnlaceNoGenerable('La campaña seleccionada no está dirigida a empresas.');
        }
        if ($campania->estado !== 'ABIERTA' || self::finDeCampania($campania)->lessThanOrEqualTo($ahora)) {
            throw new EnlaceNoGenerable('La campaña debe estar abierta y dentro de sus fechas.');
        }

        $egresadoId = null;
        if ($datos['contexto'] === 'DESEMPENO') {
            $egresadoId = $this->egresadoEvaluable($datos['id_egresado'] ?? null, (int) $campania->id);
        }

        // Sin días indicados, el enlace vale hasta el cierre de la campaña (igual que las invitaciones a egresados).
        $expira = self::finDeCampania($campania);
        if (($datos['dias_validez'] ?? null) !== null && $ahora->addDays((int) $datos['dias_validez'])->lessThan($expira)) {
            $expira = $ahora->addDays((int) $datos['dias_validez']);
        }

        // Empresa y contacto del jefe ya los dejó el egresado en su respuesta: no se vuelven a escribir.
        $empleador = $egresadoId !== null ? $this->datosDelEmpleador($egresadoId) : ['organizacion' => null, 'contacto' => null];

        $token = bin2hex(random_bytes(32));

        $id = DB::table('seguimiento_egresado.invitacion_empresa')->insertGetId([
            'id_campania' => $campania->id,
            'id_egresado' => $egresadoId,
            'contexto' => $datos['contexto'],
            'organizacion' => self::textoOpcional($datos['organizacion'] ?? null) ?? $empleador['organizacion'],
            'contacto_empresa' => self::textoOpcional($datos['contacto_empresa'] ?? null) ?? $empleador['contacto'],
            'token_hash' => self::hashDe($token),
            'expira_en' => $expira->format('Y-m-d H:i:sP'),
            'enviado_en' => null,
            'estado' => 'PENDIENTE',
        ]);

        return ['id' => (int) $id, 'token' => $token, 'expira_en' => $expira];
    }

    /**
     * Reemplaza el token de una invitación sin responder. El enlace anterior deja de funcionar.
     * El token original no se puede recuperar porque solo se guarda su hash.
     *
     * Un enlace vigente que ya se copió puede estar en manos de la empresa: reemplazarlo exige confirmación.
     *
     * @return array{id:int, token:string, expira_en:CarbonImmutable}
     *
     * @throws EnlaceNoGenerable
     */
    public function renovar(int $invitacionId, bool $confirmaReemplazo = false): array
    {
        $invitacion = DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $invitacionId)->first();

        if ($invitacion === null || ! in_array($invitacion->estado, ['PENDIENTE', 'EXPIRADA'], true)) {
            throw new EnlaceNoGenerable('Esta invitación ya no admite un enlace nuevo.');
        }

        $campania = DB::table('seguimiento_egresado.campania_seguimiento')->where('id', $invitacion->id_campania)->first();
        $ahora = CarbonImmutable::now();
        $sigueVigente = $invitacion->estado === 'PENDIENTE' && CarbonImmutable::parse($invitacion->expira_en)->greaterThan($ahora);

        if ($invitacion->enviado_en !== null && $sigueVigente && ! $confirmaReemplazo) {
            throw new EnlaceNoGenerable('Otra persona ya copió este enlace. Revisa la invitación antes de reemplazarlo.');
        }

        if ($campania->estado !== 'ABIERTA' || self::finDeCampania($campania)->lessThanOrEqualTo($ahora)) {
            throw new EnlaceNoGenerable('La campaña debe estar abierta y dentro de sus fechas.');
        }

        $expira = self::finDeCampania($campania);

        $token = bin2hex(random_bytes(32));

        DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $invitacionId)->update([
            'token_hash' => self::hashDe($token),
            'expira_en' => $expira->format('Y-m-d H:i:sP'),
            'enviado_en' => null,
            'estado' => 'PENDIENTE',
        ]);

        return ['id' => $invitacionId, 'token' => $token, 'expira_en' => $expira];
    }

    /** Deja constancia de que el enlace se copió para enviarlo. */
    public function marcarEnviado(int $invitacionId): void
    {
        DB::table('seguimiento_egresado.invitacion_empresa')
            ->where('id', $invitacionId)
            ->where('estado', 'PENDIENTE')
            ->whereNull('enviado_en')
            ->update(['enviado_en' => CarbonImmutable::now()->format('Y-m-d H:i:sP')]);
    }

    /**
     * Comprueba un token sin revelar datos de otras invitaciones. Si venció, deja constancia en la invitación.
     *
     * @return array{motivo:MotivoEnlace, invitacion:?object}
     */
    public function comprobar(string $tokenPlano): array
    {
        $invitacion = $tokenPlano === '' ? null : $this->buscar(self::hashDe($tokenPlano));
        $motivo = $this->evaluar($invitacion, CarbonImmutable::now());

        if ($motivo === MotivoEnlace::Vencido && $invitacion->estado === 'PENDIENTE') {
            DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $invitacion->id)->update(['estado' => 'EXPIRADA']);
        }

        return ['motivo' => $motivo, 'invitacion' => $motivo === MotivoEnlace::Vigente ? $invitacion : null];
    }

    /** Regla única de disponibilidad, usada al abrir el formulario y otra vez al guardar. */
    public function evaluar(?object $invitacion, CarbonImmutable $ahora): MotivoEnlace
    {
        if ($invitacion === null || $invitacion->estado === 'ANULADA') {
            return MotivoEnlace::NoValido;
        }
        if ($invitacion->estado === 'COMPLETADA' || $invitacion->respondido_en !== null) {
            return MotivoEnlace::Usado;
        }
        if ($invitacion->estado === 'EXPIRADA' || CarbonImmutable::parse($invitacion->expira_en)->lessThanOrEqualTo($ahora)) {
            return MotivoEnlace::Vencido;
        }
        if ($invitacion->estado_campania !== 'ABIERTA') {
            return MotivoEnlace::CampaniaCerrada;
        }

        return MotivoEnlace::Vigente;
    }

    /** Solo los datos que la empresa necesita ver: nunca documento, matrícula ni contacto del egresado. */
    public function buscar(string $hash, bool $bloquear = false): ?object
    {
        // PostgreSQL no permite FOR UPDATE sobre un LEFT JOIN: se bloquea solo la fila de la invitación.
        if ($bloquear) {
            DB::table('seguimiento_egresado.invitacion_empresa')->where('token_hash', $hash)->lockForUpdate()->first();
        }

        return DB::table('seguimiento_egresado.invitacion_empresa as ie')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'ie.id_campania')
            ->leftJoin('seguimiento_egresado.egresado as e', 'e.id', '=', 'ie.id_egresado')
            ->where('ie.token_hash', $hash)
            ->select(
                'ie.id', 'ie.id_campania', 'ie.id_egresado', 'ie.contexto', 'ie.organizacion', 'ie.estado', 'ie.expira_en', 'ie.respondido_en',
                'c.nombre as campania', 'c.estado as estado_campania',
                'e.nombres as egresado_nombres', 'e.apellido_paterno as egresado_paterno', 'e.apellido_materno as egresado_materno', 'e.programa as egresado_programa',
            )
            ->first();
    }

    /**
     * Egresados que pueden evaluarse: su última respuesta de seguimiento autoriza contactar a la empresa.
     *
     * @throws EnlaceNoGenerable
     */
    private function egresadoEvaluable(?int $egresadoId, int $campaniaId): int
    {
        if ($egresadoId === null) {
            throw new EnlaceNoGenerable('La evaluación de desempeño debe indicar un egresado.', 'id_egresado');
        }

        $autoriza = DB::table('seguimiento_egresado.medicion_laboral as m')
            ->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')
            ->where('i.id_egresado', $egresadoId)
            ->orderByDesc('m.fecha_respuesta')
            ->orderByDesc('m.id')
            ->value('m.autoriza_contacto_empresa');

        if (! $autoriza) {
            throw new EnlaceNoGenerable('El egresado no ha autorizado el contacto con su empresa en su última respuesta.', 'id_egresado');
        }

        $repetida = DB::table('seguimiento_egresado.invitacion_empresa')
            ->where('id_campania', $campaniaId)
            ->where('id_egresado', $egresadoId)
            ->whereIn('estado', ['PENDIENTE', 'COMPLETADA'])
            ->exists();

        if ($repetida) {
            throw new EnlaceNoGenerable('Este egresado ya tiene una evaluación de desempeño en la campaña.', 'id_egresado');
        }

        return $egresadoId;
    }

    /**
     * Empresa y contacto del jefe que el egresado dejó en su última respuesta de seguimiento.
     *
     * @return array{organizacion: ?string, contacto: ?string}
     */
    public function datosDelEmpleador(int $egresadoId): array
    {
        $fila = DB::table('seguimiento_egresado.medicion_laboral as m')
            ->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')
            ->where('i.id_egresado', $egresadoId)
            ->orderByDesc('m.fecha_respuesta')->orderByDesc('m.id')
            ->first(['m.nombre_empleador_cliente', 'm.contacto_jefe']);

        return ['organizacion' => self::textoOpcional($fila->nombre_empleador_cliente ?? null), 'contacto' => self::textoOpcional($fila->contacto_jefe ?? null)];
    }

    private static function finDeCampania(object $campania): CarbonImmutable
    {
        return CarbonImmutable::parse($campania->fecha_fin, FiltroIndicadores::ZONA)->endOfDay();
    }

    private static function textoOpcional(?string $valor): ?string
    {
        $valor = $valor === null ? null : trim($valor);

        return $valor === '' ? null : $valor;
    }
}
