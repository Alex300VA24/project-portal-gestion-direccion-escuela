<div class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios Académicos</a> / Ambientes</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios Académicos</span><h1>Ambientes y laboratorios</h1><p>Consulte el catálogo institucional y la información de ocupación registrada.</p></div></header>
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar la información de ambientes. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de ambientes"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Buscar por nombre o código<br><input class="quality-select" type="search" maxlength="120" wire:model.live.debounce.400ms="buscar"></label>
            <label>Tipo de ambiente<br><select class="quality-select" wire:model.live="tipo"><option value="">Todos</option>@foreach ($tipos as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
            <label>Ambiente<br><select class="quality-select" wire:model.live="ambiente"><option value="">Seleccione un ambiente</option>@foreach ($ambientes as $item)<option value="{{ $item->id_ambiente }}">{{ $item->codigo ?: 'Sin código' }} · {{ $item->nombre ?: 'Sin nombre' }}</option>@endforeach</select></label>
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if ($periodos->isEmpty())<div class="quality-empty"><h2>No hay periodos disponibles</h2></div>
        @elseif ($ambientes->isEmpty())<div class="quality-empty"><h2>No se encontraron ambientes</h2><p>Revise los filtros o consulte con la administración.</p></div>
        @elseif (! $periodoValido || ! $seleccion)<div class="quality-empty"><h2>Seleccione un periodo y un ambiente</h2><p>La consulta es de solo lectura.</p></div>
        @else
            <section class="quality-panel" style="margin-top:1rem; padding:1.25rem" aria-labelledby="ambiente-titulo">
                <h2 id="ambiente-titulo"><span class="quality-code">{{ $seleccion->codigo ?: 'Sin código' }}</span> {{ $seleccion->nombre ?: 'Ambiente sin nombre' }}</h2>
                <p>Tipo: {{ $seleccion->tipo ?: 'Sin registrar' }} · Capacidad: {{ $seleccion->capacidad !== null && $seleccion->capacidad > 0 ? $seleccion->capacidad.' plazas' : 'Sin capacidad válida' }} · Estado: {{ $seleccion->estado ?: 'Sin registrar' }}</p>
                <p>Ubicación: {{ $seleccion->pabellon ?: 'Sin pabellón registrado' }}@if ($seleccion->piso !== null) · Piso {{ $seleccion->piso }}@endif</p>
                <p>El catálogo consultado no incluye equipamiento ni capacidad por subgrupo. No se ha verificado si el ambiente puede compartirse.</p>
            </section>
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="bloqueos-titulo">
                <header class="quality-panel-heading"><div><h2 id="bloqueos-titulo">Bloqueos registrados</h2><p>Los bloqueos tienen fechas de vigencia; no se extrapolan a otras semanas.</p></div></header>
                @if ($bloqueos->isEmpty())<div class="quality-empty"><h3>Sin bloqueos registrados</h3><p>Esto no confirma disponibilidad.</p></div>@else
                    <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Tabla de bloqueos"><table style="width:100%; min-width:620px; text-align:left"><thead><tr><th>Fechas</th><th>Día</th><th>Franja</th><th>Motivo</th></tr></thead><tbody>@foreach ($bloqueos as $b)<tr wire:key="bloqueo-{{ $b->id_bloqueo }}"><td>{{ $b->fecha_inicio ?: 'Sin inicio' }} – {{ $b->fecha_fin ?: 'Sin fin' }} @if (! $b->fecha_inicio || ! $b->fecha_fin || $b->fecha_inicio > $b->fecha_fin) · Fechas incompletas o inválidas @endif</td><td>{{ $b->dia_semana ?: 'Día no especificado' }} @if ($b->dia_semana && ! in_array($b->dia_semana, ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'], true)) · Día no reconocido @endif</td><td>@if ($b->hora_inicio && $b->hora_fin && $b->hora_inicio < $b->hora_fin) {{ $b->hora_inicio }} – {{ $b->hora_fin }} @elseif (! $b->hora_inicio && ! $b->hora_fin) Horario no especificado @else Información incompleta o inválida @endif</td><td>{{ $b->motivo ?: 'Sin motivo registrado' }}</td></tr>@endforeach</tbody></table></div>
                @endif
            </section>
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="actividades-titulo">
                <header class="quality-panel-heading"><div><h2 id="actividades-titulo">Actividades en horarios registrados</h2><p>Cada fila conserva el estado y la versión de su horario; distintas propuestas no se consideran ocupación simultánea confirmada.</p></div></header>
                @if ($actividades->isEmpty())<div class="quality-empty"><h3>Sin actividades registradas en este ambiente</h3><p>No hay información suficiente para declarar el ambiente libre.</p></div>@else
                    <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Tabla de actividades"><table style="width:100%; min-width:760px; text-align:left"><thead><tr><th>Día y franja</th><th>Curso / sección</th><th>Actividad</th><th>Horario</th><th>Información</th></tr></thead><tbody>@foreach ($actividades as $a)<tr wire:key="detalle-ambiente-{{ $a->id_detalle }}"><td>{{ $a->dia_semana ?: 'Sin día' }} · {{ $a->hora_inicio ?: 'Sin inicio' }} – {{ $a->hora_fin ?: 'Sin fin' }}</td><td>{{ $a->curso }} · {{ $a->nombre }} / {{ $a->seccion }}</td><td>{{ $a->tipo_actividad ?: 'Sin tipo' }}</td><td>{{ $a->estado_horario ?: 'Sin estado' }} · versión {{ $a->version ?? 'sin registrar' }} · {{ $a->ambito ?: 'Sin ámbito' }}</td><td>@if ((int) $a->periodo_horario !== (int) $a->periodo_seccion) Periodo de sección inconsistente. @endif @if (! $a->dia_semana || ! $a->hora_inicio || ! $a->hora_fin || $a->hora_inicio >= $a->hora_fin) Día o franja incompletos o inválidos. @endif</td></tr>@endforeach</tbody></table></div>
                @endif
            </section>
            <section class="quality-panel" style="margin-top:1rem; padding:1.25rem" aria-labelledby="configuracion-titulo">
                <h2 id="configuracion-titulo">Configuración registrada</h2><h3>Bloques horarios del periodo</h3>
                @forelse ($bloques as $b)<p>{{ $b->nombre }} · {{ $b->hora_inicio }} – {{ $b->hora_fin }}</p>@empty<p>No hay bloques horarios registrados.</p>@endforelse
                <h3>Restricciones específicas del ambiente</h3>
                @forelse ($restricciones as $r)<p>{{ $r->nombre }} · {{ $r->severidad }}</p>@empty<p>No hay restricciones específicas registradas para este ambiente.</p>@endforelse
                <p>El calendario de días laborables aún no está confirmado. Los datos se muestran por día y franja registrados; sábado no se habilita automáticamente y «sin registro» no significa libre.</p>
            </section>
        @endif
    @endif
</div>
