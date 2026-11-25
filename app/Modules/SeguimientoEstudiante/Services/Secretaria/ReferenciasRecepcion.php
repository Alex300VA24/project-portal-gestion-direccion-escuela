<?php

namespace App\Modules\SeguimientoEstudiante\Services\Secretaria;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class ReferenciasRecepcion
{
    public function comprobar(string $tipo, int $escuela, int $periodo, array $resultado): array
    {
        $filas = $resultado['registros'];
        $estudiantes = DB::table('core.estudiante')->where('id_escuela', $escuela)
            ->whereIn('codigo_universitario', array_column($filas, 'student'))
            ->get(['id_estudiante', 'id_plan', 'codigo_universitario'])->groupBy('codigo_universitario');
        $secciones = DB::table('core.seccion as seccion')
            ->join('core.curso_plan as plan', 'plan.id_curso_plan', '=', 'seccion.id_curso_plan')
            ->join('core.plan_estudios as plan_escuela', 'plan_escuela.id_plan', '=', 'plan.id_plan')
            ->where('plan_escuela.id_escuela', $escuela)
            ->join('core.curso as curso', 'curso.id_curso', '=', 'plan.id_curso')
            ->where('seccion.id_periodo', $periodo)
            ->whereIn('plan.id_plan', $estudiantes->flatten(1)->pluck('id_plan')->unique()->all())
            ->whereIn('curso.codigo', array_column($filas, 'course'))
            ->whereIn('seccion.codigo', array_column($filas, 'section'))
            ->get(['seccion.id_seccion', 'seccion.codigo as seccion', 'plan.id_plan', 'curso.codigo as curso'])
            ->groupBy(fn (stdClass $s) => serialize([$s->id_plan, $s->curso, $s->seccion]));

        $detalles = $tipo === 'matriculas' ? collect() : $this->detalles($escuela, $periodo)
            ->where(function (Builder $q) use ($filas, $estudiantes, $secciones): void {
                $ids = collect($filas)->map(fn (array $fila) => $this->idDetalle($fila['registration'] ?? null))->filter()->unique()->all();
                $q->whereIn('detalle.id_detalle', $ids)->orWhere(function (Builder $tuplas) use ($estudiantes, $secciones): void {
                    $tuplas->whereIn('estudiante.id_estudiante', $estudiantes->flatten(1)->pluck('id_estudiante'))
                        ->whereIn('seccion.id_seccion', $secciones->flatten(1)->pluck('id_seccion'));
                });
            })
            ->get(['detalle.id_detalle', 'estudiante.id_estudiante', 'estudiante.codigo_universitario',
                'curso.codigo as curso', 'seccion.id_seccion', 'seccion.codigo as seccion']);
        $porDetalle = $detalles->keyBy('id_detalle');
        $porTupla = $detalles->groupBy(fn (stdClass $d) => serialize([$d->id_estudiante, $d->id_seccion]));
        $componentes = $tipo === 'notas' ? DB::table('gestion_matriculas.componente_evaluacion')
            ->whereIn('id_seccion', $detalles->pluck('id_seccion')->unique()->all())
            ->whereIn('nombre', array_column($filas, 'unit'))->get(['id_componente', 'id_seccion', 'nombre'])
            ->keyBy(fn (stdClass $c) => serialize([$c->id_seccion, $c->nombre])) : collect();

        $registros = [];
        $vistos = [];
        foreach ($filas as $linea => $fila) {
            $estudiante = $estudiantes->get($fila['student'] ?? '', collect());
            $seccion = $estudiante->count() === 1
                ? $secciones->get(serialize([$estudiante->first()->id_plan, $fila['course'] ?? null, $fila['section'] ?? null]), collect())
                : collect();
            $referencia = $fila['registration'] ?? '';
            $detalleId = $this->idDetalle($referencia);
            $coincidencias = $estudiante->count() === 1 && $seccion->count() === 1
                ? $porTupla->get(serialize([$estudiante->first()->id_estudiante, $seccion->first()->id_seccion]), collect())
                : collect();
            $detalle = str_starts_with($referencia, 'DETALLE:') ? $porDetalle->get($detalleId) : ($coincidencias->count() === 1 ? $coincidencias->first() : null);
            $error = null;
            if ($tipo === 'matriculas') {
                if ($estudiante->count() !== 1 || $seccion->count() !== 1) {
                    $error = 'El estudiante, el curso o la sección no se identifican de forma única en el ámbito seleccionado.';
                } else {
                    $fila['id_estudiante'] = $estudiante->first()->id_estudiante;
                    $fila['id_seccion'] = $seccion->first()->id_seccion;
                }
            } elseif ($detalle === null || ! $this->coincide($detalle, $fila)) {
                $error = 'La matrícula de la sección no coincide o no está disponible en este ámbito. Usa los tres códigos académicos o DETALLE:<id>.';
            } else {
                $fila['id_estudiante'] = $detalle->id_estudiante;
                $fila['id_seccion'] = $detalle->id_seccion;
                $fila['id_detalle'] = $detalle->id_detalle;
                if ($tipo === 'notas') {
                    $criterio = $componentes->get(serialize([$detalle->id_seccion, $fila['unit']]));
                    if ($criterio === null) {
                        $error = 'La evaluación recibida no corresponde a un componente registrado en la sección.';
                    } else {
                        $fila['id_componente'] = $criterio->id_componente;
                    }
                }
            }

            if ($error !== null) {
                $resultado['errores'][$linea] = [$error];

                continue;
            }
            $clave = serialize([$fila['id_estudiante'], $fila['id_seccion'], $fila['id_componente'] ?? null]);
            if (isset($vistos[$clave])) {
                $resultado['errores'][$linea] = ['La fila corresponde al mismo registro institucional que la fila '.$vistos[$clave].'.'];

                continue;
            }
            $vistos[$clave] = $linea;
            $registros[$linea] = $fila;
        }

        ksort($resultado['errores']);
        $resultado['registros'] = $registros;

        return $resultado;
    }

    private function coincide(stdClass $detalle, array $fila): bool
    {
        return (! isset($fila['student']) || $fila['student'] === $detalle->codigo_universitario)
            && (! isset($fila['course']) || $fila['course'] === $detalle->curso)
            && (! isset($fila['section']) || $fila['section'] === $detalle->seccion);
    }

    private function idDetalle(?string $referencia): ?int
    {
        if ($referencia === null || ! str_starts_with($referencia, 'DETALLE:')) {
            return null;
        }

        $id = filter_var(substr($referencia, 8), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    private function detalles(int $escuela, int $periodo): Builder
    {
        return DB::table('gestion_matriculas.matricula_detalle as detalle')
            ->join('gestion_matriculas.matricula as matricula', 'matricula.id_matricula', '=', 'detalle.id_matricula')
            ->join('core.estudiante as estudiante', 'estudiante.id_estudiante', '=', 'matricula.id_estudiante')
            ->join('core.seccion as seccion', 'seccion.id_seccion', '=', 'detalle.id_seccion')
            ->join('core.curso_plan as plan', 'plan.id_curso_plan', '=', 'seccion.id_curso_plan')
            ->join('core.plan_estudios as plan_escuela', 'plan_escuela.id_plan', '=', 'plan.id_plan')
            ->join('core.curso as curso', 'curso.id_curso', '=', 'plan.id_curso')
            ->where('matricula.estado', '<>', 'Anulada')
            ->where('estudiante.id_escuela', $escuela)->where('plan_escuela.id_escuela', $escuela)
            ->where('matricula.id_periodo', $periodo)->where('seccion.id_periodo', $periodo);
    }
}
