<div class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios Académicos</a> / Restricciones</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios Académicos</span><h1>Restricciones académicas</h1><p>Consulte las reglas registradas y revise datos preliminares del periodo.</p></div></header>
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudieron consultar las restricciones. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Estado<br><select class="quality-select" wire:model.live="estado"><option value="">Todas</option><option value="activa">Activas</option><option value="inactiva">Inactivas</option></select></label>
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        <section class="quality-panel" style="margin-top:1rem; padding:1.25rem" aria-labelledby="prevalidacion-titulo">
            <h2 id="prevalidacion-titulo">Preparación del periodo</h2><p>Revise datos evidentes antes de preparar una generación. Esta revisión no ejecuta el motor ni garantiza factibilidad.</p>
            <button type="button" class="institutional-primary" wire:click="prevalidar" wire:loading.attr="disabled">Revisar datos</button>
            @if ($consultaFallida)<p class="quality-alert" role="alert">No se pudo completar la revisión. Intente nuevamente o comuníquese con soporte.</p>@endif
            @if ($resultados)<ul aria-live="polite">@foreach ($resultados as $resultado)<li><strong>{{ $resultado['nivel'] }}:</strong> {{ $resultado['mensaje'] }}</li>@endforeach</ul>@endif
            <p>La generación de horarios todavía no está habilitada.</p>
        </section>
        <section class="quality-panel" style="margin-top:1rem" aria-labelledby="reglas-titulo">
            <header class="quality-panel-heading"><div><h2 id="reglas-titulo">Reglas registradas</h2><p>Las reglas de docentes y ambientes no tienen periodo explícito en el modelo. La relación con curso o sección aún no está representada.</p></div></header>
            @if ($restricciones->isEmpty())<div class="quality-empty"><h3>No hay restricciones para los filtros seleccionados</h3></div>@else
                <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Tabla de restricciones"><table style="width:100%; min-width:760px; text-align:left"><thead><tr><th>Código / regla</th><th>Descripción</th><th>Severidad</th><th>Estado</th><th>Alcance</th></tr></thead><tbody>
                    @foreach ($restricciones as $r)<tr wire:key="restriccion-{{ $r->id_restriccion }}-{{ $r->id_detalle_restriccion ?? 'sin-alcance' }}"><td><span class="quality-code">{{ $r->codigo }}</span> {{ $r->nombre }}</td><td>{{ $r->descripcion }}</td><td>{{ $r->severidad === 'DURA' ? 'Obligatoria (DURA)' : ($r->severidad === 'BLANDA' ? 'Preferencia (BLANDA)' : 'No identificada') }}</td><td>{{ $r->activa ? 'Activa' : 'Inactiva' }}</td><td>{{ $r->ambito ?? 'Sin alcance registrado' }} @if ($r->ambito === 'DOCENTE') · {{ $r->codigo_docente ?: 'Docente no identificado' }} {{ trim(($r->nombres ?? '').' '.($r->apellido_paterno ?? '').' '.($r->apellido_materno ?? '')) }} @elseif ($r->ambito === 'AMBIENTE') · {{ $r->codigo_ambiente ?: 'Ambiente no identificado' }} {{ $r->nombre_ambiente }} @elseif ($r->ambito === 'PERIODO') · Periodo seleccionado @endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>
    @endif
</div>
