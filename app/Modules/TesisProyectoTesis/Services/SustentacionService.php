<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ResultadoJurado;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sustentación presencial (actividades 30 a 35): programación por Secretaría, resultado
 * aprobatorio / desaprobatorio de cada jurado (sin nota) y consolidación del resultado.
 */
final class SustentacionService
{
    public const PROGRAMABLES = [Estados::LISTO_SUSTENTAR, Estados::DESAPROBADO];

    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly CatalogoService $catalogo,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
        private readonly ActaService $actas,
    ) {}

    /** @return Collection<int, Proyecto> */
    public function porProgramar(): Collection
    {
        return Proyecto::query()->whereIn('estado', self::PROGRAMABLES)->orderBy('registrado_en')
            ->get(['id_proyecto', 'uuid', 'codigo', 'titulo', 'estado', 'etapa']);
    }

    /** @return Collection<int, Sustentacion> */
    public function recientes(int $limite = 20): Collection
    {
        return Sustentacion::query()->with('proyecto:id_proyecto,uuid,codigo,titulo,estado')
            ->orderByDesc('fecha_hora')->limit($limite)
            ->get(['id_sustentacion', 'uuid', 'id_proyecto', 'fecha_hora', 'hora_fin', 'id_ambiente', 'estado', 'numero_intento', 'total_aprobatorios', 'resultado', 'modo_resultado', 'mencion_honrosa']);
    }

    /**
     * Agenda de un ambiente en la semana de la fecha dada.
     *
     * @return list<array{dia: Carbon, eventos: list<array{hora: string, codigo: string}>}>
     */
    public function agenda(?int $idAmbiente, Carbon $fecha): array
    {
        $inicio = $fecha->copy()->startOfWeek();
        $eventos = $idAmbiente === null ? collect() : Sustentacion::query()
            ->with('proyecto:id_proyecto,codigo')
            ->where('id_ambiente', $idAmbiente)
            ->where('estado', 'Programada')
            ->whereBetween('fecha_hora', [$inicio, $inicio->copy()->addDays(6)->endOfDay()])
            ->get(['id_proyecto', 'fecha_hora']);

        $dias = [];
        for ($i = 0; $i < 6; $i++) {
            $dia = $inicio->copy()->addDays($i);
            $dias[] = ['dia' => $dia, 'eventos' => $eventos->filter(fn ($e) => $e->fecha_hora->isSameDay($dia))
                ->map(fn ($e) => ['hora' => $e->fecha_hora->format('H:i'), 'codigo' => $e->proyecto->codigo])->values()->all()];
        }

        return $dias;
    }

    /**
     * Disponibilidad de jurados y asesor en el horario propuesto.
     *
     * @return list<array{rol: string, nombre: string, disponible: bool}>
     */
    public function participantes(Proyecto $proyecto, ?Carbon $inicio = null): array
    {
        $lista = [];
        $orden = array_flip(Parametros::CARGOS_JURADO);
        $jurados = $proyecto->juradosActivos()->get(['id_docente', 'cargo'])->sortBy(fn ($j) => $orden[$j->cargo] ?? 9);
        $asesor = $proyecto->asesorActivo()->value('id_docente');
        $nombres = $this->identidad->docentes(array_filter([...$jurados->pluck('id_docente')->all(), $asesor]));

        foreach ($jurados as $j) {
            $lista[] = ['rol' => $j->cargo, 'nombre' => $nombres[$j->id_docente]['nombre'] ?? '—',
                'disponible' => $inicio === null || ! $this->docenteOcupado((int) $j->id_docente, $inicio, $proyecto)];
        }
        if ($asesor) {
            $lista[] = ['rol' => 'Asesor', 'nombre' => $nombres[$asesor]['nombre'] ?? '—',
                'disponible' => $inicio === null || ! $this->docenteOcupado((int) $asesor, $inicio, $proyecto)];
        }

        return $lista;
    }

    public function programar(Proyecto $proyecto, Identidad $secretaria, string $fecha, string $hora, string $codigoAmbiente): Sustentacion
    {
        $idAmbiente = $this->catalogo->idAmbientePorCodigo($codigoAmbiente);
        if ($idAmbiente === null) {
            throw ValidationException::withMessages(['ambiente' => 'El ambiente no está disponible.']);
        }

        $inicio = Carbon::parse($fecha.' '.$hora);
        if ($inicio->lt(Carbon::now()->startOfDay())) {
            throw ValidationException::withMessages(['fecha' => 'La fecha no puede ser anterior a hoy.']);
        }

        return DB::transaction(function () use ($proyecto, $secretaria, $inicio, $idAmbiente) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['tesis.agenda']);
            $p = $this->historial->bloquear($proyecto);

            if (! in_array($p->estado, self::PROGRAMABLES, true)) {
                throw ValidationException::withMessages(['fecha' => 'Solo se programan tesis con la conformidad de los tres jurados o una sustentación desaprobada.']);
            }
            if ($p->juradosActivos()->count() !== Parametros::MIEMBROS_JURADO) {
                throw ValidationException::withMessages(['fecha' => 'El proyecto no tiene el jurado completo.']);
            }
            if ($this->choques()->where('id_ambiente', $idAmbiente)->filter(fn ($s) => $this->seSolapan($s->fecha_hora, $inicio))->isNotEmpty()) {
                throw ValidationException::withMessages(['ambiente' => 'El ambiente ya tiene una sustentación en ese horario.']);
            }
            foreach ($this->participantes($p, $inicio) as $part) {
                if (! $part['disponible']) {
                    throw ValidationException::withMessages(['hora' => "{$part['rol']} ({$part['nombre']}) tiene otra sustentación en ese horario."]);
                }
            }

            $sustentacion = Sustentacion::create([
                'id_proyecto' => $p->id_proyecto,
                'fecha_hora' => $inicio,
                'modalidad' => 'Presencial',
                'id_ambiente' => $idAmbiente,
                'estado' => 'Programada',
                'numero_intento' => (int) Sustentacion::query()->where('id_proyecto', $p->id_proyecto)->max('numero_intento') + 1,
                'programada_por' => $secretaria->idUsuario,
            ])->refresh();

            $lugar = $this->catalogo->nombreAmbiente($idAmbiente);
            $this->historial->cambiarEstado($p, Estados::PROGRAMADO, $secretaria->idUsuario,
                "Sustentación (intento {$sustentacion->numero_intento}) el {$inicio->format('d/m/Y H:i')} en {$lugar}.");

            $this->notificaciones->notificar([
                ...$this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all()),
                ...$this->identidad->usuariosDeDocentes([...$p->juradosActivos()->pluck('id_docente')->all(), $p->asesorActivo()->value('id_docente')]),
            ], 'sustentacion.programada', "Sustentación programada · {$p->codigo}", "{$inicio->format('d/m/Y')} a las {$inicio->format('H:i')} · {$lugar} (presencial).", $p);

            return $sustentacion;
        });
    }

    public function miResultado(Sustentacion $s, int $idDocente): ?ResultadoJurado
    {
        $jurado = $this->juradoDe($s, $idDocente);

        return $jurado ? ResultadoJurado::query()->where('id_sustentacion', $s->id_sustentacion)->where('id_jurado', $jurado->id_jurado)->first() : null;
    }

    /**
     * Registro del jurado: quién registró (sin mostrar el resultado individual hasta que estén los tres).
     *
     * @return list<array{cargo: string, nombre: string, registrado: bool, resultado: ?string}>
     */
    public function registroJurado(Sustentacion $s): array
    {
        $jurados = JuradoProyecto::query()->where('id_proyecto', $s->id_proyecto)->where('estado', 'Activo')->get(['id_jurado', 'id_docente', 'cargo']);
        $resultados = ResultadoJurado::query()->where('id_sustentacion', $s->id_sustentacion)->get(['id_jurado', 'resultado'])->keyBy('id_jurado');
        $nombres = $this->identidad->docentes($jurados->pluck('id_docente')->all());
        $completo = $resultados->count() >= Parametros::MIEMBROS_JURADO;
        $orden = array_flip(Parametros::CARGOS_JURADO);

        return $jurados->sortBy(fn ($j) => $orden[$j->cargo] ?? 9)->map(fn ($j) => [
            'cargo' => $j->cargo,
            'nombre' => $nombres[$j->id_docente]['nombre'] ?? '—',
            'registrado' => $resultados->has($j->id_jurado),
            'resultado' => $completo ? $resultados[$j->id_jurado]?->resultado : null,
        ])->values()->all();
    }

    /** @return Collection<int, Sustentacion> sustentaciones del docente como jurado activo */
    public function deJurado(int $idDocente): Collection
    {
        $proyectos = JuradoProyecto::query()->where('id_docente', $idDocente)->where('estado', 'Activo')->pluck('id_proyecto');

        return Sustentacion::query()->whereIn('id_proyecto', $proyectos)->with('proyecto:id_proyecto,uuid,codigo,titulo,estado')
            ->orderByDesc('fecha_hora')
            ->get(['id_sustentacion', 'uuid', 'id_proyecto', 'fecha_hora', 'id_ambiente', 'estado', 'numero_intento', 'resultado', 'modo_resultado']);
    }

    public function registrarResultado(Sustentacion $sustentacion, Identidad $docente, string $resultado, bool $mencion, ?string $horaFin): Sustentacion
    {
        return DB::transaction(function () use ($sustentacion, $docente, $resultado, $mencion, $horaFin) {
            $s = Sustentacion::query()->whereKey($sustentacion->id_sustentacion)->lockForUpdate()->firstOrFail();
            $jurado = $this->juradoDe($s, (int) $docente->idDocente);

            if ($jurado === null) {
                abort(403);
            }
            if ($s->estado !== 'Programada') {
                throw ValidationException::withMessages(['resultado' => 'La sustentación ya fue consolidada.']);
            }
            if ($s->fecha_hora->startOfDay()->gt(Carbon::today())) {
                throw ValidationException::withMessages(['resultado' => 'El resultado se registra el día de la sustentación.']);
            }
            if (ResultadoJurado::query()->where('id_sustentacion', $s->id_sustentacion)->where('id_jurado', $jurado->id_jurado)->exists()) {
                throw ValidationException::withMessages(['resultado' => 'Ya registraste tu resultado.']);
            }

            ResultadoJurado::create([
                'id_sustentacion' => $s->id_sustentacion,
                'id_jurado' => $jurado->id_jurado,
                'nota' => null,
                'dictamen' => $resultado,
                'resultado' => $resultado,
                'propone_mencion' => $resultado === 'Aprobatorio' && $mencion,
                'registrado_en' => Carbon::now(),
            ]);

            if ($horaFin && $s->hora_fin === null) {
                $s->update(['hora_fin' => $horaFin]);
            }

            $this->consolidarSiCompleto($s, $docente);

            return $s->refresh();
        });
    }

    private function consolidarSiCompleto(Sustentacion $s, Identidad $docente): void
    {
        $resultados = ResultadoJurado::query()->where('id_sustentacion', $s->id_sustentacion)->get(['resultado', 'propone_mencion']);
        if ($resultados->count() < Parametros::MIEMBROS_JURADO) {
            return;
        }

        $aprobatorios = $resultados->where('resultado', 'Aprobatorio')->count();
        $aprobado = $aprobatorios >= Parametros::APROBATORIOS_MINIMOS;
        $unanimidad = $aprobatorios === Parametros::MIEMBROS_JURADO;

        $s->update([
            'estado' => 'Realizada',
            'total_aprobatorios' => $aprobatorios,
            'resultado' => $aprobado ? 'Aprobado' : 'Desaprobado',
            'modo_resultado' => $aprobado ? ($unanimidad ? 'Unanimidad' : 'Mayoria') : null,
            'mencion_honrosa' => $unanimidad && $resultados->every(fn ($r) => $r->propone_mencion),
        ]);

        $acta = $this->actas->generarActaSustentacion($s->refresh());
        $p = $this->historial->bloquear($s->proyecto);

        if ($aprobado) {
            $this->historial->cambiarEstado($p, Estados::SUSTENTADO, $docente->idUsuario,
                "{$acta->resultado} ({$aprobatorios} aprobatorios). Acta N.° {$acta->numero}.", ['fecha_aprobacion_tesis' => $s->fecha_hora->toDateString()]);
        } else {
            $this->historial->cambiarEstado($p, Estados::DESAPROBADO, $docente->idUsuario,
                "Desaprobado ({$aprobatorios} aprobatorios). Acta N.° {$acta->numero}. Se programará una nueva fecha.");
        }

        $todos = [
            ...$this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all()),
            ...$this->identidad->usuariosDeDocentes([...$p->juradosActivos()->pluck('id_docente')->all(), AsesorProyecto::query()->where('id_proyecto', $p->id_proyecto)->where('estado', 'Activo')->value('id_docente')]),
        ];
        $this->notificaciones->notificar($todos, 'sustentacion.resultado', "Resultado de la sustentación · {$p->codigo}", "{$acta->resultado}.", $p);
        $this->notificaciones->notificar([...$this->identidad->usuariosConRol('secretaria'), ...$this->identidad->usuariosConRol('director_escuela')],
            'acta.generada', "Acta N.° {$acta->numero} · {$p->codigo}",
            $aprobado ? "{$acta->resultado}. Imprime el acta para la firma y súbela escaneada." : 'Desaprobado. Registra el acta firmada y programa una nueva fecha.', $p);
    }

    private function juradoDe(Sustentacion $s, int $idDocente): ?JuradoProyecto
    {
        return JuradoProyecto::query()->where('id_proyecto', $s->id_proyecto)->where('id_docente', $idDocente)->where('estado', 'Activo')->first();
    }

    /** @return Collection<int, Sustentacion> */
    private function choques(): Collection
    {
        return Sustentacion::query()->where('estado', 'Programada')->get(['id_sustentacion', 'id_proyecto', 'id_ambiente', 'fecha_hora']);
    }

    private function docenteOcupado(int $idDocente, Carbon $inicio, Proyecto $excepto): bool
    {
        $proyectos = JuradoProyecto::query()->where('id_docente', $idDocente)->where('estado', 'Activo')->pluck('id_proyecto')
            ->merge(AsesorProyecto::query()->where('id_docente', $idDocente)->where('estado', 'Activo')->pluck('id_proyecto'))
            ->reject(fn ($id) => (int) $id === $excepto->id_proyecto)->unique();

        return $this->choques()->whereIn('id_proyecto', $proyectos->all())->contains(fn ($s) => $this->seSolapan($s->fecha_hora, $inicio));
    }

    private function seSolapan(Carbon $a, Carbon $b): bool
    {
        return abs($a->diffInMinutes($b, false)) < Parametros::DURACION_SUSTENTACION_MINUTOS;
    }
}
