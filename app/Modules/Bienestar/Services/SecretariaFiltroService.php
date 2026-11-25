<?php

namespace App\Modules\Bienestar\Services;

use App\Modules\Bienestar\Models\AlertaBitacora;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Shared\Services\ModuleService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SecretariaFiltroService extends ModuleService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /**
     * Aplica la decisión de la Secretaría sobre una alerta (Pasar a Bienestar o Descartar).
     * Incluye salvaguarda ineludible para alertas automáticas de riesgo alto.
     */
    public function procesarDecision(AlertaRiesgo $alerta, string $accion, ?string $motivo, int $idUsuario): void
    {
        DB::transaction(function () use ($alerta, $accion, $motivo, $idUsuario) {
            // Se bloquea la fila para que dos decisiones simultáneas no se pisen
            if (AlertaRiesgo::query()->lockForUpdate()->whereKey($alerta->id_alerta)->value('estado') !== 'en_revision') {
                throw ValidationException::withMessages([
                    'alerta' => 'La alerta ya ha sido procesada previamente y no se encuentra en estado de revisión.',
                ]);
            }

            if ($accion === 'descartar') {
                // Salvaguarda: las alertas automáticas de riesgo alto no se pueden descartar
                if ($alerta->noPuedeDescartarse()) {
                    throw ValidationException::withMessages([
                        'accion' => 'Salvaguarda institucional: Las alertas automáticas de riesgo alto (ej. tercera matrícula o prioridad alta) no se pueden descartar. La Secretaría únicamente puede pasarlas a Bienestar.',
                    ]);
                }

                if (empty(trim((string) $motivo))) {
                    throw ValidationException::withMessages([
                        'motivo' => 'Debe registrar obligatoriamente el motivo del descarte.',
                    ]);
                }

                $alerta->update([
                    'estado' => 'descartada',
                    'filtrado_por' => $idUsuario,
                    'fecha_filtro_secretaria' => now(),
                    'fecha_resolucion' => now(),
                ]);

                AlertaBitacora::create([
                    'id_alerta' => $alerta->id_alerta,
                    'id_usuario' => $idUsuario,
                    'accion' => 'descartado',
                    'motivo' => $motivo,
                    'creado_en' => now(),
                ]);
            } elseif ($accion === 'pasar_bienestar') {
                $alerta->update([
                    'estado' => 'remitida_bienestar',
                    'filtrado_por' => $idUsuario,
                    'fecha_filtro_secretaria' => now(),
                ]);

                AlertaBitacora::create([
                    'id_alerta' => $alerta->id_alerta,
                    'id_usuario' => $idUsuario,
                    'accion' => 'pasado_a_bienestar',
                    'motivo' => $motivo ?: 'Remitido formalmente a Bienestar para atención especializada.',
                    'creado_en' => now(),
                ]);

                $this->notificaciones->alertaRemitida($alerta->id_alerta);
            } else {
                throw ValidationException::withMessages([
                    'accion' => 'Acción de filtrado no válida.',
                ]);
            }
        });
    }

    /**
     * Permite al personal de Bienestar reabrir una alerta previamente descartada.
     */
    public function reabrirAlerta(AlertaRiesgo $alerta, string $motivo, int $idUsuario): void
    {
        if ($alerta->estado !== 'descartada') {
            throw ValidationException::withMessages([
                'alerta' => 'Solo se pueden reabrir alertas en estado descartada.',
            ]);
        }

        if (empty(trim($motivo))) {
            throw ValidationException::withMessages([
                'motivo' => 'Debe ingresar una justificación para reabrir la alerta descartada.',
            ]);
        }

        DB::transaction(function () use ($alerta, $motivo, $idUsuario) {
            $alerta->update([
                'estado' => 'remitida_bienestar',
                'fecha_resolucion' => null,
            ]);

            AlertaBitacora::create([
                'id_alerta' => $alerta->id_alerta,
                'id_usuario' => $idUsuario,
                'accion' => 'reabierto',
                'motivo' => $motivo,
                'creado_en' => now(),
            ]);
        });
    }

    /**
     * Consulta de alertas para la Secretaría: Ley 29733.
     * Solo expone datos administrativos mínimos: código, nombres, ciclo y datos esenciales de la alerta.
     * Nunca expone texto libre confidencial ni descripciones sensibles de reportes.
     */
    public function obtenerBandejaSecretaria(array $filtros, int $perPage = 15): LengthAwarePaginator
    {
        $query = AlertaRiesgo::query()
            ->with([
                'estudiante:id_estudiante,codigo_universitario,id_persona',
                'estudiante.persona:id_persona,nombres,apellido_paterno,apellido_materno',
            ])
            ->select([
                'id_alerta',
                'id_estudiante',
                'periodo',
                'curso',
                'regla',
                'origen',
                'prioridad',
                'estado',
                'categoria_motivo', // Categoría, nunca el texto libre del reporte
                'fecha_deteccion',
                'created_at',
            ])
            ->orderByRaw("CASE WHEN prioridad = 'alta' OR prioridad = 'urgente' THEN 1 WHEN prioridad = 'media' THEN 2 ELSE 3 END")
            ->orderBy('fecha_deteccion', 'desc');

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        } else {
            // Por defecto muestra las que están pendientes de revisión por Secretaría
            $query->where('estado', 'en_revision');
        }

        if (! empty($filtros['prioridad'])) {
            $query->where('prioridad', $filtros['prioridad']);
        }

        if (! empty($filtros['origen'])) {
            $query->where('origen', $filtros['origen']);
        }

        if (! empty($filtros['buscar'])) {
            $buscar = trim((string) $filtros['buscar']);
            $query->where(function ($q) use ($buscar) {
                $q->whereHas('estudiante', function ($eq) use ($buscar) {
                    $eq->where('codigo_universitario', 'like', "%{$buscar}%")
                        ->orWhereHas('persona', function ($pq) use ($buscar) {
                            $pq->where('nombres', 'ilike', "%{$buscar}%")
                                ->orWhere('apellido_paterno', 'ilike', "%{$buscar}%")
                                ->orWhere('apellido_materno', 'ilike', "%{$buscar}%");
                        });
                });
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Indicadores operativos exclusivos para Secretaría.
     */
    public function obtenerIndicadoresOperativos(): array
    {
        return [
            'alertas_en_revision' => AlertaRiesgo::where('estado', 'en_revision')->count(),
            'alertas_pasadas_bienestar' => AlertaRiesgo::whereIn('estado', ['remitida_bienestar', 'en_atencion', 'atendida'])->count(),
            'alertas_descartadas' => AlertaRiesgo::where('estado', 'descartada')->count(),
            'solicitudes_enviadas' => SolicitudApoyo::where('estado', 'enviada')->count(),
            'solicitudes_recibidas' => SolicitudApoyo::where('estado', 'recibida')->count(),
            'solicitudes_observadas' => SolicitudApoyo::where('estado', 'observada')->count(),
            'solicitudes_remitidas' => SolicitudApoyo::where('estado', 'remitida')->count(),
            'solicitudes_aprobadas' => SolicitudApoyo::where('estado', 'aprobada')->count(),
            'solicitudes_rechazadas' => SolicitudApoyo::where('estado', 'rechazada')->count(),
        ];
    }
}
