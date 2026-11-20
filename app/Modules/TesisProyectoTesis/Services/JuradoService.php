<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jurado: asignación y reemplazo (Director), verificación de cada miembro, dictamen y paso a Tesis
 * (actividades 13 a 17, 21, 22, 26 a 28).
 */
final class JuradoService
{
    public const EVENTO_INTERMEDIA = 'jurado.revision_intermedia';

    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly CatalogoService $catalogo,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
        private readonly RubricaService $rubrica,
        private readonly ActaService $actas,
        private readonly SeguimientoService $seguimiento,
    ) {}

    /**
     * Docentes que pueden ser jurado: activos, distintos del asesor, ordenados por menor carga.
     *
     * @return list<array{codigo: string, nombre: string, categoria: ?string, carga: int, especialista: bool}>
     */
    public function candidatos(Proyecto $proyecto): array
    {
        $asesor = $proyecto->asesorActivo()->value('id_docente');
        $carga = JuradoProyecto::query()->where('estado', 'Activo')->selectRaw('id_docente, COUNT(*) AS n')->groupBy('id_docente')->pluck('n', 'id_docente');

        $lista = [];
        foreach ($this->identidad->docentesActivos() as $d) {
            if ($d['id'] === (int) $asesor) {
                continue;
            }
            $lista[] = [
                'codigo' => $d['codigo'], 'nombre' => $d['nombre'], 'categoria' => $d['categoria'],
                'carga' => (int) ($carga[$d['id']] ?? 0), 'especialista' => in_array($proyecto->id_linea, $d['lineas'], true),
            ];
        }
        usort($lista, fn ($a, $b) => [$a['carga'], ! $a['especialista'], $a['nombre']] <=> [$b['carga'], ! $b['especialista'], $b['nombre']]);

        return $lista;
    }

    /**
     * @param  array<string, string>  $cargos  Presidente|Secretario|Vocal => codigo_docente
     */
    public function asignar(Proyecto $proyecto, Identidad $director, array $cargos): void
    {
        $ids = $this->resolverDocentes($proyecto, $cargos);

        DB::transaction(function () use ($proyecto, $director, $ids) {
            $p = $this->historial->bloquear($proyecto);

            if ($p->estado !== Estados::VERIFICADO_ASESOR) {
                throw ValidationException::withMessages(['Presidente' => 'El proyecto no espera asignación de jurado (requiere la conformidad del asesor).']);
            }
            if ($p->juradosActivos()->exists()) {
                throw ValidationException::withMessages(['Presidente' => 'El proyecto ya tiene jurado. Usa «Reemplazar» si hay un inconveniente.']);
            }
            if (JuradoProyecto::query()->where('id_proyecto', $p->id_proyecto)->whereIn('id_docente', array_values($ids))->exists()) {
                throw ValidationException::withMessages(['Presidente' => 'Uno de los docentes ya integró este jurado anteriormente.']);
            }

            foreach ($ids as $cargo => $idDocente) {
                JuradoProyecto::create([
                    'id_proyecto' => $p->id_proyecto, 'id_docente' => $idDocente, 'cargo' => $cargo, 'estado' => 'Activo',
                    'asignado_por' => $director->idUsuario, 'fecha_asignacion' => Carbon::today(),
                ]);
            }

            $etapa = $p->enEtapaTesis() ? 'tesis' : 'proyecto';
            $this->historial->cambiarEstado($p, Estados::EN_REVISION_JURADO, $director->idUsuario, 'Jurado asignado: '.implode(', ', array_map(
                fn ($c, $id) => $c.' '.$this->identidad->nombreDocente($id), array_keys($ids), $ids)).'.');

            $this->notificaciones->notificar($this->identidad->usuariosDeDocentes(array_values($ids)), 'jurado.asignado',
                "Fuiste asignado como jurado · {$p->codigo}", "Tienes un {$etapa} por revisar con la rúbrica: {$p->titulo}", $p);
            $this->notificaciones->notificar(
                [...$this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all()),
                    ...$this->identidad->usuariosDeDocentes(array_filter([$p->asesorActivo()->value('id_docente')]))],
                'jurado.asignado', 'Se asignó el jurado', "El Director asignó el jurado del {$etapa} {$p->codigo}.", $p);
        });
    }

    /** Reemplaza al miembro de un cargo (licencia, renuncia u otro motivo). El historial queda guardado. */
    public function reemplazar(Proyecto $proyecto, Identidad $director, string $cargo, string $codigo, string $motivo): void
    {
        $nuevo = $this->identidad->idDocentePorCodigo($codigo);
        if ($nuevo === null) {
            throw ValidationException::withMessages(['docente' => 'El docente no está activo.']);
        }

        DB::transaction(function () use ($proyecto, $director, $cargo, $nuevo, $motivo) {
            $p = $this->historial->bloquear($proyecto);

            if (in_array($p->estado, [Estados::PROGRAMADO, Estados::SUSTENTADO, Estados::TESIS_APROBADA], true)) {
                throw ValidationException::withMessages(['docente' => 'No se puede cambiar el jurado con la sustentación programada o concluida.']);
            }
            $actual = JuradoProyecto::query()->where('id_proyecto', $p->id_proyecto)->where('cargo', $cargo)->where('estado', 'Activo')->lockForUpdate()->first();
            if ($actual === null) {
                throw ValidationException::withMessages(['docente' => 'No hay un miembro activo en ese cargo.']);
            }
            if ($nuevo === (int) $p->asesorActivo()->value('id_docente')) {
                throw ValidationException::withMessages(['docente' => 'El asesor no puede ser jurado de su propio asesorado.']);
            }
            if (JuradoProyecto::query()->where('id_proyecto', $p->id_proyecto)->where('id_docente', $nuevo)->exists()) {
                throw ValidationException::withMessages(['docente' => 'Ese docente ya integra (o integró) este jurado.']);
            }

            $actual->update(['estado' => 'Reemplazado', 'fecha_fin' => Carbon::today(), 'motivo_cambio' => $motivo]);
            JuradoProyecto::create([
                'id_proyecto' => $p->id_proyecto, 'id_docente' => $nuevo, 'cargo' => $cargo, 'estado' => 'Activo',
                'asignado_por' => $director->idUsuario, 'fecha_asignacion' => Carbon::today(), 'motivo_cambio' => $motivo,
            ]);

            $this->notificaciones->notificar($this->identidad->usuariosDeDocentes([$nuevo]), 'jurado.asignado',
                "Fuiste asignado como {$cargo} del jurado · {$p->codigo}", "Reemplazo: {$motivo}", $p);
            $this->notificaciones->notificar($this->identidad->usuariosDeDocentes([(int) $actual->id_docente]), 'jurado.reemplazado',
                "Dejaste de integrar el jurado · {$p->codigo}", "Motivo: {$motivo}", $p);
        });
    }

    /**
     * Estado de cada miembro en la etapa actual: sin iniciar, con observaciones o conforme (sin mostrar el detalle).
     *
     * @return list<array{cargo: string, nombre: string, id_docente: int, estado: string, cumple: int, total: int}>
     */
    public function avanceMiembros(Proyecto $proyecto): array
    {
        $total = $this->rubrica->criterios($proyecto->etapa)->count();
        $ficha = $this->seguimiento->ficha($proyecto);

        return array_map(function ($j) use ($proyecto, $total) {
            $ultima = $this->rubrica->ultimaVerificacion($proyecto, (int) $j['id_docente']);
            $resumen = $this->rubrica->resumen($ultima, $total);

            return [
                ...$j,
                'estado' => $ultima === null ? 'Sin iniciar' : ($ultima->conforme ? 'Conforme' : 'Con observaciones'),
                'cumple' => $resumen['cumple'],
                'total' => $total,
            ];
        }, $ficha['jurado']);
    }

    public function esConforme(Proyecto $proyecto, int $idDocente): bool
    {
        return (bool) $this->rubrica->ultimaVerificacion($proyecto, $idDocente)?->conforme;
    }

    /**
     * Verificación de un miembro del jurado. Al completarse las 3 conformidades:
     * Proyecto → dictamen, Aprobado y paso a Tesis; Tesis → listo para sustentar.
     *
     * @param  array{marcas: array, obs?: array, accion: string, comentario?: ?string}  $datos
     */
    public function verificar(Proyecto $proyecto, Identidad $jurado, array $datos): Revision
    {
        $modo = $this->modo($proyecto, (int) $jurado->idDocente);

        if ($modo === null) {
            throw ValidationException::withMessages(['marcas' => 'No tienes una revisión pendiente en este proyecto.']);
        }

        $entrega = $modo === 'Intermedia' ? $this->ultimaEntrega($proyecto) : $this->rubrica->entregaObjetivo($proyecto);
        if ($entrega === null) {
            throw ValidationException::withMessages(['marcas' => 'No hay una versión del documento para revisar.']);
        }

        return DB::transaction(function () use ($proyecto, $jurado, $datos, $modo, $entrega) {
            $revision = $this->rubrica->verificar($proyecto, $entrega, $jurado, 'Jurado', $modo, $datos['marcas'], $datos['obs'] ?? [],
                $modo === 'Verificacion' && $datos['accion'] === 'conformidad', $datos['comentario'] ?? null);

            if ($modo === 'Verificacion' && $revision->conforme) {
                $this->consolidar($proyecto, $jurado);
            }

            return $revision;
        });
    }

    /**
     * Qué puede hacer el jurado ahora: 'Verificacion' (revisión del proyecto o final de la tesis),
     * 'Intermedia' (semana 8 de la etapa Tesis, una sola vez, referencial) o null.
     */
    public function modo(Proyecto $proyecto, int $idDocente): ?string
    {
        if ($proyecto->estado === Estados::EN_REVISION_JURADO) {
            return $this->esConforme($proyecto, $idDocente) ? null : 'Verificacion';
        }

        if ($proyecto->estado === Estados::EN_TESIS && $this->intermediaHabilitada($proyecto)
            && $this->rubrica->ultimaVerificacion($proyecto, $idDocente, 'Intermedia') === null
            && $this->ultimaEntrega($proyecto) !== null) {
            return 'Intermedia';
        }

        return null;
    }

    public function intermediaHabilitada(Proyecto $proyecto): bool
    {
        return $proyecto->enEtapaTesis() && $this->seguimiento->semanaActual($proyecto) >= Parametros::SEMANA_REVISION_INTERMEDIA;
    }

    /** Aviso automático al jurado al llegar la semana de revisión intermedia (actividad 21). Una sola vez. */
    public function notificarIntermediaSiCorresponde(Proyecto $proyecto): bool
    {
        if ($proyecto->estado !== Estados::EN_TESIS || ! $this->intermediaHabilitada($proyecto)
            || $this->notificaciones->yaNotificado($proyecto, self::EVENTO_INTERMEDIA)) {
            return false;
        }

        $this->notificaciones->notificar($this->identidad->usuariosDeDocentes($proyecto->juradosActivos()->pluck('id_docente')->all()),
            self::EVENTO_INTERMEDIA, "Revisión intermedia · {$proyecto->codigo}",
            'La tesis llegó a la semana '.Parametros::SEMANA_REVISION_INTERMEDIA.'. Deja tus observaciones con la rúbrica como referencia.', $proyecto);

        return true;
    }

    public function ultimaEntrega(Proyecto $proyecto): ?Entrega
    {
        return Entrega::query()->where('id_proyecto', $proyecto->id_proyecto)->where('etapa', $proyecto->etapa)->orderByDesc('version')->first();
    }

    /** Proyectos donde el docente es jurado activo, con su situación. */
    public function bandeja(int $idDocente): Collection
    {
        $ids = JuradoProyecto::query()->where('id_docente', $idDocente)->where('estado', 'Activo')->pluck('cargo', 'id_proyecto');

        return Proyecto::query()->whereIn('id_proyecto', $ids->keys())->orderByDesc('registrado_en')
            ->get(['id_proyecto', 'uuid', 'codigo', 'titulo', 'etapa', 'estado', 'id_linea', 'id_periodo_tesis'])
            ->map(function ($p) use ($ids, $idDocente) {
                $this->notificarIntermediaSiCorresponde($p);

                return ['proyecto' => $p, 'cargo' => $ids[$p->id_proyecto], 'modo' => $this->modo($p, $idDocente), 'conforme' => $this->esConforme($p, $idDocente)];
            });
    }

    private function consolidar(Proyecto $proyecto, Identidad $jurado): void
    {
        $p = $this->historial->bloquear($proyecto);
        $miembros = $p->juradosActivos()->pluck('id_docente');

        if ($miembros->count() !== Parametros::MIEMBROS_JURADO || $miembros->contains(fn ($id) => ! $this->esConforme($p, (int) $id))) {
            return;
        }

        $estudiantes = $this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all());
        $asesor = $this->identidad->usuariosDeDocentes(array_filter([$p->asesorActivo()->value('id_docente')]));
        $gestion = [...$this->identidad->usuariosConRol('director_escuela'), ...$this->identidad->usuariosConRol('secretaria')];

        if (! $p->enEtapaTesis()) {
            $dictamen = $this->actas->generarDictamen($p);
            $this->historial->cambiarEstado($p, Estados::APROBADO, $jurado->idUsuario, "Los 3 jurados conformes. Dictamen N.° {$dictamen->numero}.",
                ['fecha_aprobacion_proyecto' => Carbon::today()]);
            $this->historial->cambiarEstado($p, Estados::EN_TESIS, $jurado->idUsuario, 'Pasa a la etapa de Tesis (F2).',
                ['etapa' => Estados::ETAPA_TESIS, 'id_periodo_tesis' => $this->catalogo->periodoActual()?->id_periodo]);

            $this->notificaciones->notificar([...$estudiantes, ...$asesor], 'proyecto.aprobado', "Proyecto aprobado · {$p->codigo}",
                "Los tres jurados dieron su conformidad (Dictamen N.° {$dictamen->numero}). Empieza la etapa de Tesis (F2).", $p);
            $this->notificaciones->notificar($gestion, 'dictamen.generado', "Dictamen N.° {$dictamen->numero} · {$p->codigo}",
                'Imprime el dictamen para la firma de los tres jurados y sube el documento firmado.', $p);

            return;
        }

        $this->historial->cambiarEstado($p, Estados::LISTO_SUSTENTAR, $jurado->idUsuario, 'Los 3 jurados conformes en la revisión final de la tesis.');
        $this->notificaciones->notificar([...$estudiantes, ...$asesor], 'tesis.lista', "Tesis lista para sustentar · {$p->codigo}",
            'Los tres jurados dieron su conformidad. La Secretaría programará la sustentación.', $p);
        $this->notificaciones->notificar($gestion, 'tesis.lista', "Programar sustentación · {$p->codigo}", 'La tesis tiene la conformidad de los tres jurados.', $p);
    }

    /**
     * @param  array<string, string>  $cargos
     * @return array<string, int>
     */
    private function resolverDocentes(Proyecto $proyecto, array $cargos): array
    {
        $ids = [];
        foreach (Parametros::CARGOS_JURADO as $cargo) {
            $id = $this->identidad->idDocentePorCodigo($cargos[$cargo] ?? null);
            if ($id === null) {
                throw ValidationException::withMessages([$cargo => "Elige un docente activo como {$cargo}."]);
            }
            $ids[$cargo] = $id;
        }

        if (count(array_unique($ids)) !== Parametros::MIEMBROS_JURADO) {
            throw ValidationException::withMessages(['Presidente' => 'Los tres miembros del jurado deben ser docentes distintos.']);
        }
        if (in_array((int) $proyecto->asesorActivo()->value('id_docente'), $ids, true)) {
            throw ValidationException::withMessages(['Presidente' => 'El asesor no puede ser jurado de su propio asesorado.']);
        }

        return $ids;
    }
}
