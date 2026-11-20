<div class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios</a> / Secciones</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios</span><h1>Distribución de secciones</h1><p>Revise la información disponible antes de preparar una distribución académica.</p></div></header>
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudieron consultar las secciones. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de secciones"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Buscar curso o código de sección<br><input class="quality-select" type="search" maxlength="120" wire:model.live.debounce.400ms="buscar"></label>
            <label>Plan de estudios<br><select class="quality-select" wire:model.live="plan"><option value="">Seleccione un plan</option>@foreach ($planes as $item)<option value="{{ $item->id_plan }}">{{ $item->codigo }} · {{ $item->nombre }} · Escuela {{ $item->id_escuela }}</option>@endforeach</select></label>
            <label>Ciclo<br><select class="quality-select" wire:model.live="ciclo" @disabled(! $planValido)><option value="">Todos</option>@foreach ($ciclos as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
            @if ($avisoPlan)<p role="status">{{ $avisoPlan }}</p>@endif
            @if (! $planValido)<p>Seleccione un plan de estudios; el periodo no determina automáticamente un plan.</p>@elseif ($ciclos->isEmpty())<p>El plan seleccionado no tiene ciclos registrados.</p>@endif
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if (! $asociacionDisponible)<p class="quality-alert" role="status">La asociación de ofertas aún no está disponible. Se requiere la actualización coordinada de Gestión de Horarios.</p>@endif
        @if ($asociacionDisponible)<p role="status">Se muestran asociaciones compatibles con el plan y periodo seleccionados. La conciliación de referencias inconsistentes está pendiente de autorización por escuela.</p>@endif
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
            <p>La distribución de estudiantes es preliminar. No se han asignado estudiantes ni comprobado cruces de horario.</p>
            <p>Los totales de demanda visibles corresponden a cursos del plan seleccionado y no acreditan matrícula.</p>
            @foreach ($cursos as $curso)
                <section class="quality-panel" style="margin-top:1rem" wire:key="distribucion-{{ $curso->id_curso_plan }}" aria-labelledby="curso-{{ $curso->id_curso_plan }}">
                    <header class="quality-panel-heading"><div><h2 id="curso-{{ $curso->id_curso_plan }}"><span class="quality-code">{{ $curso->codigo }}</span> {{ $curso->nombre }}</h2><p>Ciclo {{ $curso->ciclo ?? 'sin registrar' }} · Oferta del curso: {{ $curso->ofertas->isEmpty() ? 'sin registrar' : $curso->ofertas->map(fn ($o) => $o->tipo_oferta.' ('.$o->estado.')')->join(', ') }}</p></div></header>
                    <div style="padding:1rem">
                        <p>Demanda del curso: {{ $curso->total ?? 'sin datos' }}@if ($curso->total !== null) · Preferencias agregadas: mañana {{ $curso->manana }}, tarde {{ $curso->tarde }}@endif</p>
                        @if ($curso->secciones->isEmpty())
                            <p>No hay secciones registradas para este curso.</p>
                        @else
                            <div style="overflow-x:auto"><table style="width:100%; min-width:600px; text-align:left"><thead><tr><th>Sección</th><th>Estado</th><th>Capacidad</th><th>Información</th></tr></thead><tbody>
                                @foreach ($curso->secciones as $seccion)
                                    @php($vigente = $seccion->asociaciones->firstWhere('estado', 'VIGENTE'))
                                    <tr wire:key="seccion-{{ $seccion->id_seccion }}"><td>{{ $seccion->codigo }}</td><td>{{ $seccion->estado }}</td><td>{{ $seccion->capacidad !== null && $seccion->capacidad > 0 ? $seccion->capacidad : 'Sin capacidad válida' }}</td><td>
                                        @if ($vigente) Oferta {{ $vigente->tipo_oferta }} · Compatible con el curso y periodo @else Sin asociación compatible visible @endif · Turno no identificado
                                        @if ($asociacionDisponible && $escrituraDisponible)
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
                        <p>Distribución por sección pendiente: revise las asociaciones, los turnos y la capacidad de subgrupos. La comparación de capacidad no acredita factibilidad ni compatibilidad entre cursos.</p>
                    </div>
                </section>
            @endforeach
        @endif
    @endif
</div>
