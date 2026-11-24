<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\CriterioRubrica;
use App\Modules\TesisProyectoTesis\Models\DetalleEvaluacion;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Models\Rubrica;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verificación con la rúbrica única (cumple / no cumple), usada por el asesor y por cada jurado.
 */
final class RubricaService
{
    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly NotificacionService $notificaciones,
    ) {}

    public function vigente(): Rubrica
    {
        $rubrica = Rubrica::query()->where('estado', 'Vigente')->orderByDesc('version')->first();

        if ($rubrica === null) {
            throw ValidationException::withMessages(['marcas' => 'No hay una rúbrica vigente. Comunícate con la Dirección de Escuela.']);
        }

        return $rubrica;
    }

    /** @return Collection<int, CriterioRubrica> criterios que aplican a la etapa, indexados por orden */
    public function criterios(string $etapa): Collection
    {
        return CriterioRubrica::query()
            ->where('id_rubrica', $this->vigente()->id_rubrica)
            ->where('estado', 'Activo')
            ->whereIn('aplica_a', ['Ambas', $etapa])
            ->orderBy('orden')
            ->get(['id_criterio', 'orden', 'seccion', 'descripcion', 'aplica_a', 'permite_no_aplica'])
            ->keyBy('orden');
    }

    /**
     * Versión del documento que se verifica: la última de la etapa marcada como final o culminada.
     */
    public function entregaObjetivo(Proyecto $proyecto): ?Entrega
    {
        return Entrega::query()
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('etapa', $proyecto->etapa)
            ->where(fn ($q) => $q->where('tipo', 'Final')->orWhere('estado', 'Culminada'))
            ->orderByDesc('version')
            ->first();
    }

    public function revisionDe(Entrega $entrega, int $idDocente, string $tipo): ?Revision
    {
        return Revision::query()->where('id_entrega', $entrega->id_entrega)->where('id_docente', $idDocente)->where('tipo', $tipo)
            ->with(['detalles', 'observaciones'])->first();
    }

    /** Última verificación (con rúbrica) de un docente en la etapa actual del proyecto. */
    public function ultimaVerificacion(Proyecto $proyecto, int $idDocente, string $tipo = 'Verificacion'): ?Revision
    {
        return Revision::query()
            ->join('tesis.entrega as e', 'e.id_entrega', '=', 'tesis.revision.id_entrega')
            ->where('e.id_proyecto', $proyecto->id_proyecto)
            ->where('e.etapa', $proyecto->etapa)
            ->where('tesis.revision.id_docente', $idDocente)
            ->where('tesis.revision.tipo', $tipo)
            ->orderByDesc('tesis.revision.revisado_en')
            ->orderByDesc('tesis.revision.id_revision')
            ->select('tesis.revision.*', 'e.version as version_entrega')
            ->first();
    }

    /**
     * Verificación del asesor (actividades 10 y 23): solo en desarrollo y sobre la versión final vigente.
     *
     * @param  array{marcas: array, obs?: array, accion: string, comentario?: ?string}  $datos
     */
    public function verificarComoAsesor(Proyecto $proyecto, Identidad $asesor, array $datos): Revision
    {
        if (! in_array($proyecto->estado, Estados::DESARROLLO, true)) {
            throw ValidationException::withMessages(['marcas' => 'El proyecto no está en desarrollo con el asesor.']);
        }
        $entrega = $this->entregaObjetivo($proyecto);
        if ($entrega === null) {
            throw ValidationException::withMessages(['marcas' => 'Aún no hay una versión final o culminada para verificar.']);
        }

        return $this->verificar($proyecto, $entrega, $asesor, 'Asesor', 'Verificacion', $datos['marcas'], $datos['obs'] ?? [],
            $datos['accion'] === 'conformidad', $datos['comentario'] ?? null);
    }

    /**
     * @param  array<int|string, string>  $marcas  orden => cumple | no_cumple | no_aplica
     * @param  array<int|string, ?string>  $obs  orden => observación
     */
    public function verificar(Proyecto $proyecto, Entrega $entrega, Identidad $docente, string $rol, string $tipo, array $marcas, array $obs, bool $conformidad, ?string $comentario = null): Revision
    {
        $referencial = $tipo === 'Intermedia';
        $criterios = $this->criterios($proyecto->etapa);

        $errores = [];
        foreach ($criterios as $orden => $c) {
            $marca = $marcas[$orden] ?? null;
            if (! in_array($marca, ['cumple', 'no_cumple', 'no_aplica'], true)) {
                $errores["marcas.$orden"] = "Marca el criterio $orden.";
            } elseif ($marca === 'no_aplica' && ! $c->permite_no_aplica) {
                $errores["marcas.$orden"] = "El criterio $orden no admite «no aplica».";
            } elseif ($marca === 'no_cumple' && trim((string) ($obs[$orden] ?? '')) === '') {
                $errores["obs.$orden"] = "El criterio $orden está en «no cumple»: registra su observación.";
            }
        }
        foreach (array_keys($marcas) as $orden) {
            if (! $criterios->has((int) $orden)) {
                $errores['marcas'] = 'Hay criterios que no aplican a esta etapa.';
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $cumpleTodo = $criterios->keys()->every(fn ($o) => $marcas[$o] !== 'no_cumple');

        if ($conformidad && ! $cumpleTodo) {
            throw ValidationException::withMessages(['conformidad' => 'Solo puedes dar conformidad con todos los criterios en «cumple».']);
        }
        if ($referencial) {
            $conformidad = false;
        }

        return DB::transaction(function () use ($proyecto, $entrega, $docente, $rol, $tipo, $marcas, $obs, $conformidad, $comentario, $criterios, $cumpleTodo, $referencial) {
            $e = Entrega::query()->whereKey($entrega->id_entrega)->lockForUpdate()->firstOrFail();

            if ($e->id_proyecto !== $proyecto->id_proyecto) {
                abort(404);
            }
            if ($this->revisionDe($e, (int) $docente->idDocente, $tipo) !== null) {
                throw ValidationException::withMessages(['marcas' => "Ya registraste tu verificación de la versión v{$e->version}. Espera la nueva versión del estudiante."]);
            }

            $revision = Revision::create([
                'id_entrega' => $e->id_entrega,
                'id_docente' => $docente->idDocente,
                'id_rubrica' => $this->vigente()->id_rubrica,
                'tipo' => $tipo,
                'rol_revisor' => $rol,
                'dictamen' => $referencial ? 'Referencial' : ($conformidad ? 'Conforme' : 'Observado'),
                'comentario' => $comentario,
                'cumple_todo' => $cumpleTodo,
                'conforme' => $conformidad,
                'referencial' => $referencial,
                'revisado_en' => Carbon::now(),
            ])->refresh();

            foreach ($criterios as $orden => $c) {
                DetalleEvaluacion::create([
                    'id_revision' => $revision->id_revision,
                    'id_criterio' => $c->id_criterio,
                    'cumple' => $marcas[$orden] !== 'no_cumple',
                    'no_aplica' => $marcas[$orden] === 'no_aplica',
                ]);

                $texto = trim((string) ($obs[$orden] ?? ''));
                if ($marcas[$orden] === 'no_cumple' || ($marcas[$orden] === 'no_aplica' && $texto !== '')) {
                    Observacion::create([
                        'id_revision' => $revision->id_revision,
                        'id_criterio' => $c->id_criterio,
                        'seccion_documento' => $c->seccion,
                        'descripcion' => $marcas[$orden] === 'no_aplica' ? 'No aplica: '.$texto : $texto,
                        'estado' => $marcas[$orden] === 'no_aplica' ? 'Levantada' : 'Pendiente',
                    ]);
                }
            }

            if ($rol === 'Asesor' && ! $cumpleTodo) {
                $e->update(['estado' => 'Observada']);
            } elseif ($rol === 'Asesor' && $conformidad && $e->estado === 'Pendiente') {
                $e->update(['estado' => 'Culminada']);
            }

            $quien = $rol === 'Asesor' ? 'Tu asesor' : 'Un miembro del jurado';
            $mensaje = $referencial
                ? 'Revisión intermedia del jurado (referencial, no bloquea tu avance).'
                : ($conformidad ? 'Todos los criterios cumplen: conformidad registrada.' : 'Hay criterios en «no cumple». Revisa tus observaciones.');

            $this->notificaciones->notificar(
                $this->identidad->usuariosDeEstudiantes($proyecto->integrantesActivos()->pluck('id_estudiante')->all()),
                'rubrica.verificada', "{$quien} verificó la versión v{$e->version} con la rúbrica", $mensaje, $proyecto,
            );

            return $revision;
        });
    }

    /**
     * Resumen de la verificación: cuántos cumplen y cuántos no.
     *
     * @return array{cumple: int, no_cumple: int, total: int}
     */
    public function resumen(?Revision $revision, int $total): array
    {
        if ($revision === null) {
            return ['cumple' => 0, 'no_cumple' => 0, 'total' => $total];
        }

        $detalles = $revision->relationLoaded('detalles') ? $revision->detalles : $revision->detalles()->get();

        return ['cumple' => $detalles->where('cumple', true)->count(), 'no_cumple' => $detalles->where('cumple', false)->count(), 'total' => $total];
    }
}
