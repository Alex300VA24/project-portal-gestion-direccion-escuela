<?php

namespace App\Modules\SeguimientoEstudiante\Services\Nivelacion;

use App\Modules\SeguimientoEstudiante\Exceptions\CierreNivelacionPendiente;
use App\Modules\SeguimientoEstudiante\Exceptions\DocumentoNoDisponible;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

final class DocumentosNivelacionService
{
    private const PROGRAMAS = 'seguimiento_estudiante.programa_nivelacion';

    private const GRUPOS = 'seguimiento_estudiante.nivelacion_grupo';

    private const PARTICIPANTES = 'seguimiento_estudiante.nivelacion_participante';

    private const CIERRE = [
        'contexto' => 'Contexto', 'metodologia' => 'Metodología', 'ejecucion' => 'Ejecución',
        'logros' => 'Logros', 'analisis' => 'Análisis', 'conclusiones' => 'Conclusiones',
        'recomendaciones' => 'Recomendaciones', 'referencia' => 'Referencia del cierre',
    ];

    private function ahora(): string
    {
        return now('America/Lima')->toIso8601String();
    }

    private function publicados(): Builder
    {
        return DB::table(self::PROGRAMAS.' as programa')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'programa.id_periodo')
            ->leftJoin('core.escuela_profesional as escuela', 'escuela.id_escuela', '=', 'programa.id_escuela')
            ->where('programa.publicado_en', '<=', $this->ahora());
    }

    private function inscripcionPropia(int $persona): Builder
    {
        return $this->publicados()
            ->join(self::GRUPOS.' as grupo', 'grupo.id_programa', '=', 'programa.id_programa')
            ->join(self::PARTICIPANTES.' as participante', 'participante.id_grupo', '=', 'grupo.id_grupo')
            ->join('core.estudiante as estudiante', 'estudiante.id_estudiante', '=', 'participante.id_estudiante')
            ->join('core.persona as persona', 'persona.id_persona', '=', 'estudiante.id_persona')
            ->join('core.escuela_profesional as escuela_estudiante', 'escuela_estudiante.id_escuela', '=', 'estudiante.id_escuela')
            ->where('estudiante.id_persona', $persona)
            ->where(fn (Builder $ambito) => $ambito->whereNull('programa.id_escuela')
                ->orWhereColumn('programa.id_escuela', 'estudiante.id_escuela'));
    }

    private function personal(int $persona, int $inscripcion): stdClass
    {
        $registro = $this->inscripcionPropia($persona)->where('participante.id_participante', $inscripcion)->first([
            'programa.id_programa', 'programa.nombre', 'programa.descripcion', 'programa.estado', 'programa.fecha_inicio',
            'programa.fecha_fin', 'periodo.codigo as periodo', 'escuela_estudiante.nombre as escuela', 'grupo.id_grupo', 'grupo.codigo as grupo',
            'participante.estado as participacion', 'persona.nombres', 'persona.apellido_paterno', 'persona.apellido_materno',
            'estudiante.codigo_universitario as codigo',
        ]);
        throw_if($registro === null, ModelNotFoundException::class);

        return $registro;
    }

    private function coordinacion(int $persona): Builder
    {
        return DB::table('seguimiento_estudiante.coordinacion_ambito as ambito')
            ->where('ambito.id_persona', $persona)
            ->whereColumn('ambito.id_escuela', 'programa.id_escuela')
            ->whereColumn('ambito.id_periodo', 'programa.id_periodo');
    }

    private function docente(int $persona): Builder
    {
        return DB::table(self::GRUPOS.' as grupo')
            ->join('core.docente as docente', 'docente.id_docente', '=', 'grupo.id_docente')
            ->whereColumn('grupo.id_programa', 'programa.id_programa')
            ->where('docente.id_persona', $persona)->where('docente.estado', 'Activo');
    }

    private function programas(int $persona): Builder
    {
        return $this->publicados()->where(function (Builder $consulta) use ($persona): void {
            $consulta->whereExists($this->coordinacion($persona))->orWhereExists($this->docente($persona));
        });
    }

    public function listado(int $persona): LengthAwarePaginator
    {
        $listado = $this->programas($persona)
            ->select('programa.id_programa', 'programa.nombre', 'periodo.codigo as periodo',
                'programa.estado', 'programa.cierre_publicado_en', 'programa.documento_cierre')
            ->selectRaw('EXISTS ('.$this->coordinacion($persona)->toSql().') as coordina', [$persona])
            ->orderByDesc('programa.fecha_inicio')->orderBy('programa.id_programa')
            ->paginate(20)->through(function (stdClass $programa): stdClass {
                $programa->informe_disponible = $programa->coordina && $this->cierre($programa) !== null;
                unset($programa->documento_cierre);

                return $programa;
            });

        $materiales = $this->materialesDocumentales()
            ->whereIn('material.id_programa', $listado->getCollection()->where('coordina', true)->pluck('id_programa'))
            ->orderBy('material.titulo')->orderBy('material.id_archivo')
            ->get(['material.id_programa', 'material.id_archivo', 'material.titulo', 'material.visible_desde'])
            ->groupBy('id_programa');

        return $listado->through(function (stdClass $programa) use ($materiales): stdClass {
            $programa->materiales = $materiales->get($programa->id_programa, collect());

            return $programa;
        });
    }

    public function resumen(int $persona, int $inscripcion): array
    {
        $programa = $this->personal($persona, $inscripcion);
        $sesiones = $this->sesiones([$programa->id_grupo])
            ->leftJoin('seguimiento_estudiante.nivelacion_asistencia as asistencia', function (JoinClause $join) use ($inscripcion): void {
                $join->on('asistencia.id_sesion', '=', 'sesion.id_sesion')->where('asistencia.id_participante', $inscripcion);
            })
            ->addSelect('asistencia.estado as asistencia')->get();
        $notas = DB::table('seguimiento_estudiante.nivelacion_calificacion as nota')
            ->join('seguimiento_estudiante.nivelacion_criterio_evaluacion as criterio', 'criterio.id_criterio', '=', 'nota.id_criterio')
            ->where('criterio.id_programa', $programa->id_programa)
            ->where('nota.id_participante', $inscripcion)
            ->where('nota.publicada_en', '<=', $this->ahora())
            ->orderBy('criterio.nombre')
            ->get(['criterio.nombre', 'criterio.peso', 'nota.nota', 'nota.publicada_en']);

        return compact('programa', 'sesiones', 'notas');
    }

    public function ficha(int $persona, int $id, string $tipo): array
    {
        $consulta = $tipo === 'informe'
            ? $this->publicados()->whereExists($this->coordinacion($persona))
            : $this->programas($persona);
        $programa = $consulta->where('programa.id_programa', $id)
            ->select('programa.*', 'periodo.codigo as periodo', 'escuela.nombre as escuela')
            ->selectRaw('EXISTS ('.$this->coordinacion($persona)->toSql().') as coordina', [$persona])
            ->first();
        throw_if($programa === null, ModelNotFoundException::class);

        if ($tipo === 'informe') {
            $cierre = $this->cierre($programa);
            throw_if($cierre === null, CierreNivelacionPendiente::class, 'El informe final requiere un cierre documental publicado.');

            return compact('programa', 'cierre');
        }

        $grupos = DB::table(self::GRUPOS.' as grupo')
            ->join('core.docente as docente', 'docente.id_docente', '=', 'grupo.id_docente')
            ->join('core.persona as persona', 'persona.id_persona', '=', 'docente.id_persona')
            ->where('grupo.id_programa', $id)
            ->when(! $programa->coordina, fn (Builder $grupos) => $grupos->where('docente.id_persona', $persona))
            ->orderBy('grupo.codigo')
            ->get(['grupo.id_grupo', 'grupo.codigo', 'grupo.capacidad', 'persona.nombres', 'persona.apellido_paterno', 'persona.apellido_materno']);
        $sesiones = $this->sesiones($grupos->pluck('id_grupo')->all())->get();
        $criterios = DB::table('seguimiento_estudiante.nivelacion_criterio_evaluacion')
            ->where('id_programa', $id)->orderBy('nombre')->get(['nombre', 'peso']);

        return compact('programa', 'grupos', 'sesiones', 'criterios');
    }

    public function materiales(int $persona, array $inscripciones): Builder
    {
        return $this->inscripcionPropia($persona)
            ->join('seguimiento_estudiante.nivelacion_material as material', 'material.id_programa', '=', 'programa.id_programa')
            ->join('comun.archivo as archivo', 'archivo.id_archivo', '=', 'material.id_archivo')
            ->join('seguridad.modulo_sistema as modulo', 'modulo.id_modulo', '=', 'archivo.id_modulo')
            ->whereIn('participante.id_participante', $inscripciones)
            ->where('modulo.codigo', 'seguimiento_estudiante')
            ->where('material.visible_desde', '<=', $this->ahora())
            ->where('archivo.tipo_mime', 'application/pdf');
    }

    private function materialesDocumentales(): Builder
    {
        return DB::table('seguimiento_estudiante.nivelacion_material as material')
            ->join('comun.archivo as archivo', 'archivo.id_archivo', '=', 'material.id_archivo')
            ->join('seguridad.modulo_sistema as modulo', 'modulo.id_modulo', '=', 'archivo.id_modulo')
            ->where('modulo.codigo', 'seguimiento_estudiante')
            ->where('archivo.tipo_mime', 'application/pdf');
    }

    public function rutaMaterialPublicado(int $persona, int $inscripcion, int $archivo): ?string
    {
        return $this->materiales($persona, [$inscripcion])
            ->where('material.id_archivo', $archivo)->value('archivo.ruta_almacenamiento');
    }

    public function rutaMaterialEnGestion(int $persona, int $programa, int $archivo): ?string
    {
        return $this->materialEnGestion($persona, $programa, $archivo)->value('archivo.ruta_almacenamiento');
    }

    private function materialEnGestion(int $persona, int $programa, int $archivo): Builder
    {
        $ambito = $this->publicados()->whereExists($this->coordinacion($persona))
            ->whereColumn('programa.id_programa', 'material.id_programa');

        return $this->materialesDocumentales()->where('material.id_programa', $programa)
            ->where('material.id_archivo', $archivo)->whereExists($ambito);
    }

    public function publicarMaterial(int $persona, int $programa, int $archivo, bool $visible): void
    {
        $material = $this->materialEnGestion($persona, $programa, $archivo)->select('material.id_archivo');
        $destino = DB::table('seguimiento_estudiante.nivelacion_material')->where('id_programa', $programa)
            ->whereIn('id_archivo', $material);
        $cambio = (clone $destino)->whereNull('visible_desde', not: ! $visible)
            ->update(['visible_desde' => $visible ? $this->ahora() : null]);
        throw_if($cambio === 0 && ! $destino->exists(), ModelNotFoundException::class);
    }

    public function adjuntar(int $persona, int $id, string $contenido, string $nombreOriginal, string $titulo, bool $visible): void
    {
        $programa = $this->publicados()->whereExists($this->coordinacion($persona))->where('programa.id_programa', $id);
        throw_unless((clone $programa)->exists(), ModelNotFoundException::class);

        if (! str_starts_with($contenido, '%PDF-')) {
            throw ValidationException::withMessages(['archivo' => 'Adjunta un archivo PDF válido.']);
        }
        $ruta = 'seguimiento-estudiante/nivelacion/'.Str::uuid().'.pdf';
        $disco = Storage::disk('local');

        try {
            DB::transaction(function () use ($programa, $persona, $id, $ruta, $nombreOriginal, $contenido, $titulo, $visible, $disco): void {
                throw_if((clone $programa)->lock('for update of programa')->first(['programa.id_programa']) === null, ModelNotFoundException::class);
                $usuario = Usuario::query()->where('id_persona', $persona)->where('estado', 'Activo')
                    ->sharedLock()->value('id_usuario');
                throw_if($usuario === null, AuthorizationException::class, 'La carga requiere una identidad activa en el registro institucional de archivos.');
                $modulo = DB::table('seguridad.modulo_sistema')->where('codigo', 'seguimiento_estudiante')->value('id_modulo');
                throw_if($modulo === null, DocumentoNoDisponible::class, 'No se encontró el módulo en el catálogo documental.');

                throw_unless($disco->put($ruta, $contenido, 'private'), DocumentoNoDisponible::class, 'No se pudo guardar el material de Nivelación.');
                $idArchivo = DB::table('comun.archivo')->insertGetId([
                    'id_modulo' => $modulo,
                    'nombre_original' => mb_substr($nombreOriginal, 0, 255),
                    'ruta_almacenamiento' => $ruta,
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => strlen($contenido),
                    'hash_sha256' => hash('sha256', $contenido),
                    'subido_por' => $usuario,
                ], 'id_archivo');
                DB::table('seguimiento_estudiante.nivelacion_material')->insert([
                    'id_programa' => $id,
                    'id_archivo' => $idArchivo,
                    'titulo' => $titulo,
                    'visible_desde' => $visible ? $this->ahora() : null,
                ]);
            });
        } catch (Throwable $error) {
            try {
                $disco->delete($ruta);
            } catch (Throwable $falloLimpieza) {
                report($falloLimpieza);
            }

            throw $error;
        }
    }

    private function sesiones(array $grupos): Builder
    {
        return DB::table('seguimiento_estudiante.nivelacion_sesion as sesion')
            ->join(self::GRUPOS.' as grupo', 'grupo.id_grupo', '=', 'sesion.id_grupo')
            ->leftJoin('core.ambiente as ambiente', 'ambiente.id_ambiente', '=', 'sesion.id_ambiente')
            ->whereIn('sesion.id_grupo', $grupos)
            ->orderBy('sesion.fecha')->orderBy('sesion.id_sesion')
            ->select('sesion.fecha', 'sesion.tema', 'grupo.codigo as grupo', 'ambiente.nombre as ambiente');
    }

    private function cierre(stdClass $programa): ?array
    {
        if (! in_array(mb_strtoupper($programa->estado), ['CERRADO', 'FINALIZADO'], true)
            || $programa->cierre_publicado_en === null
            || ! now()->gte(Carbon::parse($programa->cierre_publicado_en))) {
            return null;
        }

        $datos = json_decode($programa->documento_cierre ?? '{}', true);
        $secciones = [];
        foreach (self::CIERRE as $campo => $titulo) {
            if (! is_string($datos[$campo] ?? null) || trim($datos[$campo]) === '') {
                return null;
            }
            $secciones[$titulo] = $datos[$campo];
        }

        return $secciones;
    }
}
