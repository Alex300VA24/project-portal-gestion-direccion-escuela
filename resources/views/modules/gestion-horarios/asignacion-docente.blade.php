<div class="quality-page gh-ui" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Preparación académica', 'subtitulo'=>'Asignación docente', 'descripcion'=>'Consulte docentes y actividades registradas para el periodo.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'preparacion', 'workspaceSub' => 'asignacion', 'workspacePlan' => $plan ?? session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => $periodo ?? session('gestion_horarios.periodo_seleccionado', '')])
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar la asignación docente. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de asignación"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Buscar docente por nombre o identificación<br><input class="quality-select" type="search" maxlength="120" wire:model.live.debounce.400ms="buscar"></label>
            <label>Plan de estudios<br><select class="quality-select" wire:model.live="plan"><option value="">Seleccione un plan</option>@foreach ($planes as $item)<option value="{{ $item->id_plan }}">{{ $item->codigo }} · {{ $item->nombre }} · Escuela {{ $item->id_escuela }}</option>@endforeach</select></label>
            <label>Ciclo<br><select class="quality-select" wire:model.live="ciclo" @disabled(! $planValido)><option value="">Todos</option>@foreach ($ciclos as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
            @if ($avisoPlan)<p role="status">{{ $avisoPlan }}</p>@endif
            @if (! $planValido)<p>Seleccione un plan de estudios; el periodo no determina automáticamente un plan.</p>@elseif ($ciclos->isEmpty())<p>El plan seleccionado no tiene ciclos registrados.</p>@endif
            <label>Sección<br><input class="quality-select" type="search" maxlength="30" wire:model.live.debounce.400ms="seccion"></label>
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        <p>Las asignaciones y duraciones mostradas no certifican disponibilidad, ausencia de cruces ni compatibilidad académica.</p>
        @if ($edicionDemo ?? false)
            <p class="gh-ui-note">Demostración local: solo se permite reasignar propuestas individuales, sin confirmar ni modificar horarios.</p>
            @if ($mensaje)<p class="gh-ui-success" role="status" aria-live="polite">{{ $mensaje }}</p>@endif
        @else
            <p>Solo lectura.</p>
        @endif
        @php
            $gruposActividad = $actividades->groupBy(fn ($a) => json_encode([$a->curso_codigo, $a->ciclo, $a->seccion]));
            $tiposActividad = ['TEORIA'=>'Teoría', 'PRACTICA'=>'Práctica', 'LABORATORIO'=>'Laboratorio'];
            $ordenTipos = ['TEORIA'=>0, 'PRACTICA'=>1, 'LABORATORIO'=>2];
            $asignacionesVisibles = $actividades->flatMap(fn ($a) => $a->asignaciones)->unique('id_asignacion');
        @endphp
        <div class="gh-ui-metrics" aria-label="Resumen de resultados filtrados">
            <div class="gh-ui-metric"><strong>{{ $docentes->count() }}</strong><small>Docentes encontrados</small></div>
            <div class="gh-ui-metric"><strong>{{ $gruposActividad->count() }}</strong><small>Cursos y secciones</small></div>
            <div class="gh-ui-metric"><strong>{{ $actividades->count() }}</strong><small>Actividades registradas</small></div>
            <div class="gh-ui-metric"><strong>{{ $asignacionesVisibles->where('estado_asignacion', 'PROPUESTA')->count() }}</strong><small>Asignaciones propuestas</small></div>
            <div class="gh-ui-metric"><strong>{{ $asignacionesVisibles->where('estado_asignacion', 'CONFIRMADA')->count() }}</strong><small>Asignaciones confirmadas</small></div>
        </div>
        @if (! $periodoValido)<div class="quality-empty"><h2>Seleccione un periodo</h2><p>Elija un periodo para consultar las actividades.</p></div>@else
            <section class="quality-panel" aria-labelledby="actividades-titulo">
                <header class="quality-panel-heading"><h2 id="actividades-titulo">Asignaciones por curso y sección</h2><p>Abra un curso para consultar teoría, práctica y laboratorio. La búsqueda por docente filtra las actividades vinculadas; límpiela para ver las actividades sin asignación. Cada fila conserva su actividad registrada; los subgrupos no se deducen de las franjas.</p></header>
                @forelse ($gruposActividad as $claveGrupo => $grupo)
                    @php($primera = $grupo->first())
                    <details class="gh-ui-group" wire:key="grupo-asignacion-{{ sha1($claveGrupo) }}" @if ($loop->first || (($asignacionEditar ?? null) !== null && $grupo->contains(fn ($a) => collect($a->asignaciones)->contains(fn ($x) => (int) $x->id_asignacion === (int) $asignacionEditar)))) open @endif>
                        <summary><span class="quality-code">{{ $primera->curso_codigo }}</span> {{ $primera->curso_nombre }} <small>Ciclo {{ $primera->ciclo ?? 'Sin ciclo' }} · Sección {{ $primera->seccion ?: 'Sin sección' }} · {{ $grupo->count() }} actividades</small></summary>
                        <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Actividades del curso {{ $primera->curso_codigo }}">
                            <table style="width:100%;min-width:680px;text-align:left"><thead><tr><th>Actividad</th><th>Franja de referencia</th><th>Docente / acción</th><th>Observaciones</th></tr></thead><tbody>
                            @foreach ($grupo->sortBy(fn ($a) => $ordenTipos[$a->tipo_actividad] ?? 3) as $actividad)
                                <tr wire:key="actividad-{{ $actividad->id_actividad }}">
                                    <td><strong>{{ $tiposActividad[$actividad->tipo_actividad] ?? ($actividad->tipo_actividad ?: 'Sin tipo') }}</strong></td>
                                    <td>{{ $actividad->dia_semana ?: 'Sin día' }}<br>{{ substr($actividad->hora_inicio ?? '',0,5) }}–{{ substr($actividad->hora_fin ?? '',0,5) }}</td>
                                    <td>
                                        @forelse ($actividad->asignaciones as $asignacion)
                                            <div wire:key="asignacion-ui-{{ $actividad->id_actividad }}-{{ $asignacion->id_asignacion }}">
                                                <strong>{{ $asignacion->nombres ? trim($asignacion->nombres.' '.$asignacion->apellido_paterno.' '.$asignacion->apellido_materno) : 'Docente sin datos completos' }}</strong>
                                                <div class="gh-ui-actions"><span class="gh-ui-state" data-estado="{{ $asignacion->estado_asignacion }}">{{ $asignacion->estado_asignacion }}</span>
                                                @if (($edicionDemo ?? false) && $asignacion->estado_asignacion === 'PROPUESTA')
                                                    <button class="institutional-secondary" type="button" wire:click="abrirReasignacion({{ (int) $asignacion->id_asignacion }}, {{ (int) $asignacion->id_docente }})" wire:loading.attr="disabled">Reasignar docente</button>
                                                @endif</div>
                                                @if (($edicionDemo ?? false) && ($asignacionEditar ?? null) !== null && (int) $asignacionEditar === (int) $asignacion->id_asignacion)
                                                    <div class="gh-ui-editor" aria-label="Reasignar docente">
                                                        <form wire:submit="guardarReasignacion">
                                                            <label>Nuevo docente<select class="quality-select" wire:model="docenteNuevo" required><option value="">Seleccione un docente</option>@foreach ($candidatosDemo as $candidato)<option value="{{ $candidato->id_docente }}">{{ trim($candidato->nombres.' '.$candidato->apellido_paterno) ?: $candidato->codigo_docente }}</option>@endforeach</select></label>
                                                            @error('docenteNuevo')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
                                                            <div class="gh-ui-actions"><button class="institutional-primary" type="submit" wire:loading.attr="disabled">Guardar propuesta</button><button class="institutional-secondary" type="button" wire:click="cancelarReasignacion" wire:loading.attr="disabled">Cancelar</button></div>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        @empty <span>Sin asignación</span> @endforelse
                                    </td>
                                    <td>@if ($actividad->sin_docente_activo) Sin docente propuesto o confirmado. @endif @if ($actividad->duplicados) Posible asignación duplicada; revisar. @endif @if (! $actividad->tipo_actividad || ! $actividad->dia_semana || ! $actividad->hora_inicio || ! $actividad->hora_fin || $actividad->hora_inicio >= $actividad->hora_fin) Datos de actividad incompletos o inválidos. @endif Tipo de oferta no confirmado.</td>
                                </tr>
                            @endforeach
                            </tbody></table>
                        </div>
                    </details>
                @empty <div class="quality-empty"><h3>No se encontraron actividades</h3><p>Revise los filtros seleccionados.</p></div> @endforelse
            </section>
        @endif
        <details class="quality-panel gh-ui-group" style="margin-top:1rem" aria-labelledby="directorio-titulo">
            <summary id="directorio-titulo">Directorio y carga docente · {{ $docentes->count() }} docentes</summary><div style="padding:.75rem 1rem"><p>Se muestran docentes con asignaciones en el plan y periodo seleccionados. La duración corresponde únicamente a ese plan y no acredita la carga global.</p></div>
            @if ($docentes->isEmpty())<div class="quality-empty"><h3>No se encontraron docentes</h3><p>Revise la búsqueda o consulte con la administración académica.</p></div>@else
                <div style="overflow-x:auto"><table style="width:100%; min-width:600px; text-align:left"><thead><tr><th>Docente</th><th>Código interno</th><th>Estado</th><th>Duración confirmada registrada</th></tr></thead><tbody>
                    @foreach ($docentes as $docente)<tr wire:key="docente-{{ $docente->id_docente }}"><td>{{ $docente->nombres ? trim($docente->nombres.' '.$docente->apellido_paterno.' '.$docente->apellido_materno) : 'Datos personales incompletos' }}</td><td class="quality-code"><small>{{ $docente->codigo_docente ?: 'Sin código' }}</small></td><td>{{ $docente->estado ?: 'Sin estado' }}</td><td>@if (! $periodoValido) Seleccione un periodo @elseif (! $docente->actividades_confirmadas) Sin actividades confirmadas @elseif ($docente->minutos_confirmados === null) Horas incompletas @else {{ $docente->minutos_confirmados }} minutos ({{ $docente->actividades_confirmadas }} actividades) @endif</td></tr>@endforeach
                </tbody></table></div>
                <p style="padding:1rem">La duración suma intervalos registrados de actividades confirmadas distintas, sin corregir cruces ni incluir otras labores. No es la carga académica oficial.</p>
            @endif
        </details>
    @endif
</div>
