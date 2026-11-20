<div class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios Académicos</a> / Disponibilidad docente</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios Académicos</span><h1>Disponibilidad docente</h1><p>Consulte declaraciones y bloques registrados para un docente y periodo.</p></div></header>
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar la disponibilidad. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de disponibilidad"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Buscar docente por nombre o identificación<br><input class="quality-select" type="search" maxlength="120" wire:model.live.debounce.400ms="buscar"></label>
            <label>Docente<br><select class="quality-select" wire:model.live="docente"><option value="">Seleccione un docente</option>@foreach ($docentes as $d)<option value="{{ $d->id_docente }}">{{ $d->codigo_docente }} · {{ $d->nombres ? trim($d->nombres.' '.$d->apellido_paterno.' '.$d->apellido_materno) : 'Datos incompletos' }}</option>@endforeach</select></label>
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if ($periodos->isEmpty())<div class="quality-empty"><h2>No hay periodos disponibles</h2></div>
        @elseif ($docentes->isEmpty())<div class="quality-empty"><h2>No se encontraron docentes</h2><p>Revise la búsqueda.</p></div>
        @elseif (! $periodoValido || ! $docenteValido)<div class="quality-empty"><h2>Seleccione periodo y docente</h2><p>La consulta es de solo lectura.</p></div>
        @else
            <section class="quality-panel" style="margin-top:1rem; padding:1.25rem" aria-labelledby="declaracion-titulo">
                <h2 id="declaracion-titulo">Declaración de disponibilidad</h2>
                @if ($declaracionesDuplicadas)<p>Estado indeterminado: existen declaraciones duplicadas.</p>
                @elseif ($declaracion)<p>Estado: <strong>{{ $declaracion->estado }}</strong> · Tratamiento para cursos regulares: <strong>{{ $declaracion->tratamiento_regular }}</strong></p>
                @else<p>No existe una declaración registrada para este periodo y docente.</p>@endif
                <p>Bloques registrados: {{ $totalRegistros }}. La ausencia de declaración o de bloques no confirma disponibilidad efectiva para la generación.</p>
                @if ($declaracion && $declaracion->estado === 'PRESENTADA' && $totalRegistros === 0)
                    <p>La declaración fue presentada sin franjas; este estado es distinto de no haber declarado.</p>
                @endif
                <p>La preferencia y la edición con control de versiones todavía requieren un contrato institucional. Una declaración guardada no necesita confirmación adicional.</p>
                @foreach ($avisos as $aviso)<p class="quality-alert" role="alert">{{ $aviso }}</p>@endforeach
            </section>
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="matriz-titulo">
                <header class="quality-panel-heading"><div><h2 id="matriz-titulo">Bloques por día</h2><p>«Registrado» indica un dato almacenado; no acredita disponibilidad efectiva.</p></div></header>
                @if ($bloques->isEmpty())<div class="quality-empty"><h3>No hay bloques horarios configurados</h3><p>Revise la configuración del periodo.</p></div>@else
                    <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Cuadrícula semanal de bloques"><table style="width:100%; min-width:760px; text-align:left"><caption class="sr-only">Bloques registrados de lunes a sábado</caption><thead><tr><th scope="col">Bloque</th>@foreach ($dias as $dia)<th scope="col">{{ $dia === 'SABADO' ? 'Sábado' : ucfirst(mb_strtolower($dia)) }}</th>@endforeach</tr></thead><tbody>
                        @foreach ($bloques as $bloque)<tr wire:key="bloque-{{ $bloque->id_bloque }}"><th scope="row">{{ $bloque->nombre }} · {{ $bloque->hora_inicio }} – {{ $bloque->hora_fin }}</th>@foreach ($dias as $dia)<td>{{ ($matriz[$bloque->id_bloque.':'.$dia] ?? 0) > 1 ? 'Duplicado' : (($matriz[$bloque->id_bloque.':'.$dia] ?? 0) === 1 ? 'Registrado' : 'Sin registro') }}</td>@endforeach</tr>@endforeach
                    </tbody></table></div>
                @endif
                <p style="padding:1rem">El calendario institucional de días laborables y la política de sábado aún no están confirmados. Mostrar sábado no lo habilita para cursos regulares ni de repetición.</p>
            </section>
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="actividades-titulo">
                <header class="quality-panel-heading"><div><h2 id="actividades-titulo">Actividades asignadas</h2><p>La comparación con los bloques es preliminar y no valida el horario completo.</p></div></header>
                @if ($actividades->isEmpty())<div class="quality-empty"><h3>No hay actividades asignadas</h3></div>@else
                    <div style="overflow-x:auto"><table style="width:100%; min-width:680px; text-align:left"><thead><tr><th>Curso y sección</th><th>Actividad</th><th>Día y hora</th><th>Estado</th><th>Observación</th></tr></thead><tbody>@foreach ($actividades as $a)<tr wire:key="asignacion-{{ $a->id_asignacion }}"><td>{{ $a->curso }} · {{ $a->nombre }} · {{ $a->seccion }}</td><td>{{ $a->tipo_actividad }}</td><td>{{ $a->dia_semana }} · {{ $a->hora_inicio }} – {{ $a->hora_fin }}</td><td>{{ $a->estado }}</td><td>{{ $a->observacion_preliminar ?: 'Sin conclusión de compatibilidad' }}</td></tr>@endforeach</tbody></table></div>
                @endif
            </section>
        @endif
    @endif
</div>
