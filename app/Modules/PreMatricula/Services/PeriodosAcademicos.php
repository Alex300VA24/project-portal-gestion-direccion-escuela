<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de períodos académicos, como ManageAcademicPeriod de la rama master.
 * Al pasar un período a PREMATRICULA sin ventanas se abre la ventana ordinaria.
 */
final class PeriodosAcademicos
{
    public const PLANIFICACION = 'PLANIFICACION';

    public const PREMATRICULA = 'PREMATRICULA';

    public const EN_CURSO = 'EN_CURSO';

    public const CERRADO = 'CERRADO';

    public const ESTADOS = [self::PLANIFICACION, self::PREMATRICULA, self::EN_CURSO, self::CERRADO];

    /** El portal no trae traducciones de validación; estos mensajes las reemplazan. */
    public const MENSAJES = [
        'required' => 'El campo :attribute es obligatorio.',
        'max' => 'El campo :attribute admite como máximo :max caracteres.',
        'unique' => 'Ya existe un período con ese :attribute.',
        'date_format' => 'El campo :attribute debe ser una fecha válida.',
        'after_or_equal' => 'El fin de clases no puede ser anterior al inicio.',
        'in' => 'Elige un estado válido: Planificación, Prematrícula, En curso o Cerrado.',
    ];

    public function __construct(private readonly BloqueoAcademico $bloqueo) {}

    /** @return Collection<int, PeriodoAcademico> */
    public function listar(): Collection
    {
        $periodos = PeriodoAcademico::query()->orderByDesc('fecha_inicio')->orderByDesc('id_periodo')->get();
        $ventanas = CronogramaPrematricula::query()
            ->whereIn('id_periodo', $periodos->modelKeys())
            ->orderBy('fecha_inicio')
            ->get()
            ->groupBy('id_periodo');

        return $periodos->each(fn (PeriodoAcademico $periodo) => $periodo->setRelation('ventanas', $ventanas->get($periodo->id_periodo, collect())));
    }

    /** @param array<string, mixed> $datos */
    public function guardar(array $datos, ?int $idUsuario, ?PeriodoAcademico $periodo = null): PeriodoAcademico
    {
        return $this->bloqueo->transaccion(function () use ($datos, $idUsuario, $periodo): PeriodoAcademico {
            $actual = $periodo === null ? null : PeriodoAcademico::query()->lockForUpdate()->findOrFail($periodo->id_periodo);
            $base = $actual === null ? [] : [
                'codigo' => $actual->codigo,
                'estado' => $actual->estado,
                'fecha_inicio' => $actual->fecha_inicio->toDateString(),
                'fecha_fin' => $actual->fecha_fin->toDateString(),
            ];
            $datos = array_replace($base, array_intersect_key($datos, array_flip(['codigo', 'estado', 'fecha_inicio', 'fecha_fin'])));

            Validator::make($datos, [
                'codigo' => ['required', 'string', 'max:20', Rule::unique(PeriodoAcademico::class, 'codigo')->ignore($actual?->id_periodo, 'id_periodo')],
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
                'estado' => ['required', Rule::in(self::ESTADOS)],
            ], self::MENSAJES, ['fecha_inicio' => 'inicio de clases', 'fecha_fin' => 'fin de clases'])->validate();

            if ($actual !== null && $this->referenciado($actual)
                && ($actual->codigo !== $datos['codigo']
                    || $actual->fecha_inicio->toDateString() !== $datos['fecha_inicio']
                    || $actual->fecha_fin->toDateString() !== $datos['fecha_fin'])) {
                throw ValidationException::withMessages(['periodo' => 'Un período referenciado conserva código y fechas de clases. Puede administrar su estado y añadir ventanas.']);
            }

            $inicio = CarbonImmutable::parse($datos['fecha_inicio']);
            $fin = CarbonImmutable::parse($datos['fecha_fin']);
            $guardado = $actual ?? new PeriodoAcademico;
            $guardado->fill([
                ...$datos,
                'anio' => $inicio->year,
                'tipo' => $this->tipo($datos['codigo']),
                'numero_semanas' => max(1, (int) ceil(($inicio->diffInDays($fin) + 1) / 7)),
            ]);

            try {
                $guardado->save();
            } catch (QueryException) {
                throw ValidationException::withMessages(['codigo' => 'Ya existe un período del mismo año y tipo.']);
            }

            if ($guardado->estado === self::PREMATRICULA
                && ! CronogramaPrematricula::query()->where('id_periodo', $guardado->id_periodo)->exists()) {
                $this->abrirVentanaOrdinaria($guardado, $idUsuario);
            }

            return $guardado->refresh();
        });
    }

    public function eliminar(PeriodoAcademico $periodo): void
    {
        $this->bloqueo->transaccion(function () use ($periodo): void {
            $actual = PeriodoAcademico::query()->lockForUpdate()->findOrFail($periodo->id_periodo);
            if ($this->referenciado($actual) || CronogramaPrematricula::query()->where('id_periodo', $actual->id_periodo)->exists()) {
                throw new PrematriculaException('No se puede eliminar un período referenciado.');
            }

            try {
                $actual->delete();
            } catch (QueryException) {
                throw new PrematriculaException('No se puede eliminar un período referenciado.');
            }
        });
    }

    public function referenciado(PeriodoAcademico $periodo): bool
    {
        return SolicitudPrematricula::query()->where('id_periodo', $periodo->id_periodo)->exists()
            || HistorialAcademico::query()->where('id_periodo', $periodo->id_periodo)->exists();
    }

    /** Ventana ordinaria desde ahora hasta el fin del último día de clases (hora de Lima). */
    private function abrirVentanaOrdinaria(PeriodoAcademico $periodo, ?int $idUsuario): void
    {
        $zona = VentanaPrematricula::ZONA_HORARIA;

        CronogramaPrematricula::query()->create([
            'id_periodo' => $periodo->id_periodo,
            'tipo' => CronogramaPrematricula::TIPO_ORDINARIA,
            'fecha_inicio' => CarbonImmutable::now($zona)->utc(),
            'fecha_fin' => CarbonImmutable::parse($periodo->fecha_fin->toDateString(), $zona)->endOfDay()->utc(),
            'estado' => 'PROGRAMADA',
            'motivo' => 'Apertura automática al habilitar período en prematrícula',
            'zona_horaria' => $zona,
            'creado_por' => $idUsuario,
        ]);
    }

    /** "2026-II" → "II"; un código sin guion se usa completo. */
    private function tipo(string $codigo): string
    {
        $partes = explode('-', $codigo, 2);

        return mb_substr(trim($partes[1] ?? $partes[0]), 0, 20);
    }
}
