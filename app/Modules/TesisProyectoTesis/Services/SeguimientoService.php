<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Resolucion;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;

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

    public function semanaActual(Proyecto $proyecto): int
    {
        $periodo = $proyecto->enEtapaTesis() && $proyecto->id_periodo_tesis
            ? $this->catalogo->periodo($proyecto->id_periodo_tesis)
            : $this->catalogo->periodoActual();

        return $this->catalogo->semanaDe($periodo);
    }
}
