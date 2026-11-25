<div class="quality-page gh-ui" style="padding:2rem 1rem;margin:0 auto;max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Recursos académicos', 'subtitulo'=>'Prioridad docente', 'descripcion'=>'Orden global de docentes, independiente del periodo.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'recursos', 'workspaceSub' => 'prioridad', 'workspacePlan' => session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => session('gestion_horarios.periodo_seleccionado', '')])
    <p class="gh-ui-note">El orden se conserva y se utiliza como preferencia durante la generación de demostración. No reemplaza las restricciones obligatorias.</p>
    @if (!$disponible)
        <div class="quality-empty"><h2>Almacenamiento local no instalado</h2><p>Ejecute la preparación de prioridad QA desde la terminal y recargue esta pantalla.</p></div>
    @else
        <div class="gh-ui-metrics"><div class="gh-ui-metric"><strong>{{ count($orden) }}</strong><small>En cola</small></div><div class="gh-ui-metric"><strong>{{ $candidatos->count() }}</strong><small>Sin prioridad</small></div><div class="gh-ui-metric"><strong>{{ $version }}</strong><small>Versión guardada</small></div></div>
        @if ($mensaje)<p class="gh-ui-success" role="status">{{ $mensaje }}</p>@endif
        @if ($cambioRemoto)<p class="quality-alert" role="alert">La cola o el catálogo cambió. Recargue para continuar; se perderá el borrador.</p>@endif
        @foreach ($errors->all() as $error)<p class="quality-alert" role="alert">{{ $error }}</p>@endforeach
        <section class="quality-panel" style="padding:1.25rem;margin-top:1rem">
            <h2>Orden de atención</h2><p>La primera posición tiene mayor prioridad. No se asigna un orden inicial ficticio.</p>
            <form wire:submit="agregar" class="gh-ui-context"><label>Agregar docente<select class="quality-select" wire:model="candidato"><option value="">Seleccione un docente</option>@foreach ($candidatos as $d)<option value="{{ $d->id_docente }}">{{ $d->nombres }} {{ $d->apellido_paterno }} {{ $d->apellido_materno }}</option>@endforeach</select></label><button class="institutional-primary" type="submit" wire:loading.attr="disabled" @disabled($cambioRemoto)>Agregar</button></form>
            <div style="overflow-x:auto"><table style="width:100%;text-align:left"><thead><tr><th>Posición</th><th>Docente</th><th>Acciones</th></tr></thead><tbody>
            @forelse ($orden as $i => $id)
                @php($d = $docentes->get($id))
                <tr wire:key="prioridad-{{ $id }}"><td>{{ $i+1 }}</td><td>{{ $d ? trim($d->nombres.' '.$d->apellido_paterno.' '.$d->apellido_materno) : 'Docente no disponible' }}</td><td><div class="gh-ui-actions">
                    <button class="institutional-secondary" type="button" wire:click="mover({{ $id }}, -1)" wire:loading.attr="disabled" @disabled($i === 0 || $cambioRemoto) aria-label="Subir docente {{ $i+1 }}">↑ Subir</button>
                    <button class="institutional-secondary" type="button" wire:click="mover({{ $id }}, 1)" wire:loading.attr="disabled" @disabled($i === count($orden)-1 || $cambioRemoto) aria-label="Bajar docente {{ $i+1 }}">↓ Bajar</button>
                    <button class="institutional-secondary" type="button" wire:click="quitar({{ $id }})" wire:loading.attr="disabled" @disabled($cambioRemoto)>Quitar</button></div></td></tr>
            @empty<tr><td colspan="3">No hay docentes priorizados. Agregue los docentes en el orden deseado.</td></tr>@endforelse
            </tbody></table></div>
            <div class="gh-ui-actions"><button class="institutional-primary" type="button" wire:click="guardar" wire:loading.attr="disabled" @disabled($cambioRemoto || !$modificado)>Guardar orden</button><button class="institutional-secondary" type="button" wire:click="recargar" wire:confirm="¿Descartar el borrador y recargar?" wire:loading.attr="disabled">Recargar orden</button></div>
        </section>
    @endif
</div>
