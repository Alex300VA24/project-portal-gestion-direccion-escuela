<div @if (!$confirmacionAutomatica) wire:poll.60s @endif>
    <section class="quality-panel" style="padding:1rem; display:flex; flex-wrap:wrap; gap:1rem" aria-label="Filtros">
        <label>Plan de estudios<br><select class="quality-select" wire:model.live="plan"><option value="">Seleccione un plan</option>@foreach ($planes as $p)<option value="{{ $p->id_plan }}">{{ $p->codigo }} · {{ $p->nombre }}</option>@endforeach</select></label>
        <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
        <label>Ciclo<br><select class="quality-select" wire:model.live="ciclo" @disabled(!$planValido)><option value="">Todos</option>@foreach ($ciclos as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></label>
        <label>Buscar curso<br><input class="quality-select" type="search" wire:model.live.debounce.400ms="buscar" maxlength="120" placeholder="Código o asignatura"></label>
    </section>
    @if ($avisoPlan)<p role="status">{{ $avisoPlan }}</p>@endif
    @if ($mensajeAsociacion)<p class="auth-status" role="status">{{ $mensajeAsociacion }}</p>@endif
    @error('planAutomatico')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
    @foreach (['curso','seccion','codigo','capacidad','asociacion','periodo'] as $campo)
        @error($campo)<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
    @endforeach
    @if (!$planAutomatico)
        <div class="quality-empty"><h2>Seleccione el plan y periodo de integración</h2><p>La planificación automática de prueba está habilitada para PLAN-2018-INF y 2026-II.</p></div>
    @else
        @php
            $todas = collect($planAutomatico['cursos']);
            $pendientes = $todas->where('estado','pendiente');
            $revision = $todas->where('estado','revision');
            $visibles = $todas->whereIn('id_curso_plan', $cursos->pluck('id_curso_plan'))->filter(fn ($c) => $mostrarSinDemanda || $c['estado'] !== 'sin_demanda');
            $aforoAula = collect($planAutomatico['ambientes'])->filter(fn ($a) => in_array(strtoupper(trim($a['estado'])), ['ACTIVO','DISPONIBLE'], true) && strtoupper(trim($a['tipo'])) === 'AULA')->max('capacidad');
            $aforoLab = collect($planAutomatico['ambientes'])->filter(fn ($a) => in_array(strtoupper(trim($a['estado'])), ['ACTIVO','DISPONIBLE'], true) && in_array(strtoupper(trim($a['tipo'])), ['LAB','LABORATORIO']))->max('capacidad');
        @endphp
        <section class="quality-panel" style="margin-top:1rem;padding:1rem" aria-labelledby="gh-auto-resumen">
            <header><h2 id="gh-auto-resumen">Planificación por demanda</h2><p>La propuesta se calcula desde Prematrícula y las capacidades de los ambientes. No necesita introducir plazas manualmente.</p></header>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:.75rem;margin:1rem 0">
                @foreach ([['Cursos con demanda',$todas->filter(fn ($c)=>$c['total']>0)->count()],['Solicitudes estimadas',$todas->sum('total')],['Secciones registradas',$todas->sum(fn ($c)=>count($c['actuales']))],['Cursos por actualizar',$pendientes->count()]] as [$titulo,$valor])
                    <div style="border:1px solid #d7dcd5;border-radius:.6rem;padding:.75rem"><small>{{ $titulo }}</small><br><strong style="font-size:1.5rem">{{ $valor }}</strong></div>
                @endforeach
            </div>
            <p>Demanda {{ $planAutomatico['version'] !== null ? 'v'.$planAutomatico['version'] : 'no recibida' }} @if ($planAutomatico['recibida_en']) · Última recepción: {{ $planAutomatico['recibida_en'] }} @endif</p>
            <p>Aforo máximo de referencia: aula <strong>{{ $aforoAula ?? 'No disponible' }}</strong> · laboratorio <strong>{{ $aforoLab ?? 'No disponible' }}</strong>.</p>
            <small>Recursos históricos de QA; los totales no son matrícula definitiva. La propuesta de población y grupos LAB no asigna alumnos.</small>
            <p role="status"><strong>Etapa:</strong> {{ match($planAutomatico['estadoConsolidacion']) { 'prematricula_abierta' => 'Prematrícula sin cierre confirmado · solo consulta', 'sin_consolidar' => 'Cierre confirmado · falta consolidar vacantes', 'consolidada' => 'Vacantes consolidadas · secciones pendientes', 'secciones_verificadas' => 'Secciones verificadas para la demanda consolidada', default => 'Revisión requerida' } }}</p>
            @if ($planAutomatico['bloqueo'])<p class="quality-alert" role="alert">{{ $planAutomatico['bloqueo'] }}</p>@endif
            @if ($revision->isNotEmpty())<p role="status">{{ $revision->count() }} cursos requieren revisión. No se modificarán automáticamente.</p>@endif
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;align-items:center">
                @if ($escrituraDisponible && !$planAutomatico['bloqueo'] && $planAutomatico['estadoConsolidacion'] === 'sin_consolidar')
                    <button class="institutional-primary" type="button" wire:click="consolidarVacantes('{{ $planAutomatico['huella'] }}')" wire:confirm="¿Confirma las vacantes propuestas para todo el plan?" wire:loading.attr="disabled">Consolidar vacantes del plan</button>
                @endif
                @if ($escrituraDisponible && !$planAutomatico['bloqueo'] && $planAutomatico['estadoConsolidacion'] === 'consolidada' && $pendientes->isNotEmpty())
                    <button class="institutional-primary" type="button" wire:click="revisarPlanAutomatico" wire:loading.attr="disabled">Revisar y crear secciones del plan</button>
                @endif
                <button class="institutional-secondary" type="button" wire:click="$refresh" wire:loading.attr="disabled">Actualizar consulta</button>
                <a href="{{ route('gestion-horarios.oferta',['plan'=>$plan,'periodo'=>$periodo]) }}">Ver oferta académica</a>
            </div>
            <p wire:loading role="status">Actualizando información…</p>
        </section>
        @if ($confirmacionAutomatica)
            <section class="quality-panel" style="padding:1rem;margin-top:1rem" aria-label="Confirmar planificación">
                <h2>Confirmar cambios del plan completo</h2><p>Versión {{ $confirmacionAutomatica['version'] }}. Se aplicarán los cursos indicados, incluso si no están visibles por el filtro. Los cursos en revisión quedarán intactos.</p>
                <ul>@foreach ($confirmacionAutomatica['cursos'] as $c)<li>{{ $c['codigo'] }} · {{ $c['nombre'] }}: @foreach ($c['propuesta'] as $s) @if ($s['accion'] !== 'conservar') {{ $s['codigo'] }} ({{ $s['accion'] === 'crear' ? 'crear' : 'ajustar' }}, {{ $s['capacidad'] }} plazas); @endif @endforeach</li>@endforeach</ul>
                <button type="button" class="institutional-primary" wire:click="aplicarPlanAutomatico" wire:loading.attr="disabled">Confirmar cambios</button>
                <button type="button" class="institutional-secondary" wire:click="cancelarPlanAutomatico">Cancelar</button>
            </section>
        @endif
        <section class="quality-panel" style="margin-top:1rem;padding:1rem" aria-labelledby="gh-auto-cursos">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem"><h2 id="gh-auto-cursos">Cursos y secciones</h2><label><input type="checkbox" wire:model.live="mostrarSinDemanda"> Mostrar cursos sin demanda</label></div>
            @if ($visibles->isEmpty())<p role="status">No hay cursos para los filtros seleccionados.</p>@endif
            @foreach ($visibles->groupBy('ciclo') as $numero => $grupo)
                <h3 style="margin:1.2rem 0 .5rem">Ciclo {{ $numero }}</h3>
                <div style="overflow-x:auto"><table style="min-width:650px;width:100%;border-collapse:collapse;text-align:left">
                    <thead><tr><th>Curso</th><th>Demanda</th><th>Actuales</th><th>Mínimo</th><th>Propuesta</th><th>Estado</th></tr></thead>
                    <tbody>@foreach ($grupo as $c)<tr wire:key="auto-curso-{{ $c['id_curso_plan'] }}" style="border-top:1px solid #d7dcd5">
                        <td style="padding:.7rem .3rem"><strong>{{ $c['codigo'] }}</strong><br>{{ $c['nombre'] }}</td>
                        <td>{{ $c['total'] ?? '—' }} @if ($c['original'] !== $c['total'])<small>(origen {{ $c['original'] }})</small>@endif</td>
                        <td>{{ count($c['actuales']) }} @if ($c['actuales'])<br><small>{{ collect($c['actuales'])->pluck('codigo')->join(', ') }}</small>@endif</td>
                        <td>{{ $c['minimo'] ?? '—' }}</td>
                        <td>@if ($c['propuesta'])<details><summary>{{ count($c['propuesta']) }} secciones · {{ $c['aforo'] }} plazas c/u</summary>@foreach ($c['propuesta'] as $s)<p><strong>{{ $s['codigo'] }}</strong> · {{ $s['poblacion_estimada'] }} estudiantes estimados @if ($s['grupos_lab'])<br>LAB: {{ implode(' + ', $s['grupos_lab']) }} @endif</p>@endforeach</details>@else — @endif</td>
                        <td>@switch($c['estado']) @case('cubierta') <span style="color:#0d7a50">Cubierta</span> @break @case('pendiente') Por actualizar @break @case('sin_demanda') Sin demanda @break @default Revisión necesaria <br><small>{{ $c['motivo'] }}</small> @endswitch</td>
                    </tr>@endforeach</tbody>
                </table></div>
            @endforeach
        </section>
        <div style="margin-top:1rem">
            @if ($planAutomatico['estadoConsolidacion'] === 'secciones_verificadas')
                <p role="status">Secciones cubiertas. La asignación institucional por actividad T/P/L todavía requiere integración y verificación de subgrupos.</p>
            @else
                <p role="status">La asignación docente permanece bloqueada hasta consolidar vacantes y verificar las secciones.</p>
            @endif
        </div>
    @endif
</div>
