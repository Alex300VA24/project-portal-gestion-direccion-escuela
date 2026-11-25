<?php

namespace App\Modules\GestionHorarios\Services;

use DateTimeImmutable;
use DateTimeZone;

/** Presentación de fechas y estados; no estima avance del optimizador. */
final class EstadoGeneracionHorario
{
    public const FASES = [
        'EN_COLA'=>'Esperando inicio', 'PREPARANDO'=>'Preparando datos',
        'FACTIBILIDAD'=>'Buscando un horario compatible', 'OPTIMIZANDO'=>'Ajustando preferencias',
        'AULAS'=>'Asignando aulas y laboratorios', 'HORAS_SUELTAS'=>'Ajustando distribución de clases',
        'VALIDANDO'=>'Verificando el horario', 'FINALIZADA'=>'Revisión terminada', 'ERROR'=>'Proceso terminado',
    ];

    public static function presentar(array $fila, ?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable('now');
        $utc = new DateTimeZone('UTC');
        $lima = new DateTimeZone('America/Lima');
        $fecha = static fn ($v) => $v ? new DateTimeImmutable((string) $v, $utc) : null;
        $creada = $fecha($fila['creada_en'] ?? null);
        $inicio = $fecha($fila['iniciada_en'] ?? null);
        $fin = $fecha($fila['finalizada_en'] ?? null);
        $latido = $fecha($fila['actualizada_en'] ?? null);
        $estado = $fila['estado'] ?? '';
        $activa = in_array($estado, ['PENDIENTE','EJECUTANDO'], true);
        $resultado = $fila['resultado']['estado'] ?? '';
        $cancelada = $resultado === 'CANCELADA';
        $interrumpida = $resultado === 'INTERRUMPIDA';
        $hasta = $fin ?? ($activa ? $ahora : ($latido ?? $inicio ?? $creada ?? $ahora));
        $desde = $inicio ?? $creada ?? $hasta;
        $seg = max(0, $hasta->getTimestamp() - $desde->getTimestamp());
        $local = static fn ($d) => $d?->setTimezone($lima)->format('d/m/Y H:i:s');
        $fases = [];
        foreach (($fila['resultado']['fases_observadas'] ?? []) as $evento) {
            $fase = $evento['fase'] ?? '';
            if (isset(self::FASES[$fase]) && !empty($evento['en'])) {
                $fases[] = ['fase'=>self::FASES[$fase], 'hora'=>$local($fecha($evento['en']))];
            }
        }
        return [
            'historial'=>$fases,
            'estado'=> $cancelada ? 'Cancelada' : ($interrumpida ? 'Interrumpida' : match ($estado) {
                'PENDIENTE'=>'En espera', 'EJECUTANDO'=>'En ejecución', 'FINALIZADA'=>'Finalizada', 'ERROR'=>'No completada', default=>'Estado desconocido',
            }),
            'fase'=>self::FASES[$fila['fase'] ?? ''] ?? 'Estado de solicitud',
            'activa'=>$activa, 'cancelada'=>$cancelada, 'interrumpida'=>$interrumpida,
            'transcurrido'=>sprintf('%02d:%02d:%02d', intdiv($seg,3600), intdiv($seg%3600,60), $seg%60),
            'creada'=>$local($creada), 'inicio'=>$local($inicio), 'fin'=>$local($fin), 'latido'=>$local($latido),
            'sin_latido'=>$estado === 'EJECUTANDO' && (!$latido || $ahora->getTimestamp()-$latido->getTimestamp()>20),
        ];
    }
}
