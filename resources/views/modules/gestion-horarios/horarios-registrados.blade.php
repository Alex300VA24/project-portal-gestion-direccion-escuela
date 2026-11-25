<main class="quality-page gh-ui" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Revisión y publicación', 'subtitulo'=>'Horarios registrados', 'descripcion'=>'Consulte las actividades de una versión del horario. La información es de solo lectura.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'revision', 'workspaceSub' => 'registrados', 'workspacePlan' => $plan ?? session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => $periodo ?? session('gestion_horarios.periodo_seleccionado', '')])
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
                @include('modules.gestion-horarios.cuadricula-semanal')
            </section>
        @endif
    @endif
</main>
