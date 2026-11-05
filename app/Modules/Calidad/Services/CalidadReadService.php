<?php

namespace App\Modules\Calidad\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CalidadReadService
{
    /** @return array<string, mixed> */
    public function home(): array
    {
        $periods = $this->periods();
        $period = $periods->first();
        $indicators = $this->indicatorRows($period?->id_periodo);
        $total = $indicators->count();
        $compliant = $indicators->where('status_key', 'success')->count();

        return [
            'databaseReady' => $this->databaseReady(),
            'period' => $period,
            'processTotal' => $this->count('calidad.proceso'),
            'indicatorTotal' => $total,
            'compliance' => $total > 0 ? round(($compliant / $total) * 100, 1) : 0,
            'compliant' => $compliant,
            'atRisk' => $indicators->where('status_key', 'warning')->count(),
            'nonCompliant' => $indicators->where('status_key', 'danger')->count(),
            'unmeasured' => $indicators->where('status_key', 'neutral')->count(),
            'priorityIndicators' => $indicators
                ->sortBy(fn (array $row): int => match ($row['status_key']) {
                    'danger' => 0,
                    'warning' => 1,
                    'neutral' => 2,
                    default => 3,
                })
                ->take(6)
                ->values(),
            'evidence' => $this->evidenceRows()->take(5)->values(),
            'improvements' => $this->improvementRows()->take(5)->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function processMap(): array
    {
        $processes = $this->processRows();
        $byParent = $processes->groupBy(fn (array $row): int => (int) ($row['parent_id'] ?? 0));
        $roots = $processes->whereNull('parent_id')->values();

        if ($roots->isEmpty() && $processes->isNotEmpty()) {
            $roots = $processes;
        }

        $stages = $roots->map(function (array $root) use ($byParent): array {
            $children = $byParent->get((int) $root['id'], collect())->values();

            return [
                ...$root,
                'processes' => $children->isEmpty() ? collect([$root]) : $children,
                'indicator_count' => ($children->isEmpty() ? collect([$root]) : $children)->sum('indicator_count'),
            ];
        });

        return [
            'databaseReady' => $this->databaseReady(),
            'stages' => $stages,
            'processTotal' => $processes->count(),
            'indicatorTotal' => $processes->sum('indicator_count'),
            'responsibles' => $processes->pluck('responsible')->filter()->unique()->sort()->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function indicators(?int $periodId = null): array
    {
        $periods = $this->periods();
        $selectedPeriod = $periodId === null
            ? $periods->first()
            : $periods->firstWhere('id_periodo', $periodId) ?? $periods->first();
        $indicators = $this->indicatorRows($selectedPeriod?->id_periodo);

        return [
            'databaseReady' => $this->databaseReady(),
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'indicators' => $indicators,
            'summary' => [
                'total' => $indicators->count(),
                'success' => $indicators->where('status_key', 'success')->count(),
                'warning' => $indicators->where('status_key', 'warning')->count(),
                'danger' => $indicators->where('status_key', 'danger')->count(),
                'neutral' => $indicators->where('status_key', 'neutral')->count(),
            ],
        ];
    }

    public const DOCUMENT_TYPES = ['silabo', 'institucional', 'calidad'];

    /** @return array<string, mixed> */
    public function documents(string $type = 'silabo', ?int $periodId = null, ?int $cycle = null): array
    {
        $type = in_array($type, self::DOCUMENT_TYPES, true) ? $type : 'silabo';
        $periods = $this->periods();
        $period = $periodId === null
            ? $periods->first()
            : $periods->firstWhere('id_periodo', $periodId) ?? $periods->first();

        $cycleCounts = $type === 'silabo' ? $this->syllabusCycleCounts($period?->id_periodo) : collect();
        $cycles = $cycleCounts->keys()->sort()->values();
        $activeCycle = $cycle !== null && $cycles->contains($cycle) ? $cycle : $cycles->first();

        $documents = match ($type) {
            'silabo' => $this->syllabusRows($period?->id_periodo, $activeCycle),
            'institucional' => $this->institutionalRows(),
            default => $this->evidenceRows()->map(fn (array $row): array => [
                'id' => $row['id'],
                'title' => $row['title'],
                'code' => $row['standard_code'],
                'meta' => [$row['dimension_name'], $row['period_code'], $row['file_name']],
                'status' => null,
                'url' => null,
                'date' => $row['created_at'],
                'description' => $row['description'],
            ]),
        };

        return [
            'databaseReady' => $this->databaseReady(),
            'activeType' => $type,
            'periods' => $periods,
            'activePeriod' => $period,
            'cycleCounts' => $cycleCounts,
            'activeCycle' => $activeCycle,
            'documents' => $documents,
        ];
    }

    /** @return Collection<int, int> ciclo => cantidad */
    private function syllabusCycleCounts(?int $periodId): Collection
    {
        if (! $this->databaseReady() || $periodId === null) {
            return collect();
        }

        try {
            return DB::table('cotecu.silabos as s')
                ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
                ->where('s.id_periodo', $periodId)
                ->whereNotNull('cp.ciclo')
                ->selectRaw('cp.ciclo AS ciclo, COUNT(*) AS total')
                ->groupBy('cp.ciclo')
                ->orderBy('cp.ciclo')
                ->pluck('total', 'ciclo')
                ->map(fn ($total): int => (int) $total)
                ->mapWithKeys(fn (int $total, $ciclo): array => [(int) $ciclo => $total]);
        } catch (Throwable) {
            return collect();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function syllabusRows(?int $periodId, ?int $cycle): Collection
    {
        if (! $this->databaseReady() || $periodId === null || $cycle === null) {
            return collect();
        }

        try {
            return DB::table('cotecu.silabos as s')
                ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
                ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
                ->join('core.periodo_academico as pa', 'pa.id_periodo', '=', 's.id_periodo')
                ->where('s.id_periodo', $periodId)
                ->where('cp.ciclo', $cycle)
                ->select(['s.id', 's.version', 's.archivo_url', 's.estado', 's.updated_at', 'c.codigo as course_code', 'c.nombre as course_name', 'pa.codigo as period_code'])
                ->orderBy('c.nombre')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'title' => $row->course_name,
                    'code' => $row->course_code,
                    'meta' => [$row->period_code.' · '.$cycle.'.º ciclo', 'Versión '.$row->version],
                    'status' => $row->estado,
                    'url' => $this->safeUrl($row->archivo_url),
                    'date' => $row->updated_at,
                    'description' => null,
                ]);
        } catch (Throwable) {
            return collect();
        }
    }

    /** Archivos del módulo Calidad que no están vinculados como evidencia de acreditación. @return Collection<int, array<string, mixed>> */
    private function institutionalRows(): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        try {
            return DB::table('comun.archivo as a')
                ->join('seguridad.modulo_sistema as m', 'm.id_modulo', '=', 'a.id_modulo')
                ->where('m.codigo', 'calidad')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('calidad.evidencia as e')->whereColumn('e.id_archivo', 'a.id_archivo'))
                ->select(['a.id_archivo', 'a.nombre_original', 'a.tipo_mime', 'a.tamano_bytes', 'a.subido_en'])
                ->orderByDesc('a.subido_en')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id_archivo,
                    'title' => $row->nombre_original,
                    'code' => null,
                    'meta' => [$row->tipo_mime, number_format(((int) $row->tamano_bytes) / 1024, 0).' KB'],
                    'status' => null,
                    'url' => null,
                    'date' => $row->subido_en,
                    'description' => null,
                ]);
        } catch (Throwable) {
            return collect();
        }
    }

    private function safeUrl(?string $url): ?string
    {
        return $url !== null && preg_match('~^https?://~i', $url) === 1 ? $url : null;
    }

    /** @return Collection<int, object> */
    private function periods(): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        try {
            return DB::table('core.periodo_academico')
                ->select('id_periodo', 'codigo', 'anio', 'tipo', 'fecha_inicio', 'fecha_fin', 'estado')
                ->orderByDesc('fecha_inicio')
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function processRows(): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        try {
            return DB::table('calidad.proceso as p')
                ->leftJoin('seguridad.usuario as u', 'u.id_usuario', '=', 'p.id_responsable')
                ->leftJoin('core.persona as pe', 'pe.id_persona', '=', 'u.id_persona')
                ->leftJoin('calidad.indicador as i', 'i.id_proceso', '=', 'p.id_proceso')
                ->select([
                    'p.id_proceso', 'p.id_proceso_padre', 'p.codigo', 'p.nombre', 'p.nivel',
                    'u.correo_institucional', 'pe.nombres', 'pe.apellido_paterno',
                ])
                ->selectRaw('COUNT(i.id_indicador) AS indicator_count')
                ->groupBy([
                    'p.id_proceso', 'p.id_proceso_padre', 'p.codigo', 'p.nombre', 'p.nivel',
                    'u.correo_institucional', 'pe.nombres', 'pe.apellido_paterno',
                ])
                ->orderBy('p.nivel')
                ->orderBy('p.codigo')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id_proceso,
                    'parent_id' => $row->id_proceso_padre === null ? null : (int) $row->id_proceso_padre,
                    'code' => $row->codigo,
                    'name' => $row->nombre,
                    'level' => (int) $row->nivel,
                    'responsible' => trim(($row->nombres ?? '').' '.($row->apellido_paterno ?? '')) ?: ($row->correo_institucional ?? 'Sin responsable asignado'),
                    'indicator_count' => (int) $row->indicator_count,
                ]);
        } catch (Throwable) {
            return collect();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function indicatorRows(?int $periodId): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        try {
            $query = DB::table('calidad.indicador as i')
                ->join('calidad.proceso as p', 'p.id_proceso', '=', 'i.id_proceso')
                ->leftJoin('seguridad.usuario as u', 'u.id_usuario', '=', 'i.id_responsable')
                ->leftJoin('core.persona as pe', 'pe.id_persona', '=', 'u.id_persona');

            if ($periodId !== null) {
                $query
                    ->leftJoin('calidad.meta_indicador as m', function ($join) use ($periodId): void {
                        $join->on('m.id_indicador', '=', 'i.id_indicador')->where('m.id_periodo', $periodId);
                    })
                    ->leftJoin('calidad.medicion_indicador as med', function ($join) use ($periodId): void {
                        $join->on('med.id_indicador', '=', 'i.id_indicador')->where('med.id_periodo', $periodId);
                    });
            }

            $query->select([
                'i.id_indicador', 'i.codigo', 'i.nombre', 'i.objetivo', 'i.formula',
                'i.unidad_medida', 'i.frecuencia', 'i.sentido', 'i.estado',
                'p.codigo as process_code', 'p.nombre as process_name',
                'u.correo_institucional', 'pe.nombres', 'pe.apellido_paterno',
            ]);

            if ($periodId !== null) {
                $query->addSelect('m.valor_meta', 'm.umbral_minimo', 'med.numerador', 'med.denominador', 'med.registrado_en');
            } else {
                $query->selectRaw('NULL AS valor_meta, NULL AS umbral_minimo, NULL AS numerador, NULL AS denominador, NULL AS registrado_en');
            }

            return $query
                ->orderBy('p.codigo')
                ->orderBy('i.codigo')
                ->get()
                ->map(function (object $row): array {
                    $value = $this->measurementValue($row);
                    $target = $row->valor_meta === null ? null : (float) $row->valor_meta;
                    $threshold = $row->umbral_minimo === null ? null : (float) $row->umbral_minimo;
                    [$status, $statusKey] = $this->status($value, $target, $threshold, $row->sentido);

                    return [
                        'id' => (int) $row->id_indicador,
                        'code' => $row->codigo,
                        'name' => $row->nombre,
                        'objective' => $row->objetivo,
                        'formula' => $row->formula,
                        'unit' => $row->unidad_medida,
                        'frequency' => $row->frecuencia,
                        'direction' => $row->sentido,
                        'record_state' => $row->estado,
                        'process_code' => $row->process_code,
                        'process_name' => $row->process_name,
                        'responsible' => trim(($row->nombres ?? '').' '.($row->apellido_paterno ?? '')) ?: ($row->correo_institucional ?? 'Sin responsable asignado'),
                        'value' => $value,
                        'target' => $target,
                        'threshold' => $threshold,
                        'progress' => $this->progress($value, $target, $row->sentido),
                        'status' => $status,
                        'status_key' => $statusKey,
                        'measured_at' => $row->registrado_en,
                    ];
                });
        } catch (Throwable) {
            return collect();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function evidenceRows(): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        try {
            return DB::table('calidad.evidencia as e')
                ->join('calidad.estandar as s', 's.id_estandar', '=', 'e.id_estandar')
                ->join('calidad.dimension_acreditacion as d', 'd.id_dimension', '=', 's.id_dimension')
                ->join('core.periodo_academico as pa', 'pa.id_periodo', '=', 'e.id_periodo')
                ->join('comun.archivo as a', 'a.id_archivo', '=', 'e.id_archivo')
                ->select([
                    'e.id_evidencia', 'e.titulo', 'e.descripcion', 'e.registrado_en',
                    's.codigo as standard_code', 's.nombre as standard_name',
                    'd.codigo as dimension_code', 'd.nombre as dimension_name',
                    'pa.codigo as period_code', 'a.nombre_original', 'a.ruta_almacenamiento',
                    'a.tipo_mime', 'a.tamano_bytes',
                ])
                ->orderByDesc('e.registrado_en')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id_evidencia,
                    'title' => $row->titulo,
                    'description' => $row->descripcion,
                    'standard_code' => $row->standard_code,
                    'standard_name' => $row->standard_name,
                    'dimension_code' => $row->dimension_code,
                    'dimension_name' => $row->dimension_name,
                    'period_code' => $row->period_code,
                    'file_name' => $row->nombre_original,
                    'path' => $row->ruta_almacenamiento,
                    'mime' => $row->tipo_mime,
                    'size' => (int) $row->tamano_bytes,
                    'created_at' => $row->registrado_en,
                ]);
        } catch (Throwable) {
            return collect();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function improvementRows(): Collection
    {
        if (! $this->databaseReady()) {
            return collect();
        }

        try {
            return DB::table('calidad.plan_mejora as pm')
                ->leftJoin('calidad.indicador as i', 'i.id_indicador', '=', 'pm.id_indicador')
                ->leftJoin('calidad.estandar as s', 's.id_estandar', '=', 'pm.id_estandar')
                ->join('core.periodo_academico as pa', 'pa.id_periodo', '=', 'pm.id_periodo')
                ->leftJoin('calidad.accion_mejora as ac', 'ac.id_plan_mejora', '=', 'pm.id_plan_mejora')
                ->select([
                    'pm.id_plan_mejora', 'pm.titulo', 'pm.descripcion', 'pm.fecha_inicio',
                    'pm.fecha_fin', 'pm.estado', 'pa.codigo as period_code',
                    'i.codigo as indicator_code', 's.codigo as standard_code',
                ])
                ->selectRaw('COUNT(ac.id_accion) AS action_count')
                ->selectRaw('COALESCE(AVG(ac.avance_porcentaje), 0) AS average_progress')
                ->groupBy([
                    'pm.id_plan_mejora', 'pm.titulo', 'pm.descripcion', 'pm.fecha_inicio',
                    'pm.fecha_fin', 'pm.estado', 'pa.codigo', 'i.codigo', 's.codigo',
                ])
                ->orderByRaw("CASE WHEN pm.estado IN ('En curso', 'En Progreso') THEN 0 ELSE 1 END")
                ->orderByDesc('pm.fecha_inicio')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id_plan_mejora,
                    'title' => $row->titulo,
                    'description' => $row->descripcion,
                    'status' => $row->estado,
                    'period_code' => $row->period_code,
                    'origin_code' => $row->indicator_code ?? $row->standard_code ?? 'Sin origen',
                    'action_count' => (int) $row->action_count,
                    'progress' => round((float) $row->average_progress, 1),
                    'starts_at' => $row->fecha_inicio,
                    'ends_at' => $row->fecha_fin,
                ]);
        } catch (Throwable) {
            return collect();
        }
    }

    private function measurementValue(object $row): ?float
    {
        if ($row->numerador === null) {
            return null;
        }

        $numerator = (float) $row->numerador;
        $denominator = (float) ($row->denominador ?? 0);

        if ($denominator <= 0) {
            return round($numerator, 2);
        }

        $ratio = $numerator / $denominator;
        $percentageUnit = str_contains(mb_strtolower((string) $row->unidad_medida), '%')
            || str_contains(mb_strtolower((string) $row->unidad_medida), 'porcentaje');

        return round($percentageUnit ? $ratio * 100 : $ratio, 2);
    }

    /** @return array{string, string} */
    private function status(?float $value, ?float $target, ?float $threshold, string $direction): array
    {
        if ($value === null || $target === null) {
            return ['Sin medición', 'neutral'];
        }

        $direction = mb_strtolower($direction);

        if ($direction === 'descendente') {
            return $value <= $target
                ? ['Cumple', 'success']
                : ($value <= ($threshold ?? $target * 1.1) ? ['En riesgo', 'warning'] : ['No cumple', 'danger']);
        }

        if ($direction === 'objetivo') {
            $tolerance = $threshold ?? abs($target * 0.1);
            $distance = abs($value - $target);

            return $distance == 0.0
                ? ['Cumple', 'success']
                : ($distance <= $tolerance ? ['En riesgo', 'warning'] : ['No cumple', 'danger']);
        }

        return $value >= $target
            ? ['Cumple', 'success']
            : ($value >= ($threshold ?? $target * 0.9) ? ['En riesgo', 'warning'] : ['No cumple', 'danger']);
    }

    private function progress(?float $value, ?float $target, string $direction): float
    {
        if ($value === null || $target === null || $target == 0.0) {
            return 0;
        }

        if (mb_strtolower($direction) === 'descendente') {
            return $value <= $target ? 100 : max(0, min(100, round(($target / $value) * 100, 1)));
        }

        return max(0, min(100, round(($value / $target) * 100, 1)));
    }

    private function count(string $table): int
    {
        if (! $this->databaseReady()) {
            return 0;
        }

        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function databaseReady(): bool
    {
        try {
            return DB::getDriverName() === 'pgsql'
                && DB::table('information_schema.tables')
                    ->where('table_schema', 'calidad')
                    ->where('table_name', 'proceso')
                    ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
