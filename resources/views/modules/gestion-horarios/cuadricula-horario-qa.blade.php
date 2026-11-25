{{-- Cuadrícula del horario único; un movimiento solo se persiste después de validación. --}}
<style>
.gh-ui .gh-grid-week { display:flex; overflow-x:auto; border:1px solid var(--gh-ui-border); border-radius:10px; background:var(--gh-ui-surface,#fff); margin:1rem 0; }
.gh-ui .gh-grid-week * { box-sizing:border-box; }
.gh-ui .gh-grid-time { flex:0 0 112px; position:sticky; left:0; z-index:3; background:var(--gh-ui-soft,#eef0f3); }
.gh-ui .gh-grid-day { flex:1 0 auto; min-width:0; border-left:1px solid var(--gh-ui-border); }
.gh-ui .gh-grid-head { height:44px; display:flex; align-items:center; justify-content:center; font-weight:700; background:var(--portal-navy,#17224d);color:#fff; border-bottom:1px solid var(--gh-ui-border); }
.gh-ui .gh-grid-track { position:relative; background:repeating-linear-gradient(to bottom, transparent 0 95px,var(--gh-ui-border,#dfe3ea) 95px 96px); }
.gh-ui .gh-grid-hour { height:96px; display:flex; align-items:center; justify-content:center; text-align:center; font-variant-numeric:tabular-nums; padding:4px; font-size:.8rem; border-bottom:1px solid var(--gh-ui-border); }
.gh-ui .gh-grid-week button.gh-grid-event { position:absolute; display:block; text-align:left; padding:5px 7px; min-height:0; border:1px solid #a9c4b6; border-left:4px solid #267357; border-radius:6px; color:#141a33; background:#eaf4ed; font-size:.76rem; line-height:1.18; overflow:hidden; }
.gh-ui .gh-grid-week button.gh-grid-event[data-tipo="PRACTICA"] { background:#f9efdf; border-color:#d9bc83; border-left-color:#9c721e; }
.gh-ui .gh-grid-week button.gh-grid-event[data-tipo="LABORATORIO"] { background:#e8eff9; border-color:#afc4de; border-left-color:#416c9d; }
.gh-ui .gh-grid-event strong,.gh-ui .gh-grid-event small { display:block; overflow-wrap:anywhere; }
.gh-ui .gh-grid-event small { font-size:.72rem; }
.gh-ui .gh-grid-break { position:absolute; width:100%; background:#e9eceb; text-align:center; color:#526176; font-size:.8rem; display:flex; align-items:center; justify-content:center; }

</style>
<h3>Horario semanal</h3>
<div class="gh-ui-context" aria-label="Filtros del horario semanal" wire:key="filtros-horario-{{ $vistaHorario }}">
    @if ($vistaHorario === 'ciclo')
        <label>Ciclo<select class="quality-select" wire:model.live="ciclo"><option value="">Todos</option>@foreach (collect($secciones)->pluck('ciclo')->unique()->sort() as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></label>
        <label>Sección<select class="quality-select" wire:model.live="seccion"><option value="">Todas</option>@foreach ($seccionesFiltro as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></label>
        <label>Curso<select class="quality-select" wire:model.live="cursoFiltro"><option value="">Todos</option>@foreach ($cursosFiltro as $codigo => $nombre)<option value="{{ $codigo }}">{{ $nombre }}</option>@endforeach</select></label>
    @elseif ($vistaHorario === 'docente')
        @include('modules.gestion-horarios.selector-horario-buscable', ['campo' => 'docenteFiltro', 'etiqueta' => 'Docente', 'placeholder' => 'Buscar docente por nombre', 'opciones' => $docentesFiltro])
    @else
        @include('modules.gestion-horarios.selector-horario-buscable', ['campo' => 'ambienteFiltro', 'etiqueta' => 'Ambiente', 'placeholder' => 'Buscar aula o laboratorio', 'opciones' => $ambientesFiltro])
    @endif
    <div class="gh-ui-actions"><button type="button" class="institutional-secondary" wire:click="limpiarFiltros">Limpiar filtros</button></div>
</div>
<p><small>Teoría · Práctica · Laboratorio se distinguen por color y etiqueta. Los carriles paralelos muestran coincidencias temporales, no necesariamente conflictos. Seleccione una sesión para consultar su detalle. @if ($edicionDisponible) Arrastre una sesión a otra franja o utilice el formulario para preparar el movimiento. @endif</small></p>
@if ($detalle && isset($datos['sesiones'][$detalle['id']]))
    @php($detalleFranja = $datos['sesiones'][$detalle['id']])
    @php($detalleSeccion = $secciones[$detalle['seccion_id']])
    <section class="gh-ui-editor" aria-label="Detalle de sesión seleccionada" aria-live="polite">
        <h3>{{ $detalleSeccion['nombre'] }}</h3>
        <p>Ciclo {{ $detalleSeccion['ciclo'] }} · Sección {{ $detalleSeccion['seccion'] }} · {{ $detalle['tipo'] }} @if (!empty($detalle['grupo'])) · {{ $detalle['grupo'] }} @endif</p>
        <p>{{ $detalleFranja['dia'] }} {{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($detalleFranja['inicio']) }}–{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($detalleFranja['fin']) }} · {{ $nombresAmbientes[$detalleFranja['ambiente'] ?? ''] ?? $detalleFranja['ambiente'] ?? 'Sin ambiente registrado' }}</p>
        <p>Docente: {{ implode(', ', array_map(fn ($codigo) => ($nombresDocentes[$codigo] ?? '') ?: 'Docente sin nombre conciliado', array_column($detalle['docentes'], 'codigo_docente'))) ?: 'Sin asignación' }}</p>
        @if ($edicionDisponible && $actividad === (string)$detalle['id'])
            <form wire:submit="guardar" class="gh-ui-context">
                <label>Día<select class="quality-select" wire:model="dia" required>@foreach (\App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::DIAS as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach</select></label>
                <label>Inicio<select class="quality-select" wire:model="inicio" required>@foreach ($datos['bloques'] as $b)<option value="{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($b[0]) }}">{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($b[0]) }}</option>@endforeach</select></label>
                @if (isset($datos['cpsat_qa']))<label>Ambiente<select class="quality-select" wire:model="ambienteEdicion"><option value="">Sin ambiente compartido</option>@foreach (\App\Modules\GestionHorarios\Services\ConsultaSemanalDemo::opciones($nombresAmbientes) as $codigo=>$nombre)<option value="{{ $codigo }}">{{ $nombre }}</option>@endforeach</select></label>@endif
                <div class="gh-ui-actions"><button type="submit" class="institutional-primary" wire:loading.attr="disabled" wire:target="guardar">Validar y guardar</button><span wire:loading wire:target="guardar" role="status">Validando el movimiento…</span></div>
                @error('sesion')<p role="alert" class="quality-alert">{{ $message }}</p>@enderror
            </form>
        @elseif ($edicionDisponible)
            <button type="button" class="institutional-primary" wire:click="seleccionar({{ $detalle['id'] }})">Editar sesión</button>
        @endif
        <button type="button" class="institutional-secondary" wire:click="cerrarDetalle">Cerrar detalle</button>
    </section>
@endif
@if (!$actividades)
    <p class="quality-empty">{{ $vistaHorario === 'docente' && $docenteFiltro === '' ? 'Seleccione un docente para consultar su horario.' : ($vistaHorario === 'ambiente' && $ambienteFiltro === '' ? 'Seleccione un aula o laboratorio para consultar su ocupación.' : 'No hay actividades para los filtros seleccionados.') }}</p>
@else
    @php($altura = ($cuadricula['hasta'] - $cuadricula['desde']) * 1.6)
    <div class="gh-grid-week" role="region" aria-label="Cuadrícula semanal desplazable" tabindex="0">
        <div class="gh-grid-time"><div class="gh-grid-head">HORA</div>@for ($h = $cuadricula['desde']; $h < $cuadricula['hasta']; $h += 60)<div class="gh-grid-hour">{{ \App\Modules\GestionHorarios\Services\DistribucionHoraria::rango12($h, $h + 60) }}</div>@endfor</div>
        @foreach ($cuadricula['dias'] as $dia => $col)
            <div class="gh-grid-day" style="width:{{ $col['carriles'] * 158 }}px">
                <div class="gh-grid-head">{{ $dia }}</div>
                <div class="gh-grid-track" style="height:{{ $altura }}px" @if ($edicionDisponible) x-data x-on:dragover.prevent x-on:drop.prevent="const id=Number($event.dataTransfer.getData('text/plain')); const inicio={{ $cuadricula['desde'] }}+Math.floor(($event.clientY-$el.getBoundingClientRect().top)/96)*60; if(Number.isInteger(id)&&id>0&&inicio>={{ $cuadricula['desde'] }}&&inicio<{{ $cuadricula['hasta'] }}) $wire.previsualizarMovimiento(id,'{{ $dia }}',inicio)" @endif>
                    @if ($cuadricula['desde'] <= 780 && $cuadricula['hasta'] >= 840 && !collect($datos['bloques'])->contains(fn ($b) => $b[0] < 840 && $b[1] > 780))
                        <div class="gh-grid-break" style="top:{{ (780 - $cuadricula['desde']) * 1.6 }}px;height:96px">Receso</div>
                    @endif
                    @foreach ($col['filas'] as $fila)
                        @php($a = $fila['actividad'])
                        @php($s = $fila['sesion'])
                        @php($sec = $secciones[$a['seccion_id']])
                        @php($doc = implode(', ', array_map(fn ($codigo) => ($nombresDocentes[$codigo] ?? '') ?: 'Docente sin nombre conciliado', array_column($a['docentes'], 'codigo_docente'))) ?: 'Sin asignación')
                        <button type="button" wire:key="cuadricula-{{ $version }}-{{ $a['id'] }}" wire:click="consultarSesion({{ $a['id'] }})" class="gh-grid-event" @if ($edicionDisponible) draggable="true" x-data x-on:dragstart="$event.dataTransfer.setData('text/plain','{{ $a['id'] }}'); $event.dataTransfer.effectAllowed='move'" @endif data-tipo="{{ $a['tipo'] }}" title="{{ $sec['nombre'] }} · {{ $doc }} · {{ $nombresAmbientes[$s['ambiente'] ?? ''] ?? $s['ambiente'] ?? 'Sin ambiente registrado' }}" style="top:{{ ($s['inicio'] - $cuadricula['desde']) * 1.6 + 2 }}px;height:{{ ($s['fin'] - $s['inicio']) * 1.6 - 4 }}px;left:calc({{ $fila['carril'] / $col['carriles'] * 100 }}% + 2px);width:calc({{ 100 / $col['carriles'] }}% - 4px)">
                            <small>{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($s['inicio']) }}–{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($s['fin']) }}</small><strong>{{ $sec['nombre'] }}</strong><small>{{ $a['tipo'] }} · {{ $sec['ciclo'] }}/{{ $sec['seccion'] }} @if (!empty($a['grupo'])) · {{ $a['grupo'] }} @endif</small><small>{{ $doc }}</small><small>{{ $nombresAmbientes[$s['ambiente'] ?? ''] ?? $s['ambiente'] ?? 'Sin ambiente registrado' }}</small>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
<p class="gh-ui-note">El sábado está sujeto a la política académica y disponibilidad declarada. @if ($edicionDisponible) Los movimientos no se guardan hasta superar la validación. @else Consulta de la versión {{ $version }}. La edición no está disponible en esta vista. @endif</p>
