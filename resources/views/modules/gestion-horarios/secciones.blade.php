<div class="quality-page gh-ui gh-secciones" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    <style>
        .gh-secciones .quality-page-heading { display:block; }
        .gh-secciones .gh-curso { margin:.6rem 0; }
        .gh-secciones .gh-curso summary { cursor:pointer; padding:.8rem 1rem; }
        .gh-secciones .gh-curso summary small { margin-left:.5rem; }
        .gh-secciones .gh-curso th,.gh-secciones .gh-curso td { padding:.5rem; vertical-align:top; overflow-wrap:anywhere; }
        .gh-secciones .gh-curso td { border-top:1px solid var(--portal-border,#d7dcd5); }
        .gh-secciones select,.gh-secciones input { max-width:100%; }
        .gh-secciones label { min-width:0; max-width:100%; }
        @media (max-width:600px) {
            .gh-secciones .gh-curso summary small { display:block; margin:.25rem 0 0; }
            .gh-secciones .gh-curso td,.gh-secciones .gh-curso th { padding:.35rem .2rem; }
        }
    </style>
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Preparación académica', 'subtitulo'=>'Distribución de secciones', 'descripcion'=>'Planifique las plazas por curso, ciclo y periodo.'])
    @include('modules.gestion-horarios.workspace-navegacion', [
        'workspaceEtapa' => 'preparacion', 'workspaceSub' => 'secciones',
        'workspacePlan' => ($planValido ?? false) ? $plan : '',
        'workspacePeriodo' => ($planValido ?? false) && isset($periodos) && $periodos->contains('id_periodo', (int) $periodo) ? $periodo : '',
    ])
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudieron consultar las secciones. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de secciones"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Plan de estudios<br><select class="quality-select" wire:model.live="plan"><option value="">Seleccione un plan</option>@foreach ($planes as $item)<option value="{{ $item->id_plan }}">{{ $item->codigo }} · {{ $item->nombre }} · Escuela {{ $item->id_escuela }}</option>@endforeach</select></label>
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Ciclo<br><select class="quality-select" wire:model.live="ciclo" @disabled(! $planValido)><option value="">Todos</option>@foreach ($ciclos as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
            <label>Buscar curso o código de sección<br><input class="quality-select" type="search" maxlength="120" wire:model.live.debounce.400ms="buscar"></label>
            @if ($etiquetasSemana->isNotEmpty())
                <label>Semana por sección<br><select class="quality-select" wire:model.live="seccionSemana">
                    <option value="">Seleccione una sección</option>
                    @foreach ($etiquetasSemana as $etiqueta)<option value="{{ $etiqueta }}">{{ $etiqueta }}</option>@endforeach
                </select></label>
            @endif
            @if ($avisoPlan)<p role="status">{{ $avisoPlan }}</p>@endif
            @if (! $planValido)<p>Seleccione un plan de estudios; el periodo no determina automáticamente un plan.</p>@elseif ($ciclos->isEmpty())<p>El plan seleccionado no tiene ciclos registrados.</p>@endif
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if (! $asociacionDisponible)<p class="quality-alert" role="status">La vinculación por tipo de oferta está pendiente. Puede consultar las secciones por curso y periodo.</p>@endif
        @if ($asociacionDisponible)<p role="status">Se muestran asociaciones compatibles con el plan y periodo seleccionados. La conciliación de referencias inconsistentes está pendiente de autorización por escuela.</p>@endif
        @if ($periodoProtegido)<p role="status">Este periodo tiene horarios aprobados o publicados. Solicite una revisión para modificarlo.</p>@endif
        @if (! $escrituraDisponible)<p role="status">Para modificar asociaciones es necesario verificar la escuela autorizada y seleccionar un plan disponible.</p>@endif
        @if ($integridad && collect($integridad['asociaciones'])->contains(fn ($a) => $a['estado'] === 'VIGENTE' && $a['causas']))<p class="quality-alert" role="alert">Hay asociaciones vigentes con referencias inexistentes o datos incompatibles. Solicite una revisión; no se han corregido automáticamente.</p>@endif
        @if ($mensajeAsociacion)<p role="status" aria-live="polite">{{ $mensajeAsociacion }}</p>@endif
        @error('asociacion')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
        @if (! $planValido)
            <div class="quality-empty"><h2>Seleccione un plan autorizado</h2><p>Los periodos se muestran cuando tienen datos vinculados al plan.</p></div>
        @elseif ($periodos->isEmpty())
            <div class="quality-empty"><h2>No hay periodos vinculados</h2><p>No se encontraron ofertas, secciones ni demanda para este plan.</p></div>
        @elseif ($periodo === '')
            <div class="quality-empty"><h2>Seleccione un periodo</h2><p>Elija un periodo para consultar sus secciones.</p></div>
        @elseif ($cursos->isEmpty())
            <div class="quality-empty"><h2>No se encontraron cursos ni secciones</h2><p>Revise el periodo o los filtros seleccionados.</p></div>
        @else
            <p><a href="{{ route('gestion-horarios.oferta', ['plan' => $plan, 'periodo' => $periodo]) }}">Ver oferta del plan</a></p>
            <p>Los totales de demanda visibles corresponden a cursos del plan seleccionado y no acreditan matrícula.</p>
            <section class="quality-panel" style="padding:1rem;margin:1rem 0" aria-labelledby="gh-propuesta-secciones">
                <h2 id="gh-propuesta-secciones">Propuesta de capacidad por demanda</h2>
                <p>Indique una capacidad objetivo para cada sección adicional. Se conservan las secciones activas existentes. Revise una propuesta y confirme su creación para cursos regulares sin ambigüedad. Los cursos de repetición y grupos especiales requieren planificación manual.</p>
                <label for="gh-capacidad-objetivo">Plazas por nueva sección</label>
                <input id="gh-capacidad-objetivo" class="quality-select" type="number" min="1" max="2147483647" step="1" wire:model.live.debounce.500ms="capacidadObjetivo" placeholder="Ej. 40">
                @error('capacidadObjetivo')<p role="alert" class="quality-alert">{{ $message }}</p>@enderror
                <p wire:loading wire:target="capacidadObjetivo,prepararPropuesta,aplicarPropuesta" role="status">Procesando propuesta…</p>
                @error('propuesta')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
                @if ($propuestaPendiente)
                    <div class="quality-panel" style="padding:1rem;margin-top:1rem" aria-label="Confirmación de secciones">
                        <h3>{{ $propuestaPendiente['nombre'] }}</h3>
                        <p>Demanda vigente (versión {{ $propuestaPendiente['version'] }}): {{ $propuestaPendiente['total'] }}. Plazas activas: {{ $propuestaPendiente['plazas'] }}.</p>
                        @if ($propuestaPendiente['codigos'])
                            <p>Se crearán: {{ implode(', ', $propuestaPendiente['codigos']) }} · {{ $propuestaPendiente['capacidad'] }} plazas cada una.</p>
                            <p>No se asignarán estudiantes, docentes ni franjas. {{ $propuestaPendiente['asociacion'] ? 'Se asociarán a la oferta regular.' : 'La asociación institucional queda pendiente.' }}</p>
                            <button type="button" class="institutional-primary" wire:click="aplicarPropuesta" wire:loading.attr="disabled" wire:confirm="¿Crear las secciones indicadas?">Confirmar creación</button>
                        @else
                            <p>No se requieren secciones adicionales.</p>
                        @endif
                        <button type="button" class="institutional-secondary" wire:click="cancelarPropuesta">Cerrar propuesta</button>
                    </div>
                @endif
            </section>
            @if ($semana !== null)
                <section class="quality-panel" style="padding:1rem;margin:1rem 0" aria-labelledby="gh-semana-plan">
                    <h2 id="gh-semana-plan">Actividades registradas · Ciclo {{ $ciclo }} · Sección {{ $seccionSemana }}</h2>
                    @if ($semana->isEmpty())
                        <p role="status">Todavía no hay actividades programadas para esta sección.</p>
                    @else
                        @include('modules.gestion-horarios.cuadricula-semanal', ['sesiones' => $semana, 'planificacion' => true])
                        <button type="button" class="institutional-secondary" wire:click="$toggle('verEditorSemana')" style="margin-top:1rem">{{ $verEditorSemana ? 'Cerrar simulación' : 'Abrir editor de borrador' }}</button>
                        @if ($verEditorSemana)
                            @livewire(\App\Modules\GestionHorarios\Livewire\BorradorActividadesSemana::class, ['plan' => (int) $plan, 'periodo' => (int) $periodo, 'ciclo' => (int) $ciclo, 'seccion' => $seccionSemana], key('borrador-'.$plan.'-'.$periodo.'-'.$ciclo.'-'.$seccionSemana))
                        @endif
                    @endif
                </section>
            @endif
            @foreach ($cursos as $curso)
                <details class="quality-panel gh-curso" wire:key="distribucion-{{ $curso->id_curso_plan }}" @if ($loop->first || $cursoEnEdicion === (int) $curso->id_curso_plan) open @endif>
                    <summary>
                        <span class="quality-code">{{ $curso->codigo }}</span> <strong>{{ $curso->nombre }}</strong>
                        <small>Ciclo {{ $curso->ciclo ?? 'sin registrar' }} · {{ $curso->secciones->count() }} secciones</small>
                    </summary>
                    <div style="padding:1rem">
                        <p>Oferta del curso: {{ $curso->ofertas->isEmpty() ? 'sin registrar' : $curso->ofertas->map(fn ($o) => $o->tipo_oferta.' ('.$o->estado.')')->join(', ') }}</p>
                        <p>Demanda del curso: {{ $curso->total ?? 'sin datos' }}@if ($curso->total !== null) · Preferencias agregadas: mañana {{ $curso->manana }}, tarde {{ $curso->tarde }}@endif</p>
                        @if (isset($propuestasSecciones[$curso->id_curso_plan]))
                            @php($propuesta = $propuestasSecciones[$curso->id_curso_plan])
                            <div class="quality-panel" style="padding:.75rem;margin:.75rem 0">
                                <strong>Estimación de secciones adicionales</strong>
                                <p>Secciones activas: {{ $propuesta['activas'] }} · Plazas activas conocidas: {{ $propuesta['plazas'] }}</p>
                                @if ($propuesta['faltantes'] === null)
                                    <p role="status">No se puede calcular: falta demanda o hay secciones activas sin capacidad válida.</p>
                                @else
                                    <p>Plazas faltantes: {{ $propuesta['faltantes'] }} · Secciones adicionales estimadas: {{ $propuesta['adicionales'] }}</p>
                                    @if ($curso->ofertas->count() !== 1 || $curso->ofertas->first()->tipo_oferta !== 'REGULAR')
                                        <p role="status">La demanda es agregada; revise la distribución por tipo de oferta antes de crear secciones.</p>
                                    @elseif ($propuesta['adicionales'] > 0 && $escrituraDisponible && ! $periodoProtegido && $curso->ofertas->first()->estado !== 'CERRADA')
                                        <button type="button" class="institutional-secondary" wire:click="prepararPropuesta({{ $curso->id_curso_plan }})" wire:loading.attr="disabled">Revisar propuesta de creación</button>
                                    @endif
                                @endif
                                <small>No acredita disponibilidad docente, capacidad de ambientes ni factibilidad del horario.</small>
                            </div>
                        @endif
                        @if (! $periodoProtegido && $escrituraDisponible && $curso->ofertas->isNotEmpty() && ! $curso->ofertas->contains('estado', 'CERRADA'))
                            @if ($cursoEnEdicion === (int) $curso->id_curso_plan)
                                <form wire:submit="guardarSeccion" aria-label="Planificar sección" style="display:flex;align-items:end;gap:.75rem;flex-wrap:wrap;margin:1rem 0">
                                    <label>Código de sección<br><input class="quality-select" wire:model="codigo" maxlength="30" placeholder="A, B, C…" required></label>
                                    <label>Plazas planificadas<br><input class="quality-select" type="number" wire:model="capacidad" min="1" max="2147483647" required></label>
                                    <button type="submit" class="institutional-primary" wire:loading.attr="disabled">Guardar sección</button>
                                    <button type="button" class="institutional-secondary" wire:click="cancelarEdicion">Cancelar</button>
                                    @foreach (['codigo', 'capacidad', 'seccion'] as $campo)
                                        @error($campo)<p role="alert" class="quality-alert">{{ $message }}</p>@enderror
                                    @endforeach
                                </form>
                            @else
                                <button type="button" class="institutional-secondary" wire:click="abrirSeccion({{ $curso->id_curso_plan }})">Crear sección</button>
                            @endif
                        @endif
                        @if ($curso->secciones->isEmpty())
                            <p>No hay secciones registradas para este curso.</p>
                        @else
                            <div><table style="width:100%; text-align:left"><thead><tr><th>Sección</th><th>Estado</th><th>Capacidad</th><th>Información</th></tr></thead><tbody>
                                @foreach ($curso->secciones as $seccion)
                                    @php($vigente = $seccion->asociaciones->firstWhere('estado', 'VIGENTE'))
                                    <tr wire:key="seccion-{{ $seccion->id_seccion }}"><td>{{ $seccion->codigo }}
                                        @if (! $periodoProtegido && $escrituraDisponible && $curso->ofertas->isNotEmpty() && ! $curso->ofertas->contains('estado', 'CERRADA') && ! $seccion->programada)
                                            <button type="button" class="quality-open-link" wire:click="abrirSeccion({{ $curso->id_curso_plan }}, {{ $seccion->id_seccion }})" aria-label="Editar sección {{ $seccion->codigo }}">Editar</button>
                                        @endif
                                    </td><td>{{ $seccion->estado }}</td><td>{{ $seccion->capacidad !== null && $seccion->capacidad > 0 ? $seccion->capacidad : 'Sin capacidad válida' }}</td><td>
                                        @if ($vigente) Oferta {{ $vigente->tipo_oferta }} · Compatible con el curso y periodo @else Sin asociación compatible visible @endif
                                        @if ($asociacionDisponible && $escrituraDisponible && ! $periodoProtegido)
                                            <label for="oferta-{{ $seccion->id_seccion }}">Oferta compatible</label>
                                            <select id="oferta-{{ $seccion->id_seccion }}" class="quality-select" wire:model="ofertasSeleccionadas.{{ $seccion->id_seccion }}"><option value="">Seleccione una oferta</option>@foreach ($curso->ofertas as $oferta)@if ($oferta->estado !== 'CERRADA')<option value="{{ $oferta->id_oferta }}">{{ $oferta->tipo_oferta }} · {{ $oferta->estado }}</option>@endif @endforeach</select>
                                            <button type="button" class="quality-open-link" wire:click="asociar({{ $seccion->id_seccion }}, {{ $vigente ? $vigente->id_oferta_seccion : 'null' }})" wire:confirm="¿Confirma la asociación seleccionada?" wire:loading.attr="disabled">Guardar asociación</button>
                                            @if ($seccion->asociaciones->isNotEmpty())<details><summary>Historial de asociaciones</summary><ul>@foreach ($seccion->asociaciones as $asociacion)<li>{{ $asociacion->tipo_oferta }} · {{ $asociacion->estado }} · {{ $asociacion->registrada_en }}@if ($asociacion->cerrada_en) – {{ $asociacion->cerrada_en }}@endif</li>@endforeach</ul></details>@endif
                                        @endif
                                    </td></tr>
                                @endforeach
                            </tbody></table></div>
                            @if ($curso->capacidad_registrada !== null)
                                <p>Capacidad registrada total: {{ $curso->capacidad_registrada }} plazas, sumando todas las secciones sin distinguir tipo ni estado.</p>
                                @if ($curso->diferencia !== null && $curso->diferencia > 0)<p class="quality-status is-warning">La demanda supera esa suma en {{ $curso->diferencia }} plazas.</p>@endif
                            @else
                                <p>Complete las capacidades de todas las secciones antes de comparar la demanda.</p>
                            @endif
                        @endif
                        <p>Actividades registradas: {{ $curso->actividades ? implode(', ', $curso->actividades) : 'sin registrar' }}. Capacidad de subgrupos: no disponible.</p>

                    </div>
                </details>
            @endforeach
        @endif
    @endif
</div>
