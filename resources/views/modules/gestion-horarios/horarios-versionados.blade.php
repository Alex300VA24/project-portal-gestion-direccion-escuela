<div class="quality-page gh-ui" style="padding:2rem 1rem;margin:0 auto;max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Elaboración de horarios', 'subtitulo'=>null, 'descripcion'=>match ($vistaHorario) { 'docente' => 'Consulta la planificación por docente.', 'ambiente' => 'Consulta la ocupación de aulas y laboratorios.', default => 'Elabora y consulta horarios por ciclo y sección.' }])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'elaboracion', 'workspaceSub' => 'horario', 'workspacePlan' => session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => session('gestion_horarios.periodo_seleccionado', '')])
    <p class="gh-ui-note">Demostración local. Los cambios académicos validados actualizan el mismo horario. Si no hay cambios, se conserva la versión. La publicación institucional aún no está habilitada.</p>
    @foreach ($errors->all() as $error)<p class="quality-alert" role="alert">{{ $error }}</p>@endforeach
    @if ($mensaje)<p class="gh-ui-success" role="status">{{ $mensaje }}</p>@endif
    @if (!$disponible)
        <div class="quality-empty"><h2>Horario de trabajo no registrado</h2><p>Solicite la configuración inicial del horario a soporte.</p></div>
    @else
        <nav class="gh-subnav gh-horario-vistas" aria-label="Formas de consultar el horario">
            @foreach (['ciclo'=>'Por ciclo y sección','docente'=>'Por docente','ambiente'=>'Por ambiente'] as $clave => $etiqueta)
                <button type="button" wire:click="cambiarVista('{{ $clave }}')" aria-pressed="{{ $vistaHorario === $clave ? 'true' : 'false' }}" wire:loading.attr="disabled" wire:target="cambiarVista">{{ $etiqueta }}</button>
            @endforeach
        </nav>
        <div class="gh-ui-actions"><span>Periodo 2026-II · En elaboración</span><a class="institutional-secondary" href="{{ route('gestion-horarios.periodos', ['plan'=>session('gestion_horarios.workspace.plan',''), 'periodo'=>session('gestion_horarios.periodo_seleccionado','')]) }}">Calendario académico</a><a class="institutional-secondary" href="{{ route('gestion-horarios.restricciones', ['plan'=>session('gestion_horarios.workspace.plan',''), 'periodo'=>session('gestion_horarios.periodo_seleccionado','')]) }}">Restricciones académicas</a></div>
        <style>
            .gh-ui .gh-horario-vistas button { border:0; background:white; color:#355449; font-weight:500; padding:.62rem .85rem; box-shadow:none; }
            .gh-ui .gh-horario-vistas button[aria-pressed="true"] { background:#e7f1e9; color:#103e30; font-weight:700; }
        </style>
        @include('modules.gestion-horarios.generacion-cpsat-qa')
        @if ($actual)
            <section class="quality-panel" style="padding:1.25rem;margin-top:1rem">
                <h2>{{ $actual['nombre'] }} · Versión {{ $version }}</h2>
                @if ($version === $ultima)
                    <details class="gh-ui-group"><summary>Configurar nombre del horario</summary><div style="padding:.8rem"><form wire:submit="renombrar" class="gh-ui-context"><label>Nombre<input class="quality-select" wire:model="nombre" maxlength="100" required></label><button type="submit" class="institutional-primary" wire:loading.attr="disabled">Guardar configuración</button></form></div></details>
                @endif
                @if (isset($datos['cpsat_qa']))
                    <p class="gh-ui-success" role="status">Horario generado · {{ $datos['cpsat_qa']['sesiones'] }} sesiones verificadas.</p>
                    <p class="gh-ui-note">Se verificaron las reglas con los datos disponibles en esa ejecución. Si modificó recursos o restricciones, revise los datos y vuelva a generar. No representa un horario institucional.</p>
                @endif
                <div class="gh-ui-metrics"><div class="gh-ui-metric"><strong>{{ count($datos['actividades']) }}</strong><small>Actividades de origen</small></div><div class="gh-ui-metric"><strong>{{ count($datos['sesiones']) }}</strong><small>Sesiones programadas</small></div><div class="gh-ui-metric"><strong>{{ count($datos['actividades']) - count($datos['sesiones']) }}</strong><small>Pendientes</small></div></div>
                @if (count($datos['sesiones']))
                    @php($exportarFiltros = match ($vistaHorario) { 'docente'=>['vista'=>'docente','docente'=>$docenteFiltro], 'ambiente'=>['vista'=>'ambiente','ambiente'=>$ambienteFiltro], default=>['vista'=>'ciclo','ciclo'=>$ciclo,'seccion'=>$seccion,'curso'=>$cursoFiltro] })
                    <div class="gh-ui-actions" aria-label="Exportar horario">
                        @if (($vistaHorario === 'ciclo' || ($vistaHorario === 'docente' && $docenteFiltro !== '') || ($vistaHorario === 'ambiente' && $ambienteFiltro !== '')) && count($actividades))
                            <a class="institutional-secondary" href="{{ route('gestion-horarios.exportar-trabajo-qa',array_merge(['version'=>$version,'formato'=>'pdf'], array_filter($exportarFiltros,fn($v)=>$v!==''))) }}">Descargar PDF de la consulta</a>
                            <a class="institutional-secondary" href="{{ route('gestion-horarios.exportar-trabajo-qa',array_merge(['version'=>$version,'formato'=>'xlsx'], array_filter($exportarFiltros,fn($v)=>$v!==''))) }}">Descargar Excel de la consulta</a>
                        @endif
                        <a class="institutional-secondary" href="{{ route('gestion-horarios.exportar-trabajo-qa',['version'=>$version,'formato'=>'pdf']) }}">PDF completo</a>
                        <a class="institutional-secondary" href="{{ route('gestion-horarios.exportar-trabajo-qa',['version'=>$version,'formato'=>'xlsx']) }}">Excel completo</a>
                    </div>
                @endif
                @if ($version !== $ultima)<p class="quality-alert" role="status">Está consultando una versión anterior. Abra la última para editar.</p>@endif
                <div class="gh-ui-actions">
                    @if ($version > 1)<button type="button" class="institutional-secondary" wire:click="abrir('{{ $propuesta }}', {{ $version-1 }})">Versión anterior</button>@endif
                    @if ($version < $ultima)<button type="button" class="institutional-secondary" wire:click="abrir('{{ $propuesta }}', {{ $version+1 }})">Versión siguiente</button><button type="button" class="institutional-primary" wire:click="abrir('{{ $propuesta }}')">Última versión</button>@endif
                </div>
                @if ($version > 1)<details class="gh-ui-group" @if ($compararVersion !== '') open @endif><summary>Comparar con una versión anterior</summary><div style="padding:.8rem"><label>Versión<select class="quality-select" wire:model.live="compararVersion"><option value="">Sin comparación</option>@for ($v=1;$v<$version;$v++)<option value="{{ $v }}">V{{ $v }}</option>@endfor</select></label></div></details>@endif
                @if ($compararVersion !== '' && (int)$compararVersion >= 1 && (int)$compararVersion < $version && ctype_digit($compararVersion)) @if (!$cambios)<p class="gh-ui-note">La programación coincide con V{{ $compararVersion }}. El historial existente se conserva.</p>@else<details open><summary>{{ count($cambios) }} cambios de programación respecto a V{{ $compararVersion }}</summary><ul>@foreach ($cambios as $c)<li>Actividad {{ $c['id'] }}: {{ $c['antes'] ? $c['antes']['dia'].' '.\App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($c['antes']['inicio']) : 'Pendiente' }} → {{ $c['despues'] ? $c['despues']['dia'].' '.\App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($c['despues']['inicio']) : 'Pendiente' }}</li>@endforeach</ul></details>@endif @endif
                @include('modules.gestion-horarios.cuadricula-horario-qa')
                @if (count($pendientes))
                <details class="gh-ui-group"><summary>Sesiones pendientes ({{ count($pendientes) }})</summary>
                <div style="overflow-x:auto"><table style="width:100%;text-align:left"><thead><tr><th>Curso / sección</th><th>Actividad</th><th>Docente</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                @forelse ($pendientes as $a)
                    @php($s = $secciones[$a['seccion_id']])
                    @php($franja = $datos['sesiones'][$a['id']] ?? null)
                    <tr wire:key="actividad-{{ $a['id'] }}"><td>{{ $s['curso'] }} · {{ $s['nombre'] }}<br><small>Ciclo {{ $s['ciclo'] }} / {{ $s['seccion'] }}</small></td><td>{{ $a['tipo'] }} · {{ $a['duracion'] }} min @if (!empty($a['grupo'])) · {{ $a['grupo'] }} @endif</td><td>{{ implode(', ', array_map(fn ($codigo) => ($nombresDocentes[$codigo] ?? '') ?: 'Docente sin nombre conciliado', array_column($a['docentes'], 'codigo_docente'))) ?: 'Sin asignación' }}</td><td>{{ $franja ? $franja['dia'].' '.\App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($franja['inicio']).'–'.\App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($franja['fin']) : 'Pendiente' }} @if (!empty($franja['ambiente']))<br><small>{{ $nombresAmbientes[$franja['ambiente']] ?? $franja['ambiente'] }}</small>@endif</td><td>@if (isset($datos['cpsat_qa']))<small>Solo consulta</small>@else<button type="button" class="institutional-secondary" wire:click="seleccionar({{ $a['id'] }})" @disabled($version !== $ultima) wire:loading.attr="disabled">{{ $franja ? 'Mover' : 'Programar' }}</button>@endif</td></tr>
                    @if (!isset($datos['cpsat_qa']) && $actividad === (string) $a['id'] && $version === $ultima)
                        <tr wire:key="editar-{{ $a['id'] }}"><td colspan="5"><form wire:submit="guardar" class="gh-ui-context"><label>Día<select class="quality-select" wire:model="dia" required><option value="">Seleccione</option>@foreach (\App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::DIAS as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach</select></label><label>Inicio<select class="quality-select" wire:model="inicio" required><option value="">Seleccione</option>@foreach ($datos['bloques'] as $b)<option value="{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($b[0]) }}">{{ \App\Modules\GestionHorarios\Services\HorariosVersionadosDemo::hora($b[0]) }}</option>@endforeach</select></label><button class="institutional-primary" type="submit" wire:loading.attr="disabled">Guardar cambios</button>@if ($franja)<button class="institutional-secondary" type="button" wire:click="retirar" wire:loading.attr="disabled">Dejar pendiente</button>@endif<button class="institutional-secondary" type="button" wire:click="$set('actividad', '')">Cancelar</button></form></td></tr>
                    @endif
                @empty<tr><td colspan="5">No hay actividades en este filtro.</td></tr>@endforelse
                </tbody></table></div></details>
                @else
                    @if (count($actividades))<p class="gh-ui-success">No hay sesiones pendientes en esta consulta.</p>@endif
                @endif
            </section>
        @endif
    @endif
</div>
