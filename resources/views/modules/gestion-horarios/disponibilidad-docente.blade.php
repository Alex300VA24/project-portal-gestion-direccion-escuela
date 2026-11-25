<div class="quality-page gh-ui" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Preparación académica', 'subtitulo'=>'Disponibilidad docente', 'descripcion'=>'Consulte las declaraciones y edite las franjas en el entorno local de demostración.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'preparacion', 'workspaceSub' => 'disponibilidad', 'workspacePlan' => $plan ?? session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => $periodo ?? session('gestion_horarios.periodo_seleccionado', '')])
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar la disponibilidad. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de disponibilidad"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            @php($opcionesDocentes = \App\Modules\GestionHorarios\Services\ConsultaSemanalDemo::opciones($docentes->mapWithKeys(fn ($d) => [$d->id_docente => trim($d->nombres.' '.$d->apellido_paterno.' '.$d->apellido_materno) ?: 'Docente sin nombre registrado'])->all()))
            @include('modules.gestion-horarios.selector-horario-buscable', ['campo'=>'docente', 'etiqueta'=>'Docente', 'placeholder'=>'Buscar o seleccionar docente', 'opciones'=>$opcionesDocentes])
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if ($periodos->isEmpty())<div class="quality-empty"><h2>No hay periodos disponibles</h2></div>
        @elseif ($docentes->isEmpty())<div class="quality-empty"><h2>No se encontraron docentes</h2><p>Revise la búsqueda.</p></div>
        @elseif (! $periodoValido || ! $docenteValido)<div class="quality-empty"><h2>Seleccione periodo y docente</h2><p>Seleccione un docente para consultar y editar su disponibilidad.</p></div>
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
                @if ($edicionDemo ?? false)
                    @if ($mensajeEdicion)<p class="gh-ui-success" role="status">{{ $mensajeEdicion }}</p>@endif
                    @if (!$versionEdicion)<button type="button" class="institutional-primary" wire:click="abrirEdicion" wire:loading.attr="disabled">Editar disponibilidad</button>@endif
                    @error('edicionDisponibilidad')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
                @else
                    <p>La edición requiere autorización institucional.</p>
                @endif
                @foreach ($avisos as $aviso)<p class="quality-alert" role="alert">{{ $aviso }}</p>@endforeach
            </section>
            @if (($edicionDemo ?? false) && $versionEdicion)
                <section class="quality-panel" style="margin-top:1rem;padding:1.25rem" aria-labelledby="edicion-disp-titulo">
                    <h2 id="edicion-disp-titulo">Editar disponibilidad semanal</h2>
                    <p>Marque los bloques disponibles. Una declaración presentada sin franjas es distinta de una no presentada. El sábado no habilita por sí solo la programación.</p>
                    <form wire:submit="guardarEdicion">
                        <div class="gh-ui-context">
                            <label>Estado <select class="quality-select" wire:model.live="estadoEdicion"><option value="PRESENTADA">Presentada</option><option value="PENDIENTE">Pendiente</option><option value="NO_PRESENTADA">No presentada</option></select></label>
                            <label>Tratamiento regular <select class="quality-select" wire:model.live="tratamientoEdicion"><option value="ESTRICTA">Estricta</option><option value="FLEXIBLE">Flexible</option></select></label>
                        </div>
                        <div style="overflow-x:auto"><table style="width:100%;min-width:680px;text-align:center"><thead><tr><th>Bloque</th>@foreach ($dias as $dia)<th scope="col">{{ $dia }}</th>@endforeach</tr></thead><tbody>
                        @foreach ($bloques as $bloque)<tr wire:key="editar-bloque-{{ $bloque->id_bloque }}"><th scope="row">{{ $bloque->hora_inicio }} – {{ $bloque->hora_fin }}</th>@foreach ($dias as $dia)
                            @php($clave = $bloque->id_bloque.':'.$dia)
                            <td><label class="gh-ui-cell" data-registro="{{ in_array($clave, $franjasEdicion, true) ? '1' : '0' }}"><input type="checkbox" value="{{ $clave }}" wire:model.live="franjasEdicion" aria-label="{{ $dia }} {{ $bloque->hora_inicio }}" @disabled($estadoEdicion !== 'PRESENTADA')><span class="sr-only">{{ $dia }} {{ $bloque->hora_inicio }}</span></label></td>
                        @endforeach</tr>@endforeach</tbody></table></div>
                        <p>Franjas seleccionadas: {{ count($franjasEdicion) }}</p>
                        <button type="button" class="institutional-secondary" wire:click="limpiarFranjas" wire:loading.attr="disabled">Limpiar selección</button>
                        @if ($estadoEdicion !== 'PRESENTADA' && $franjasEdicion)<p role="alert">Limpie las franjas antes de guardar este estado.</p>@endif
                        <p><label><input type="checkbox" wire:model.live="confirmarEdicion"> Confirmo que estoy modificando datos históricos de demostración.</label></p>
                        @foreach ($errors->all() as $error)<p class="quality-alert" role="alert">{{ $error }}</p>@endforeach
                        <div class="gh-ui-actions"><button type="submit" class="institutional-primary" wire:loading.attr="disabled" @disabled(!$confirmarEdicion || ($estadoEdicion !== 'PRESENTADA' && $franjasEdicion))>Guardar disponibilidad</button><button type="button" class="institutional-secondary" wire:click="cancelarEdicion" wire:loading.attr="disabled">Cancelar</button></div>
                    </form>
                </section>
            @endif
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="matriz-titulo">
                <header class="quality-panel-heading"><div><h2 id="matriz-titulo">Bloques por día</h2><p>«Registrado» indica un dato almacenado; no acredita disponibilidad efectiva.</p></div></header>
                @if ($bloques->isEmpty())<div class="quality-empty"><h3>No hay bloques horarios configurados</h3><p>Revise la configuración del periodo.</p></div>@else
                    <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Cuadrícula semanal de bloques"><table style="width:100%; min-width:760px; text-align:left"><caption class="sr-only">Bloques registrados de lunes a sábado</caption><thead><tr><th scope="col">Bloque</th>@foreach ($dias as $dia)<th scope="col">{{ $dia === 'SABADO' ? 'Sábado' : ucfirst(mb_strtolower($dia)) }}</th>@endforeach</tr></thead><tbody>
                        @foreach ($bloques as $bloque)<tr wire:key="bloque-{{ $bloque->id_bloque }}"><th scope="row">{{ $bloque->nombre }} · {{ $bloque->hora_inicio }} – {{ $bloque->hora_fin }}</th>@foreach ($dias as $dia)<td>@php($ghRegistro = $matriz[$bloque->id_bloque.':'.$dia] ?? 0)<span class="gh-ui-cell" data-registro="{{ $ghRegistro === 1 ? '1' : '0' }}" data-duplicado="{{ $ghRegistro > 1 ? '1' : '0' }}">{{ $ghRegistro > 1 ? 'Duplicado' : ($ghRegistro === 1 ? 'Registrado' : 'Sin registro') }}</span></td>@endforeach</tr>@endforeach
                    </tbody></table></div>
                @endif
                <p style="padding:1rem">Los cursos de repetición pueden programarse el sábado si el docente declaró disponibilidad compatible. Los cursos regulares requieren autorización específica.</p>
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
