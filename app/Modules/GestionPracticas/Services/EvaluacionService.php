<?php

namespace App\Modules\GestionPracticas\Services;

use App\Modules\GestionPracticas\Models\CriterioEvaluacion;
use App\Modules\GestionPracticas\Models\DocumentoPractica;
use App\Modules\GestionPracticas\Models\Evaluacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Support\Acceso;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Evaluación final del docente supervisor y cierre de la práctica. */
final class EvaluacionService
{
    /** @return Collection<int, CriterioEvaluacion> */
    public function criterios(): Collection
    {
        return CriterioEvaluacion::where('evaluador', Evaluacion::SUPERVISOR)
            ->where('activo', true)->orderBy('id_criterio')->get();
    }

    /**
     * Registra (o reemplaza) la evaluación del supervisor.
     *
     * @param  array<int|string, mixed>  $notas  id_criterio => nota (0–20)
     */
    public function registrar(Practica $practica, Actor $supervisor, array $notas, ?string $comentario): Evaluacion
    {
        $this->exigirSupervisor($practica, $supervisor);
        $this->exigirEnCurso($practica);

        $criterios = $this->criterios();

        if ($criterios->isEmpty()) {
            throw ValidationException::withMessages(['notas' => 'No hay criterios de evaluación activos para el supervisor.']);
        }

        $filas = [];

        foreach ($criterios as $criterio) {
            $nota = $notas[$criterio->id_criterio] ?? null;

            if (! is_numeric($nota) || $nota < 0 || $nota > 20) {
                throw ValidationException::withMessages([
                    "notas.{$criterio->id_criterio}" => "Ingrese una nota entre 0 y 20 para «{$criterio->nombre}».",
                ]);
            }

            $filas[] = ['id_criterio' => $criterio->id_criterio, 'nota' => round((float) $nota, 2)];
        }

        return DB::transaction(function () use ($practica, $supervisor, $comentario, $filas): Evaluacion {
            $evaluacion = Evaluacion::firstOrNew([
                'id_practica' => $practica->id_practica,
                'evaluador' => Evaluacion::SUPERVISOR,
            ]);
            $evaluacion->fill([
                'evaluado_por' => $supervisor->requiereUsuario(),
                'comentario' => $comentario,
                'evaluado_en' => now(),
            ])->save();
            $evaluacion->refresh();

            DB::table('gestion_practicas.evaluacion_criterio')->where('id_evaluacion', $evaluacion->id_evaluacion)->delete();
            DB::table('gestion_practicas.evaluacion_criterio')->insert(
                array_map(fn (array $fila) => $fila + ['id_evaluacion' => $evaluacion->id_evaluacion], $filas),
            );

            return $evaluacion;
        });
    }

    public function notaFinal(Practica $practica): ?float
    {
        $nota = DB::selectOne('SELECT gestion_practicas.calcular_nota_final(?) AS nota', [$practica->id_practica])->nota;

        return $nota === null ? null : (float) $nota;
    }

    /** Cierra la práctica: exige evaluación registrada e informe final aprobado. */
    public function cerrar(Practica $practica, Actor $supervisor): Practica
    {
        $this->exigirSupervisor($practica, $supervisor);
        $this->exigirEnCurso($practica);

        $evaluada = Evaluacion::where('id_practica', $practica->id_practica)
            ->where('evaluador', Evaluacion::SUPERVISOR)->exists();

        if (! $evaluada) {
            throw ValidationException::withMessages(['estado' => 'Registre la nota de la evaluación antes de cerrar la práctica.']);
        }

        $informeAprobado = $practica->documentos()
            ->where('tipo_documento', 'informe_final')
            ->where('estado', DocumentoPractica::APROBADO)->exists();

        if (! $informeAprobado) {
            throw ValidationException::withMessages(['estado' => 'El informe final debe estar aprobado antes de cerrar la práctica.']);
        }

        $nota = $this->notaFinal($practica);

        if ($nota === null) {
            throw ValidationException::withMessages(['estado' => 'No se pudo calcular la nota final. Verifique que todas las notas estén registradas.']);
        }

        $practica->update([
            'estado' => Practica::FINALIZADA,
            'nota_final' => $nota,
            'resultado' => $nota >= Acceso::NOTA_MINIMA ? 'aprobado' : 'desaprobado',
            'cerrada_por' => $supervisor->requiereUsuario(),
            'cerrada_en' => now(),
        ]);

        return $practica;
    }

    private function exigirSupervisor(Practica $practica, Actor $supervisor): void
    {
        if ((int) $practica->id_docente_supervisor !== $supervisor->requiereDocente()) {
            throw new AuthorizationException('Solo el docente supervisor asignado a esta práctica puede realizar esta acción.');
        }
    }

    private function exigirEnCurso(Practica $practica): void
    {
        if ($practica->estado !== Practica::EN_CURSO) {
            throw ValidationException::withMessages(['estado' => 'Solo se puede registrar la evaluación mientras la práctica está en curso.']);
        }
    }
}
