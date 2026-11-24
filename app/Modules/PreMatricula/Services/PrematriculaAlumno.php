<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\DetalleSolicitud;
use App\Modules\PreMatricula\Models\LimiteCreditosEstudiante;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Support\Collection;

/** Casos de uso del alumno: armar, registrar y corregir su prematrícula. */
final class PrematriculaAlumno
{
    public function __construct(
        private readonly VentanaPrematricula $ventanas,
        private readonly ElegibilidadAcademica $elegibilidad,
        private readonly CatalogoAcademico $catalogo,
        private readonly RevalidacionPrematricula $revalidacion,
        private readonly RegistroRevision $revisiones,
        private readonly BloqueoAcademico $bloqueo,
    ) {}

    /**
     * Todo lo que necesita la pantalla "Mi prematrícula".
     *
     * @return array<string, mixed>
     */
    public function resumen(Estudiante $estudiante): array
    {
        $periodo = $this->ventanas->periodoHabilitado();
        $ventana = $periodo === null ? null : $this->ventanas->activa($periodo->id_periodo);
        $solicitud = $periodo === null ? null : $this->solicitud($estudiante, $periodo);
        if ($solicitud === null && $ventana !== null) {
            // Como en master (GetOrCreateDraft): el borrador se crea al ingresar con una ventana activa.
            $solicitud = $this->borrador($estudiante)->load(['revisionVigente', 'validacion']);
        }
        $seleccion = $solicitud === null ? collect() : $this->seleccion($solicitud);
        $editable = $solicitud !== null && $this->puedeEditar($solicitud, $ventana);
        $disponibles = $editable
            ? $this->elegibilidad->disponibles($estudiante, $periodo)->reject(fn (object $curso): bool => $seleccion->contains('id_curso_plan', $curso->id_curso_plan))->values()
            : collect();

        return [
            'estudiante' => $estudiante,
            'periodo' => $periodo,
            'ventana' => $ventana,
            'solicitud' => $solicitud,
            'seleccion' => $seleccion,
            'disponibles' => $disponibles,
            'ciclos' => $disponibles->pluck('ciclo')->unique()->sort()->values(),
            'creditos' => (float) $seleccion->sum('creditos'),
            'creditos_maximos' => LimiteCreditosEstudiante::creditosPara($estudiante->id_estudiante),
            'editable' => $editable,
            'puede_corregir' => $solicitud !== null
                && $solicitud->estado === SolicitudPrematricula::ESTADO_ENVIADA
                && $ventana?->tipo === CronogramaPrematricula::TIPO_EXTRAORDINARIA,
        ];
    }

    /** Obtiene o crea el borrador del período habilitado; exige una ventana activa. */
    private function borrador(Estudiante $estudiante): SolicitudPrematricula
    {
        return $this->bloqueo->transaccion(function () use ($estudiante): SolicitudPrematricula {
            $periodo = $this->periodoAbierto();
            if ($this->ventanas->activa($periodo->id_periodo) === null) {
                throw new PrematriculaException('La prematrícula no tiene una ventana activa.');
            }
            $solicitud = $this->solicitud($estudiante, $periodo) ?? SolicitudPrematricula::query()->create([
                'id_estudiante' => $estudiante->id_estudiante,
                'id_periodo' => $periodo->id_periodo,
                'estado' => SolicitudPrematricula::ESTADO_BORRADOR,
            ]);
            $this->revalidacion->evaluar($solicitud);

            return $solicitud;
        });
    }

    public function agregar(Estudiante $estudiante, int $idCursoPlan, string $turno = 'SIN_PREFERENCIA'): DetalleSolicitud
    {
        return $this->bloqueo->transaccion(function () use ($estudiante, $idCursoPlan, $turno): DetalleSolicitud {
            [$solicitud, $periodo] = $this->solicitudEditable($estudiante);

            if ($solicitud->detalles()->where('id_curso_plan', $idCursoPlan)->exists()) {
                throw new PrematriculaException('El curso ya está en tu selección.');
            }

            $curso = $this->elegibilidad->disponibles($estudiante, $periodo)->firstWhere('id_curso_plan', $idCursoPlan)
                ?? throw new PrematriculaException('No cumples los requisitos para llevar este curso.');

            $total = (float) $this->seleccion($solicitud)->sum('creditos') + $curso->creditos;
            $maximo = LimiteCreditosEstudiante::creditosPara($estudiante->id_estudiante);
            if ($total > $maximo) {
                throw new PrematriculaException("Con este curso sumarías {$this->numero($total)} créditos y tu límite es {$maximo}.");
            }

            $detalle = $solicitud->detalles()->create([
                'id_curso_plan' => $idCursoPlan,
                'estado' => 'SELECCIONADO',
                'preferencia_turno' => $turno,
            ]);
            $this->revalidacion->evaluar($solicitud);

            return $detalle;
        });
    }

    /** Cambia la preferencia de turno de un curso del borrador, como UpdateSchedulePreference de master. */
    public function cambiarTurno(Estudiante $estudiante, int $idDetalle, string $turno): DetalleSolicitud
    {
        return $this->bloqueo->transaccion(function () use ($estudiante, $idDetalle, $turno): DetalleSolicitud {
            [$solicitud] = $this->solicitudEditable($estudiante);
            $detalle = $solicitud->detalles()->whereKey($idDetalle)->first()
                ?? throw new PrematriculaException('El curso no pertenece a tu selección.');
            $detalle->update(['preferencia_turno' => $turno]);

            return $detalle->refresh();
        });
    }

    public function retirar(Estudiante $estudiante, int $idDetalle): void
    {
        $this->bloqueo->transaccion(function () use ($estudiante, $idDetalle): void {
            [$solicitud] = $this->solicitudEditable($estudiante);
            $borrados = $solicitud->detalles()->whereKey($idDetalle)->delete();

            if ($borrados === 0) {
                throw new PrematriculaException('El curso no pertenece a tu selección.');
            }

            $this->revalidacion->evaluar($solicitud);
        });
    }

    public function registrar(Estudiante $estudiante): SolicitudPrematricula
    {
        return $this->bloqueo->transaccion(function () use ($estudiante): SolicitudPrematricula {
            [$solicitud, $periodo, $ventana] = $this->solicitudEditable($estudiante);
            $seleccion = $this->seleccion($solicitud);

            if ($seleccion->isEmpty()) {
                throw new PrematriculaException('Agrega al menos un curso antes de registrar.');
            }

            $maximo = LimiteCreditosEstudiante::creditosPara($estudiante->id_estudiante);
            if ($seleccion->sum('creditos') > $maximo) {
                throw new PrematriculaException("Tu selección supera el límite de {$maximo} créditos.");
            }

            $disponibles = $this->elegibilidad->disponibles($estudiante, $periodo)->pluck('id_curso_plan')->all();
            foreach ($seleccion as $curso) {
                if (! in_array($curso->id_curso_plan, $disponibles, true)) {
                    throw new PrematriculaException("El curso {$curso->nombre} ya no cumple los requisitos. Retíralo para continuar.");
                }
            }

            $revision = $this->revisiones->registrar($solicitud, $ventana);
            $solicitud->update([
                'estado' => SolicitudPrematricula::ESTADO_ENVIADA,
                'enviada_en' => $revision->enviada_en,
                'id_revision_vigente' => $revision->id_revision,
                'id_cronograma_correccion' => null,
                'actualizado_en' => now(),
            ]);
            $this->revalidacion->evaluar($solicitud);

            return $solicitud->refresh();
        });
    }

    /** Reabre una prematrícula registrada durante una ventana extraordinaria. */
    public function iniciarCorreccion(Estudiante $estudiante): SolicitudPrematricula
    {
        return $this->bloqueo->transaccion(function () use ($estudiante): SolicitudPrematricula {
            $periodo = $this->periodoAbierto();
            $ventana = $this->ventanas->activa($periodo->id_periodo);
            $solicitud = $this->solicitud($estudiante, $periodo);

            if ($ventana?->tipo !== CronogramaPrematricula::TIPO_EXTRAORDINARIA
                || $solicitud?->estado !== SolicitudPrematricula::ESTADO_ENVIADA) {
                throw new PrematriculaException('Solo puedes corregir una prematrícula registrada durante una ventana extraordinaria.');
            }

            $solicitud->update([
                'estado' => SolicitudPrematricula::ESTADO_BORRADOR,
                'id_cronograma_correccion' => $ventana->id_cronograma,
                'actualizado_en' => now(),
            ]);
            $this->revalidacion->evaluar($solicitud);

            return $solicitud->refresh();
        });
    }

    /** @return Collection<int, SolicitudPrematricula> */
    public function historial(Estudiante $estudiante): Collection
    {
        return SolicitudPrematricula::query()
            ->with(['periodo', 'revisionVigente', 'revisiones' => fn ($query) => $query->whereNotNull('numero_revision')->orderByDesc('numero_revision')])
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->whereNotNull('id_revision_vigente')
            ->orderByDesc('id_periodo')
            ->get();
    }

    public function solicitud(Estudiante $estudiante, PeriodoAcademico $periodo): ?SolicitudPrematricula
    {
        return SolicitudPrematricula::query()
            ->with(['revisionVigente', 'validacion'])
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_periodo', $periodo->id_periodo)
            ->first();
    }

    /**
     * Cursos del borrador con datos del catálogo.
     *
     * @return Collection<int, object>
     */
    public function seleccion(SolicitudPrematricula $solicitud): Collection
    {
        $detalles = $solicitud->detalles()->orderBy('id_detalle')->get();
        $cursos = $this->catalogo->cursos($detalles->pluck('id_curso_plan')->map(fn ($id): int => (int) $id)->all());

        return $detalles->map(function (DetalleSolicitud $detalle) use ($cursos): object {
            $curso = clone $cursos->get($detalle->id_curso_plan);
            $curso->id_detalle = $detalle->id_detalle;

            return $curso;
        })->sortBy([['ciclo', 'asc'], ['codigo', 'asc']])->values();
    }

    private function puedeEditar(SolicitudPrematricula $solicitud, ?CronogramaPrematricula $ventana): bool
    {
        if ($ventana === null || $solicitud->estado !== SolicitudPrematricula::ESTADO_BORRADOR) {
            return false;
        }

        if ($solicitud->id_revision_vigente === null && $solicitud->id_cronograma_correccion === null) {
            return true;
        }

        return $ventana->tipo === CronogramaPrematricula::TIPO_EXTRAORDINARIA
            && (int) $solicitud->id_cronograma_correccion === (int) $ventana->id_cronograma;
    }

    /** @return array{0: SolicitudPrematricula, 1: PeriodoAcademico, 2: CronogramaPrematricula} */
    private function solicitudEditable(Estudiante $estudiante): array
    {
        $periodo = $this->periodoAbierto();
        $ventana = $this->ventanas->activa($periodo->id_periodo);
        $solicitud = $this->solicitud($estudiante, $periodo)
            ?? $this->borrador($estudiante);

        if (! $this->puedeEditar($solicitud, $ventana)) {
            throw new PrematriculaException('Tu prematrícula ya fue registrada y no se puede modificar ahora.');
        }

        return [$solicitud, $periodo, $ventana];
    }

    private function periodoAbierto(): PeriodoAcademico
    {
        return $this->ventanas->periodoHabilitado()
            ?? throw new PrematriculaException('La prematrícula no está abierta en este momento.');
    }

    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
