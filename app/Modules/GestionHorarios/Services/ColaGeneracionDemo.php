<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Cola local QA; no constituye autorización institucional de generación. */
final class ColaGeneracionDemo
{
    public const TABLA = 'gestion_horarios.qa_generacion';
    public const ACTIVOS = ['PENDIENTE', 'EJECUTANDO'];
    public const FASES = ['PREPARANDO', 'FACTIBILIDAD', 'OPTIMIZANDO', 'AULAS', 'HORAS_SUELTAS', 'VALIDANDO'];

    public function disponible(User $usuario): bool
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        return DB::connection()->getSchemaBuilder()->hasTable(self::TABLA) && HorarioUnicoDemo::id() !== null;
    }

    public function ultima(User $usuario): ?array
    {
        if (! $this->disponible($usuario)) { return null; }
        $r = DB::table(self::TABLA)->orderByDesc('creada_en')->orderByDesc('id')->first();
        if (! $r) { return null; }
        $fila = (array) $r;
        $fila['resultado'] = $r->resultado ? json_decode($r->resultado, true, 512, JSON_THROW_ON_ERROR) : [];
        return $fila;
    }

    public function solicitar(User $usuario, string $modo, int $segundos): void
    {
        abort_unless($this->disponible($usuario), 422, 'Prepare primero la cola QA.');
        if (! in_array($modo, ['prevalidar', 'generar', 'reorganizar'], true) || $segundos < 1 || $segundos > 300) {
            throw ValidationException::withMessages(['segundosCpsat' => 'Tiempo de búsqueda: 1 a 300 segundos.']);
        }
        DB::transaction(function () use ($modo, $segundos) {
            DB::statement('LOCK TABLE '.self::TABLA.' IN SHARE ROW EXCLUSIVE MODE');
            if (DB::table(self::TABLA)->whereIn('estado', self::ACTIVOS)->exists()) {
                throw ValidationException::withMessages(['segundosCpsat' => 'Ya existe un cálculo pendiente o en ejecución.']);
            }
            DB::table(self::TABLA)->insert(['id' => (string) Str::uuid(), 'modo' => $modo, 'segundos' => $segundos,
                'estado' => 'PENDIENTE', 'fase' => 'EN_COLA']);
        });
    }

    /** Cancela exclusivamente solicitudes que aún no fueron tomadas por el operador. */
    public function cancelarPendiente(User $usuario, string $id): bool
    {
        abort_unless($this->disponible($usuario), 403);
        abort_unless(Str::isUuid($id), 422);
        return DB::table(self::TABLA)->where('id', $id)->where('estado', 'PENDIENTE')
            ->whereNull('iniciada_en')->whereNull('propuesta')->update([
                'estado'=>'ERROR', 'fase'=>'ERROR',
                'resultado'=>json_encode(['estado'=>'CANCELADA'], JSON_THROW_ON_ERROR),
                'actualizada_en'=>DB::raw('CURRENT_TIMESTAMP'), 'finalizada_en'=>DB::raw('CURRENT_TIMESTAMP'),
            ]) === 1;
    }

    /** Solo eventos conocidos; no interpretar mensajes arbitrarios como estados. */
    public static function fase(string $linea): ?string
    {
        $linea = trim($linea);
        if (! str_starts_with($linea, 'CP-SAT: ')) { return null; }
        $fase = substr($linea, 8);
        return in_array($fase, self::FASES, true) ? $fase : null;
    }

    public static function interpretar(string $salida, string $modo): array
    {
        if (! in_array($modo, ['prevalidar', 'generar', 'reorganizar'], true)) { throw new \RuntimeException('Modo no reconocido.'); }
        $resultado = null; $propuesta = null;
        foreach (preg_split('/\R/', $salida) as $linea) {
            $linea = trim($linea);
            if (str_starts_with($linea, '{')) {
                $json = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($json) && isset($json['estado'])) {
                    if ($resultado !== null) { throw new \RuntimeException('Resumen duplicado.'); }
                    $resultado = $json;
                }
            }
            if (preg_match('/^(?:Propuesta QA guardada \(solo consulta\)|Horario QA sin cambios): ([0-9a-f-]{36})$/D', $linea, $m)) {
                if ($propuesta !== null || ! Str::isUuid($m[1])) { throw new \RuntimeException('Identificador de propuesta inválido.'); }
                $propuesta = $m[1];
            }
        }
        $estado = $resultado['estado'] ?? '';
        $validos = ['LISTO', 'REQUIERE_ATENCION', 'NO_SE_PUEDE_GENERAR', 'INFEASIBLE', 'MODEL_INVALID', 'INPUT_INVALID', 'UNKNOWN', 'OPTIMAL', 'FEASIBLE'];
        if (! in_array($estado, $validos, true)) { throw new \RuntimeException('El motor no devolvió un estado reconocido.'); }
        $solucion = in_array($estado, ['OPTIMAL', 'FEASIBLE'], true);
        if ((in_array($modo,['generar','reorganizar'],true) && $solucion) !== ($propuesta !== null) || ($modo === 'prevalidar' && $solucion)) {
            throw new \RuntimeException('Resultado y persistencia no coinciden.');
        }
        return [$resultado, $propuesta];
    }
}
