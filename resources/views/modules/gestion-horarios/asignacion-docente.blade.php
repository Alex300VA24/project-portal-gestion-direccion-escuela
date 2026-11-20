<div class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios Académicos</a> / Asignación docente</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios Académicos</span><h1>Asignación docente</h1><p>Consulte docentes y actividades registradas para el periodo.</p></div></header>
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
        <p>Solo lectura. Las asignaciones y duraciones mostradas no certifican disponibilidad, ausencia de cruces ni compatibilidad académica.</p>
        <section class="quality-panel" style="margin-top:1rem" aria-labelledby="directorio-titulo">
            <header class="quality-panel-heading"><div><h2 id="directorio-titulo">Docentes</h2><p>Se muestran docentes con asignaciones en el plan y periodo seleccionados. La duración corresponde únicamente a ese plan y no acredita la carga global.</p></div></header>
            @if ($docentes->isEmpty())<div class="quality-empty"><h3>No se encontraron docentes</h3><p>Revise la búsqueda o consulte con la administración académica.</p></div>@else
                <div style="overflow-x:auto"><table style="width:100%; min-width:600px; text-align:left"><thead><tr><th>Código</th><th>Docente</th><th>Estado</th><th>Duración confirmada registrada</th></tr></thead><tbody>
                    @foreach ($docentes as $docente)<tr wire:key="docente-{{ $docente->id_docente }}"><td class="quality-code">{{ $docente->codigo_docente ?: 'Sin código' }}</td><td>{{ $docente->nombres ? trim($docente->nombres.' '.$docente->apellido_paterno.' '.$docente->apellido_materno) : 'Datos personales incompletos' }}</td><td>{{ $docente->estado ?: 'Sin estado' }}</td><td>@if (! $periodoValido) Seleccione un periodo @elseif (! $docente->actividades_confirmadas) Sin actividades confirmadas @elseif ($docente->minutos_confirmados === null) Horas incompletas @else {{ $docente->minutos_confirmados }} minutos ({{ $docente->actividades_confirmadas }} actividades) @endif</td></tr>@endforeach
                </tbody></table></div>
                <p style="padding:1rem">La duración suma intervalos registrados de actividades confirmadas distintas, sin corregir cruces ni incluir otras labores. No es la carga académica oficial.</p>
            @endif
        </section>
        @if (! $periodoValido)<div class="quality-empty"><h2>Seleccione un periodo</h2><p>Elija un periodo para consultar las actividades.</p></div>@else
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="actividades-titulo">
                <header class="quality-panel-heading"><div><h2 id="actividades-titulo">Actividades del periodo</h2><p>La búsqueda de docente muestra actividades vinculadas a docentes coincidentes; límpiela para ver las actividades sin asignación.</p></div></header>
                @if ($actividades->isEmpty())<div class="quality-empty"><h3>No se encontraron actividades</h3><p>Revise el periodo y los filtros seleccionados.</p></div>@else
                    <div style="overflow-x:auto"><table style="width:100%; min-width:800px; text-align:left"><thead><tr><th>Curso</th><th>Ciclo / sección</th><th>Actividad</th><th>Horario registrado</th><th>Docentes y estado</th><th>Información</th></tr></thead><tbody>
                        @foreach ($actividades as $actividad)<tr wire:key="actividad-{{ $actividad->id_actividad }}"><td><span class="quality-code">{{ $actividad->curso_codigo }}</span> {{ $actividad->curso_nombre }}</td><td>{{ $actividad->ciclo ?? 'Sin ciclo' }} / {{ $actividad->seccion ?: 'Sin sección' }}</td><td>{{ $actividad->tipo_actividad ?: 'Tipo no registrado' }}</td><td>{{ $actividad->dia_semana ?: 'Día no registrado' }} · {{ $actividad->hora_inicio ?: 'Sin inicio' }} – {{ $actividad->hora_fin ?: 'Sin fin' }}</td><td>@forelse ($actividad->asignaciones as $asignacion)<div>{{ $asignacion->nombres ? trim($asignacion->nombres.' '.$asignacion->apellido_paterno.' '.$asignacion->apellido_materno) : 'Docente sin datos completos' }} · {{ $asignacion->estado_asignacion }}</div>@empty Sin asignación @endforelse</td><td>@if ($actividad->sin_docente_activo) Sin docente propuesto o confirmado. @endif @if ($actividad->duplicados) Posible asignación duplicada; revisar. @endif @if (! $actividad->tipo_actividad || ! $actividad->dia_semana || ! $actividad->hora_inicio || ! $actividad->hora_fin || $actividad->hora_inicio >= $actividad->hora_fin) Datos de actividad incompletos o inválidos. @endif Tipo de oferta no confirmado.</td></tr>@endforeach
                    </tbody></table></div>
                @endif
            </section>
        @endif
    @endif
</div>
