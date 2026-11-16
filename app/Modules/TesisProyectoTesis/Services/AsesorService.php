<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ReporteSimilitud;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Parametros;

/**
 * Bandeja del asesor: proyectos donde es asesor ACTIVO.
 */
final class AsesorService
{
    public function __construct(private readonly IdentidadService $identidad, private readonly CatalogoService $catalogo) {}

    /** @return array<string, mixed> */
    public function bandeja(int $idDocente, ?string $etapa = null): array
    {
        $ids = AsesorProyecto::query()->where('id_docente', $idDocente)->where('estado', 'Activo')->pluck('id_proyecto');
        $proyectos = Proyecto::query()->whereIn('id_proyecto', $ids)
            ->when($etapa, fn ($q) => $q->where('etapa', $etapa))
            ->with('integrantesActivos')
            ->orderByDesc('registrado_en')
            ->get(['id_proyecto', 'uuid', 'codigo', 'titulo', 'etapa', 'estado', 'id_linea']);

        $entregas = Entrega::query()->whereIn('id_proyecto', $proyectos->pluck('id_proyecto'))
            ->orderByDesc('entregado_en')->orderByDesc('version')
            ->get(['id_entrega', 'uuid', 'id_proyecto', 'etapa', 'version', 'tipo', 'numero_semana', 'estado', 'entregado_en'])
            ->groupBy('id_proyecto');

        $estudiantes = $this->identidad->estudiantes($proyectos->flatMap(fn ($p) => $p->integrantesActivos->pluck('id_estudiante'))->all());
        $lineas = $this->catalogo->nombresLineas($proyectos->pluck('id_linea')->all());
        $noAptos = ReporteSimilitud::query()->whereIn('id_entrega', Entrega::query()->select('id_entrega')->whereIn('id_proyecto', $proyectos->pluck('id_proyecto')))
            ->where('porcentaje', '>', Parametros::SIMILITUD_MAXIMA)->count();

        $filas = $proyectos->map(function ($p) use ($entregas, $estudiantes, $lineas) {
            $lista = $entregas->get($p->id_proyecto, collect())->where('etapa', $p->etapa);
            $pendiente = $lista->firstWhere('estado', 'Pendiente');
            $ultima = $lista->first();
            $paraVerificar = in_array($p->estado, Estados::DESARROLLO, true)
                && $ultima !== null && ($ultima->tipo === 'Final' || $ultima->estado === 'Culminada');

            return [
                'proyecto' => $p,
                'estudiantes' => $p->integrantesActivos->map(fn ($i) => $estudiantes[$i->id_estudiante]['nombre'] ?? '—')->all(),
                'linea' => $lineas[$p->id_linea] ?? '—',
                'ultima' => $ultima,
                'pendiente' => $pendiente,
                'porRevisar' => $lista->where('estado', 'Pendiente')->count(),
                'paraVerificar' => $paraVerificar,
            ];
        });

        return [
            'filas' => $filas,
            'cifras' => [
                'proyectos' => $filas->count(),
                'porRevisar' => $filas->sum('porRevisar'),
                'paraVerificar' => $filas->where('paraVerificar', true)->count(),
                'noAptos' => $noAptos,
            ],
        ];
    }
}
