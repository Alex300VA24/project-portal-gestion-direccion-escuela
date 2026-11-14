<?php

namespace App\Modules\Cotecu\Services;

use App\Modules\Cotecu\Models\Registro;
use App\Modules\Cotecu\Models\Revision;
use App\Modules\Cotecu\Models\Silabo;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\Core\PlanEstudios;
use App\Shared\Models\Core\Seccion;
use App\Shared\Models\Seguridad\Usuario;
use App\Shared\Services\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CotecuService extends ModuleService
{
    /**
     * Todos los periodos académicos, del más reciente al más antiguo.
     *
     * @return Collection<int, PeriodoAcademico>
     */
    public function periodos(): Collection
    {
        return PeriodoAcademico::query()
            ->orderByDesc('anio')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Periodo Activo, si existe: el único en el que se puede subir un
     * sílabo.
     */
    public function periodoActivo(): ?PeriodoAcademico
    {
        return PeriodoAcademico::query()
            ->where('estado', 'Activo')
            ->orderByDesc('fecha_inicio')
            ->first();
    }

    /**
     * Periodo de contexto de las pantallas: el Activo; si no hay, el más
     * reciente. Así la vista nunca queda sin semestre que mostrar.
     */
    public function periodoActual(): ?PeriodoAcademico
    {
        return $this->periodoActivo() ?? $this->periodos()->first();
    }

    /**
     * Solo un periodo Activo habilita la subida de sílabos.
     */
    public function hayPeriodoActivo(): bool
    {
        return $this->periodoActivo() !== null;
    }

    /**
     * id_usuario en seguridad.usuario de la cuenta con sesión iniciada.
     *
     * Puente: public.users.email ↔ seguridad.usuario.correo_institucional.
     * Devuelve null cuando la cuenta no tiene fila institucional: la
     * subida debe avisar en vez de reventar con una violación de FK.
     */
    public function idUsuarioActual(): ?int
    {
        $email = auth()->user()?->email;

        if ($email === null) {
            return null;
        }

        $id = Usuario::query()->where('correo_institucional', $email)->value('id_usuario');

        return $id === null ? null : (int) $id;
    }

    /**
     * Documentos del plan curricular visibles para la cuenta en sesión.
     *
     * Regla de visibilidad de COTECU: quien sube o revisa (plan.upload /
     * plan.review) ve todos los estados; quien solo lista ve únicamente
     * los Validado.
     *
     * @return Collection<int, Registro>
     */
    public function planes(): Collection
    {
        $query = Registro::query()
            ->join('core.plan_estudios', 'core.plan_estudios.id_plan', '=', 'cotecu.planes_curriculares.id_plan')
            ->select(
                'cotecu.planes_curriculares.*',
                'core.plan_estudios.nombre as plan_nombre',
                'core.plan_estudios.codigo as plan_codigo',
            )
            ->orderByDesc('cotecu.planes_curriculares.version');

        if (! $this->vePlanesEnProceso()) {
            $query->where('cotecu.planes_curriculares.estado', 'Validado');
        }

        return $query->get();
    }

    /**
     * La cuenta participa del proceso de planes (sube o revisa): por eso
     * ve los planes en cualquier estado.
     */
    public function vePlanesEnProceso(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('cotecu.plan.upload') || $user->can('cotecu.plan.review'));
    }

    /**
     * Catálogo de planes de estudios para el selector del formulario.
     *
     * @return Collection<int, PlanEstudios>
     */
    public function planesEstudio(): Collection
    {
        return PlanEstudios::query()
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Registra un documento del plan curricular en estado Pendiente.
     *
     * La subida de archivo está simulada en esta versión de COTECU: no se
     * recibe binario, solo se guarda una ruta por defecto en
     * storage/app/private/ como archivo_url.
     *
     * @param  array{codigo: string, nombre: string}  $plan
     */
    public function registrarPlanCurricular(int $idPlan, string $codigo, string $nombre, int $version): Registro
    {
        $archivo = 'storage/app/private/cotecu/planes/'
            . Str::lower($codigo) . '-v' . $version . '.pdf';

        return Registro::query()->create([
            'id_plan' => $idPlan,
            'nombre' => $nombre,
            'version' => $version,
            'archivo_url' => $archivo,
            'estado' => 'Pendiente',
        ]);
    }

    /**
     * id_docente en core.docente de la cuenta en sesión.
     *
     * Sigue el puente institucional: seguridad.usuario → id_persona →
     * core.docente. Devuelve null cuando la cuenta no es docente: sus
     * secciones (y por tanto su selector) quedan vacías.
     */
    public function idDocenteActual(): ?int
    {
        $idUsuario = $this->idUsuarioActual();

        if ($idUsuario === null) {
            return null;
        }

        $idPersona = Usuario::query()->where('id_usuario', $idUsuario)->value('id_persona');

        if ($idPersona === null) {
            return null;
        }

        $idDocente = DB::table('core.docente')->where('id_persona', $idPersona)->value('id_docente');

        return $idDocente === null ? null : (int) $idDocente;
    }

    /**
     * Secciones asignadas a la cuenta en sesión en un periodo: son las
     * únicas que puede elegir al subir un sílabo, con los datos del
     * curso listos para el selector.
     *
     * @return Collection<int, Seccion>
     */
    public function seccionesDelProfesor(?int $idPeriodo): Collection
    {
        $idDocente = $this->idDocenteActual();

        if ($idDocente === null || $idPeriodo === null) {
            return new Collection();
        }

        return Seccion::query()
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'core.seccion.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('core.seccion.id_docente_responsable', $idDocente)
            ->where('core.seccion.id_periodo', $idPeriodo)
            ->orderBy('c.codigo')
            ->select(
                'core.seccion.id_seccion',
                'core.seccion.id_curso_plan',
                'core.seccion.codigo as seccion_codigo',
                'cp.ciclo',
                'c.codigo as curso_codigo',
                'c.nombre as curso_nombre',
            )
            ->get();
    }

    /**
     * Sílabos de la cuenta en sesión en un periodo, con los datos del
     * curso y el ciclo para listarlos.
     *
     * @return Collection<int, Silabo>
     */
    public function misSilabos(?int $idPeriodo): Collection
    {
        $idUsuario = $this->idUsuarioActual();

        if ($idUsuario === null || $idPeriodo === null) {
            return new Collection();
        }

        return Silabo::query()
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'cotecu.silabos.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('cotecu.silabos.id_profesor', $idUsuario)
            ->where('cotecu.silabos.id_periodo', $idPeriodo)
            ->orderBy('c.codigo')
            ->select(
                'cotecu.silabos.id',
                'cotecu.silabos.version',
                'cotecu.silabos.estado',
                'cotecu.silabos.archivo_url',
                'cp.ciclo',
                'c.codigo as curso_codigo',
                'c.nombre as curso_nombre',
            )
            ->get();
    }

    /**
     * Registra un sílabo del profesor en estado Pendiente.
     *
     * La subida de archivo está simulada en esta versión de COTECU: no
     * se recibe binario, solo se guarda una ruta por defecto en
     * storage/app/private/ como archivo_url.
     */
    public function registrarSilabo(int $idCursoPlan, int $idPeriodo, int $idProfesor, int $version): Silabo
    {
        $cursoCodigo = DB::table('core.curso_plan')
            ->join('core.curso', 'core.curso.id_curso', '=', 'core.curso_plan.id_curso')
            ->where('core.curso_plan.id_curso_plan', $idCursoPlan)
            ->value('core.curso.codigo');

        $periodoCodigo = DB::table('core.periodo_academico')
            ->where('id_periodo', $idPeriodo)
            ->value('codigo');

        $archivo = 'storage/app/private/cotecu/silabos/'
            . Str::lower((string) $cursoCodigo) . '-' . Str::lower((string) $periodoCodigo)
            . '-v' . $version . '.pdf';

        return Silabo::query()->create([
            'id_curso_plan' => $idCursoPlan,
            'id_periodo' => $idPeriodo,
            'id_profesor' => $idProfesor,
            'version' => $version,
            'archivo_url' => $archivo,
            'estado' => 'Pendiente',
        ]);
    }

    /**
     * Cola de revisión de sílabos: los registros de revisión de los
     * sílabos en estado Pendiente, con los datos del curso, el semestre
     * y el profesor que pide la pantalla Revisar sílabos.
     *
     * Backfill idempotente ("si es que no hay"): cada sílabo Pendiente
     * sin fila en cotecu.revisiones_silabo recibe una con la lista de
     * cotejo activa, el revisor = cuenta en sesión y estado_final
     * 'Pendiente'. Reabrir la pantalla no duplica registros: solo se
     * inserta si el sílabo todavía no tiene.
     *
     * Los sílabos Validado/Observado ya fueron revisados: no aparecen.
     * No hay filtro de semestre (un pendiente sigue pendiente aunque
     * cambie el semestre); cada fila muestra su chip de semestre.
     *
     * @return Collection<int, Revision>
     */
    public function colaRevisionSilabos(): Collection
    {
        $idRevisor = $this->idUsuarioActual();
        $idLista = DB::table('cotecu.listas_cotejo_silabos')
            ->where('estado', 'Activo')
            ->orderByDesc('id')
            ->value('id');

        if ($idRevisor !== null && $idLista !== null) {
            $pendientes = DB::table('cotecu.silabos')
                ->where('estado', 'Pendiente')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('cotecu.revisiones_silabo')
                        ->whereColumn('cotecu.revisiones_silabo.silabo_id', 'cotecu.silabos.id');
                })
                ->pluck('id');

            foreach ($pendientes as $idSilabo) {
                DB::table('cotecu.revisiones_silabo')->insert([
                    'silabo_id' => $idSilabo,
                    'lista_cotejo_silabo_id' => $idLista,
                    'id_revisor' => $idRevisor,
                    'estado_final' => 'Pendiente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $this->revisionesQuery()
            ->where('s.estado', 'Pendiente')
            ->orderByDesc('p.fecha_inicio')
            ->orderBy('c.codigo')
            ->get();
    }

    /**
     * Detalle de una revisión de sílabo para su pantalla de marcado:
     * la misma cruzada de la cola, renglón único. A diferencia de la
     * cola no filtra por estado, para que una revisión ya cerrada
     * pueda reabrirse en solo lectura.
     */
    public function detalleRevision(int $idRevision): ?Revision
    {
        return $this->revisionesQuery()
            ->where('cotecu.revisiones_silabo.id', $idRevision)
            ->first();
    }

    /**
     * Cruzada de la cola: registro ⋈ sílabo ⋈ curso ⋈ periodo ⋈
     * profesor (persona) con los datos que muestran las pantallas de
     * revisión.
     *
     * @return Builder<Revision>
     */
    private function revisionesQuery(): Builder
    {
        return Revision::query()
            ->join('cotecu.silabos as s', 's.id', '=', 'cotecu.revisiones_silabo.silabo_id')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->join('seguridad.usuario as u', 'u.id_usuario', '=', 's.id_profesor')
            ->leftJoin('core.persona as pe', 'pe.id_persona', '=', 'u.id_persona')
            ->select(
                'cotecu.revisiones_silabo.id',
                'cotecu.revisiones_silabo.estado_final',
                's.version',
                's.archivo_url',
                'cp.ciclo',
                'c.codigo as curso_codigo',
                'c.nombre as curso_nombre',
                'p.codigo as periodo_codigo',
                DB::raw("concat(pe.nombres, ' ', pe.apellido_paterno) as profesor_nombre"),
            );
    }

    /**
     * Ítems de la lista de cotejo de una revisión, en orden de marcado.
     *
     * @return SupportCollection<int, object>
     */
    public function itemsDeRevision(Revision $revision): SupportCollection
    {
        return DB::table('cotecu.lista_cotejo_silabos_items')
            ->where('lista_cotejo_silabo_id', $revision->lista_cotejo_silabo_id)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Marcado ya guardado de una revisión, indexado por ítem: es lo que
     * se pinta al reabrir una revisión cerrada (solo lectura).
     *
     * @return SupportCollection<int, object>
     */
    public function resultadosDeRevision(int $idRevision): SupportCollection
    {
        return DB::table('cotecu.resultados_silabos_items')
            ->where('revision_silabo_id', $idRevision)
            ->get()
            ->keyBy('item_silabo_id');
    }

    /**
     * Cierra la revisión: guarda el marcado de cada ítem en
     * cotecu.resultados_silabos_items y decide el estado final — todos
     * cumplen → Validado; al menos uno sin marcar → Observado —
     * aplicándolo también al sílabo para que salga de la cola.
     *
     * Regla del cierre: un ítem marcado (cumple) nunca guarda
     * observación (se limpia); un ítem sin marcar conserva la suya.
     *
     * @param  SupportCollection<int, object>  $items
     * @param  array<int, array{cumple: bool, observacion: string|null}>  $marcas
     */
    public function cerrarRevision(Revision $revision, SupportCollection $items, array $marcas): string
    {
        $todosCumplen = true;

        foreach ($items as $item) {
            if (! ($marcas[(int) $item->id]['cumple'] ?? false)) {
                $todosCumplen = false;
            }
        }

        $estadoFinal = $todosCumplen ? 'Validado' : 'Observado';

        DB::transaction(function () use ($revision, $items, $marcas, $estadoFinal) {
            foreach ($items as $item) {
                $marca = $marcas[(int) $item->id] ?? ['cumple' => false, 'observacion' => null];
                $cumple = $marca['cumple'];
                $observacion = $cumple ? null : $marca['observacion'];

                $actualizado = DB::table('cotecu.resultados_silabos_items')
                    ->where('revision_silabo_id', $revision->id)
                    ->where('item_silabo_id', $item->id)
                    ->update([
                        'cumple' => $cumple,
                        'observacion_item' => $observacion,
                        'updated_at' => now(),
                    ]);

                if ($actualizado === 0) {
                    DB::table('cotecu.resultados_silabos_items')->insert([
                        'revision_silabo_id' => $revision->id,
                        'item_silabo_id' => $item->id,
                        'cumple' => $cumple,
                        'observacion_item' => $observacion,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $revision->update(['estado_final' => $estadoFinal]);
            Silabo::query()->whereKey($revision->silabo_id)->update(['estado' => $estadoFinal]);
        });

        return $estadoFinal;
    }
}
