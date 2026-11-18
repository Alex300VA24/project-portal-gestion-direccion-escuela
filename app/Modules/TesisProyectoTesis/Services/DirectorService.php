<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\HistorialEstado;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Collection;

/**
 * Bandeja e indicadores del Director de Escuela.
 */
final class DirectorService
{
    public function __construct(private readonly IdentidadService $identidad, private readonly CatalogoService $catalogo) {}

    /** @return array<string, mixed> */
    public function panel(): array
    {
        $proyectos = Proyecto::query()->get(['id_proyecto', 'uuid', 'codigo', 'titulo', 'etapa', 'estado', 'registrado_en']);
        $activos = $proyectos->filter(fn ($p) => $p->esActivo());

        $conSolicitud = Solicitud::query()->where('estado', 'Pendiente')->pluck('id_proyecto')->all();
        $accion = [];

        foreach ($activos->sortBy([['registrado_en', 'asc'], ['id_proyecto', 'asc']]) as $p) {
            if ($p->estado === Estados::REGISTRADO && ! in_array($p->id_proyecto, $conSolicitud, true)) {
                $accion[] = ['tipo' => 'Solicitar asesor', 'variante' => 'warning', 'proyecto' => $p, 'boton' => 'Solicitar',
                    'url' => route('tesis-proyecto-tesis.director.asesor.solicitar', $p), 'detalle' => 'registrado '.$p->registrado_en->diffForHumans()];
            }
            if ($p->estado === Estados::VERIFICADO_ASESOR && app('router')->has('tesis-proyecto-tesis.director.jurado')) {
                $accion[] = ['tipo' => 'Asignar jurado', 'variante' => 'info', 'proyecto' => $p, 'boton' => 'Asignar',
                    'url' => route('tesis-proyecto-tesis.director.jurado', $p), 'detalle' => 'conformidad del asesor y Turnitin'];
            }
        }

        $porEstado = [];
        foreach (Estados::ETIQUETAS as $estado => $etiqueta) {
            $n = $proyectos->where('estado', $estado)->count();
            if ($n > 0) {
                $porEstado[] = ['etiqueta' => $etiqueta, 'n' => $n, 'variante' => Estados::variante($estado)];
            }
        }

        $periodo = $this->catalogo->periodoActual();

        return [
            'cifras' => [
                'activas' => $activos->count(),
                'proyecto' => $activos->where('etapa', Estados::ETAPA_PROYECTO)->count(),
                'tesis' => $activos->where('etapa', Estados::ETAPA_TESIS)->count(),
                'accion' => count($accion),
            ],
            'accion' => $accion,
            'porEstado' => $porEstado,
            'maximo' => max([1, ...array_column($porEstado, 'n')]),
            'periodo' => $periodo?->codigo,
            'semana' => $this->catalogo->semanaDe($periodo),
            'diasAsesor' => $this->promedioDias(Estados::REGISTRADO, Estados::CON_ASESOR),
            'diasDictamen' => $this->promedioDias(Estados::VERIFICADO_ASESOR, Estados::APROBADO),
            'aprobadas' => $proyectos->whereIn('estado', [Estados::SUSTENTADO, Estados::TESIS_APROBADA])->count(),
            'actas' => Acta::query()->orderByDesc('emitida_en')->limit(5)->with('proyecto:id_proyecto,codigo,uuid')->get(['id_acta', 'uuid', 'id_proyecto', 'tipo', 'numero', 'resultado', 'emitida_en', 'id_archivo_firmado']),
        ];
    }

    /**
     * Días promedio entre dos estados según el historial (indicador de tiempos por etapa).
     */
    public function promedioDias(string $desde, string $hasta): ?float
    {
        $inicio = HistorialEstado::query()->where('estado_nuevo', $desde)->selectRaw('id_proyecto, MIN(cambiado_en) AS f')->groupBy('id_proyecto')->pluck('f', 'id_proyecto');
        $fin = HistorialEstado::query()->where('estado_nuevo', $hasta)->selectRaw('id_proyecto, MIN(cambiado_en) AS f')->groupBy('id_proyecto')->pluck('f', 'id_proyecto');

        $dias = [];
        foreach ($fin as $id => $f) {
            if (isset($inicio[$id])) {
                $dias[] = max(0, (strtotime((string) $f) - strtotime((string) $inicio[$id])) / 86400);
            }
        }

        return $dias === [] ? null : round(array_sum($dias) / count($dias), 1);
    }

    /**
     * Listado general con asesor y línea.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function proyectos(?string $estado = null, ?string $buscar = null): Collection
    {
        $lista = Proyecto::query()
            ->with(['asesorActivo:id_asesor,id_proyecto,id_docente', 'integrantesActivos'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($buscar, fn ($q) => $q->where(fn ($w) => $w->where('codigo', 'ilike', '%'.$buscar.'%')->orWhere('titulo', 'ilike', '%'.$buscar.'%')))
            ->orderByDesc('registrado_en')->orderByDesc('id_proyecto')
            ->get(['id_proyecto', 'uuid', 'codigo', 'titulo', 'etapa', 'estado', 'id_linea', 'registrado_en']);

        $docentes = $this->identidad->docentes($lista->pluck('asesorActivo.id_docente')->filter()->values()->all());
        $estudiantes = $this->identidad->estudiantes($lista->flatMap(fn ($p) => $p->integrantesActivos->pluck('id_estudiante'))->all());
        $lineas = $this->catalogo->nombresLineas($lista->pluck('id_linea')->all());

        return $lista->map(fn ($p) => [
            'proyecto' => $p,
            'asesor' => $p->asesorActivo ? ($docentes[$p->asesorActivo->id_docente]['nombre'] ?? '—') : null,
            'estudiantes' => $p->integrantesActivos->map(fn ($i) => $estudiantes[$i->id_estudiante]['nombre'] ?? '—')->all(),
            'linea' => $lineas[$p->id_linea] ?? '—',
        ]);
    }
}
