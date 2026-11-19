<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\ActividadCronograma;
use App\Modules\TesisProyectoTesis\Models\Colaborador;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\PartidaPresupuesto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Recurso;
use App\Modules\TesisProyectoTesis\Models\Resolucion;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Parametros;

/**
 * Datos de lectura del expediente para las pantallas (ficha, pasos, historial).
 */
final class SeguimientoService
{
    public const PASOS = [
        ['Registro', 'Proyecto F1'],
        ['Asesor designado', 'Por resolución'],
        ['Avances semanales', 'Con el asesor'],
        ['Verificación del asesor', 'Rúbrica y Turnitin'],
        ['Revisión del jurado', 'Con la rúbrica'],
        ['Dictamen', 'Pasa a Tesis'],
        ['Tesis (F2)', '10.º ciclo'],
        ['Sustentación', 'Acta'],
    ];

    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly CatalogoService $catalogo,
    ) {}

    /**
     * Ficha resumida del proyecto: integrantes, asesor, jurado y línea.
     *
     * @return array<string, mixed>
     */
    public function ficha(Proyecto $proyecto): array
    {
        $proyecto->loadMissing(['integrantesActivos', 'asesorActivo', 'juradosActivos']);

        $estudiantes = $this->identidad->estudiantes($proyecto->integrantesActivos->pluck('id_estudiante')->all());
        $idsDocentes = array_filter([$proyecto->asesorActivo?->id_docente, ...$proyecto->juradosActivos->pluck('id_docente')->all()]);
        $docentes = $this->identidad->docentes(array_values($idsDocentes));
        $resolucion = $proyecto->asesorActivo?->id_resolucion
            ? Resolucion::query()->select(['numero', 'fecha'])->find($proyecto->asesorActivo->id_resolucion)
            : null;

        $orden = array_flip(['Presidente', 'Secretario', 'Vocal']);

        return [
            'proyecto' => $proyecto,
            'integrantes' => $proyecto->integrantesActivos->map(fn ($i) => [
                'orden' => $i->orden_autor,
                'nombre' => $estudiantes[$i->id_estudiante]['nombre'] ?? '—',
                'codigo' => $estudiantes[$i->id_estudiante]['codigo'] ?? '',
            ])->all(),
            'asesor' => $proyecto->asesorActivo ? [
                'nombre' => $docentes[$proyecto->asesorActivo->id_docente]['nombre'] ?? '—',
                'resolucion' => $resolucion?->numero,
            ] : null,
            'jurado' => $proyecto->juradosActivos
                ->sortBy(fn ($j) => $orden[$j->cargo] ?? 9)
                ->map(fn ($j) => ['cargo' => $j->cargo, 'nombre' => $docentes[$j->id_docente]['nombre'] ?? '—', 'id_docente' => $j->id_docente])
                ->values()->all(),
            'linea' => $this->catalogo->nombresLineas([$proyecto->id_linea])[$proyecto->id_linea] ?? '—',
        ];
    }

    /** @return list<array{titulo: string, detalle: string, clase: string}> */
    public function pasos(Proyecto $proyecto): array
    {
        $actual = match ($proyecto->estado) {
            Estados::REGISTRADO => 2,
            Estados::CON_ASESOR, Estados::EN_DESARROLLO => 3,
            Estados::VERIFICADO_ASESOR => 5,
            Estados::EN_REVISION_JURADO => $proyecto->enEtapaTesis() ? 7 : 5,
            Estados::APROBADO, Estados::EN_TESIS => 7,
            Estados::LISTO_SUSTENTAR, Estados::PROGRAMADO, Estados::DESAPROBADO => 8,
            Estados::SUSTENTADO, Estados::TESIS_APROBADA => 9,
            default => 1,
        };

        $pasos = [];
        foreach (self::PASOS as $i => [$titulo, $detalle]) {
            $n = $i + 1;
            $pasos[] = ['titulo' => $titulo, 'detalle' => $detalle, 'clase' => $n < $actual ? 'is-done' : ($n === $actual ? 'is-now' : '')];
        }

        return $pasos;
    }

    /** @return list<array{estado: string, comentario: ?string, fecha: mixed}> */
    public function historial(Proyecto $proyecto): array
    {
        return $proyecto->historial()->get(['estado_nuevo', 'observacion', 'cambiado_en'])
            ->map(fn ($h) => ['estado' => $h->estado_nuevo, 'comentario' => $h->observacion, 'fecha' => $h->cambiado_en])
            ->reverse()->values()->all();
    }

    /** Entregas del proyecto (más recientes primero). */
    public function entregas(Proyecto $proyecto, ?string $etapa = null, int $limite = 50)
    {
        $entregas = Entrega::query()
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->when($etapa, fn ($q) => $q->where('etapa', $etapa))
            ->orderByDesc('entregado_en')->orderByDesc('version')
            ->limit($limite)
            ->get(['id_entrega', 'uuid', 'etapa', 'version', 'tipo', 'numero_semana', 'comentario', 'estado', 'entregado_en', 'id_archivo']);

        $conteo = Observacion::query()
            ->join('tesis.revision as r', 'r.id_revision', '=', 'tesis.observacion.id_revision')
            ->whereIn('r.id_entrega', $entregas->pluck('id_entrega'))
            ->groupBy('r.id_entrega')
            ->selectRaw('r.id_entrega as id_entrega, COUNT(*) as n')
            ->pluck('n', 'id_entrega');

        return $entregas->each(fn ($e) => $e->setAttribute('observaciones_count', (int) ($conteo[$e->id_entrega] ?? 0)));
    }

    public function observacionesPendientes(Proyecto $proyecto): int
    {
        return Observacion::query()
            ->whereIn('id_revision', Revision::query()->select('id_revision')->whereIn('id_entrega', Entrega::query()->select('id_entrega')->where('id_proyecto', $proyecto->id_proyecto)))
            ->where('estado', 'Pendiente')
            ->count();
    }

    /**
     * Detalle del formato F1 para las fichas (estudiante, asesor y Director): datos generales, cronograma,
     * recursos con su partida (subtotales y totales por tipo), personal y colaboradores.
     *
     * @return array<string, mixed>
     */
    public function detalleF1(Proyecto $proyecto): array
    {
        $ficha = $this->ficha($proyecto);
        $partidas = PartidaPresupuesto::query()->where('id_proyecto', $proyecto->id_proyecto)
            ->orderBy('id_partida')->get(['id_partida', 'id_recurso', 'codigo_clasificador', 'descripcion', 'cantidad', 'unidad', 'costo_unitario']);
        $recursos = Recurso::query()->where('id_proyecto', $proyecto->id_proyecto)->where('tipo', '<>', 'Personal')
            ->orderBy('id_recurso')->get(['id_recurso', 'tipo', 'descripcion', 'cantidad'])->keyBy('id_recurso');

        $filas = [];
        foreach ($partidas as $p) {
            $r = $p->id_recurso !== null ? $recursos->pull($p->id_recurso) : null;
            $codigo = $p->codigo_clasificador ?? Parametros::SIN_CODIGO;
            $filas[] = [
                'tipo' => $r?->tipo ?? '—',
                'descripcion' => $p->descripcion ?? $r?->descripcion ?? '—',
                'codigo' => $codigo,
                'concepto' => Parametros::CLASIFICADOR_GASTOS[$codigo] ?? null,
                'unidad' => $p->unidad,
                'cantidad' => (float) $p->cantidad,
                'costo' => (float) $p->costo_unitario,
                'subtotal' => round((float) $p->cantidad * (float) $p->costo_unitario, 2),
            ];
        }
        // Recursos antiguos sin partida (registrados antes del formato con presupuesto por fila).
        foreach ($recursos as $r) {
            $filas[] = ['tipo' => $r->tipo, 'descripcion' => $r->descripcion, 'codigo' => null, 'concepto' => null, 'unidad' => null,
                'cantidad' => $r->cantidad !== null ? (float) $r->cantidad : null, 'costo' => null, 'subtotal' => 0.0];
        }

        $total = fn (?string $tipo) => round(array_sum(array_map(fn ($f) => $tipo === null || $f['tipo'] === $tipo ? $f['subtotal'] : 0, $filas)), 2);

        return [
            'generales' => [
                'Enfoque' => $proyecto->enfoque,
                'Tipo por el fin que persigue' => $proyecto->tipo_investigacion,
                'Diseño de contrastación' => $proyecto->diseno,
                'Línea de investigación' => $ficha['linea'],
                'Lugar de ejecución' => $proyecto->lugar_ejecucion,
                'Institución' => $proyecto->institucion,
                'Duración' => $proyecto->duracion_meses ? $proyecto->duracion_meses.' '.($proyecto->duracion_meses === 1 ? 'mes' : 'meses') : null,
                'Financiamiento' => $proyecto->financiamiento,
            ],
            'ayudaFinanciamiento' => Parametros::FINANCIAMIENTO_AYUDA[$proyecto->financiamiento] ?? null,
            'actividades' => ActividadCronograma::query()->where('id_proyecto', $proyecto->id_proyecto)->orderBy('orden')
                ->get(['descripcion', 'fecha_inicio', 'fecha_fin', 'horas_semanales'])->all(),
            'recursos' => $filas,
            'totales' => ['Bienes' => $total('Bienes'), 'Servicios' => $total('Servicios'), 'General' => $total(null)],
            'personal' => [
                ...array_map(fn ($i) => $i['nombre'].' · estudiante', $ficha['integrantes']),
                $ficha['asesor'] ? $ficha['asesor']['nombre'].' · asesor' : 'Asesor · por designar (resolución del Decanato)',
            ],
            'colaboradores' => Colaborador::query()->where('id_proyecto', $proyecto->id_proyecto)->orderBy('id_colaborador')
                ->get(['nombres_completos', 'institucion', 'rol_colaboracion'])->all(),
        ];
    }

    public function semanaActual(Proyecto $proyecto): int
    {
        $periodo = $proyecto->enEtapaTesis() && $proyecto->id_periodo_tesis
            ? $this->catalogo->periodo($proyecto->id_periodo_tesis)
            : $this->catalogo->periodoActual();

        return $this->catalogo->semanaDe($periodo);
    }
}
