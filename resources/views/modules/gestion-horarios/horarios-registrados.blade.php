<main class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios</a> / Horarios registrados</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios Académicos</span><h1>Horarios registrados</h1><p>Consulte las actividades de una versión del horario. La información es de solo lectura.</p></div></header>
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar los horarios. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de horarios"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Horario<br><select class="quality-select" wire:model.live="horario"><option value="">Seleccione una versión</option>@foreach ($horarios as $h)<option value="{{ $h->id_horario }}">{{ $h->ambito }} · versión {{ $h->version }} · {{ $h->estado }}</option>@endforeach</select></label>
            <label>Ciclo<br><input class="quality-select" type="number" min="1" max="99" wire:model.live.debounce.400ms="ciclo"></label>
            <label>Sección<br><input class="quality-select" maxlength="30" wire:model.live.debounce.400ms="seccion"></label>
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando horarios…</p>
        @if (! $seleccion)
            <div class="quality-empty"><h2>Seleccione un periodo y un horario</h2><p>Si no aparecen versiones, no hay horarios registrados para el periodo seleccionado.</p></div>
        @elseif ($sesiones->isEmpty())
            <div class="quality-empty"><h2>Sin actividades para esta selección</h2><p>Revise los filtros o consulte otra versión del horario.</p></div>
        @else
            <div style="display:flex; flex-wrap:wrap; gap:.75rem; margin-top:1rem">
                <button type="button" class="institutional-primary" wire:click="descargar('pdf')" wire:loading.attr="disabled">Descargar PDF</button>
                <button type="button" class="institutional-primary" wire:click="descargar('xlsx')" wire:loading.attr="disabled">Descargar Excel</button>
            </div>
            @if ($errorDescarga)<p role="alert">{{ $errorDescarga }}</p>@endif
            <section class="quality-panel" style="margin-top:1rem; padding:1rem"><h2>{{ $seleccion->ambito }} · versión {{ $seleccion->version }} · {{ $seleccion->estado }}</h2><p>Las franjas y días corresponden a actividades registradas. La visualización no confirma disponibilidad ni factibilidad.</p>
                <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Cuadrícula semanal de actividades"><table style="width:100%; min-width:720px; text-align:left"><thead><tr><th scope="col">Franja</th>@foreach (['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'] as $dia) @if ($sesiones->contains('dia_semana', $dia))<th scope="col">{{ $dia }}</th>@endif @endforeach</tr></thead><tbody>
                @foreach ($sesiones->groupBy(fn ($s) => $s->hora_inicio.'–'.$s->hora_fin) as $franja => $filas)
                    <tr><th scope="row">{{ $franja }}</th>@foreach (['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'] as $dia) @if ($sesiones->contains('dia_semana', $dia))<td>@foreach ($filas->where('dia_semana', $dia) as $s)<div style="padding:.5rem; border:1px solid var(--portal-border, #d7dcd5); margin:.25rem"><strong>{{ $s->curso_codigo }} · {{ $s->curso_nombre }}</strong><br>{{ $s->tipo_actividad }} · ciclo {{ $s->ciclo }} · sección {{ $s->seccion }}<br>Ambiente: {{ $s->ambiente ?: 'Sin registrar' }}<br>Docente: @forelse ($docentes->get($s->id_actividad, collect()) as $d){{ $d->nombres }} {{ $d->apellido_paterno }} {{ $d->apellido_materno }}@if (! $loop->last); @endif @empty Sin asignación confirmada @endforelse</div>@endforeach</td>@endif @endforeach</tr>
                @endforeach
                </tbody></table></div>
            </section>
        @endif
    @endif
</main>
