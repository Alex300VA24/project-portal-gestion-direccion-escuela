<div class="quality-page gh-ui" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Preparación académica', 'subtitulo'=>'Restricciones académicas', 'descripcion'=>'Configure las franjas que deben respetarse al generar o editar el horario.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'preparacion', 'workspaceSub' => 'restricciones', 'workspacePlan' => $plan ?? session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => $periodo ?? session('gestion_horarios.periodo_seleccionado', '')])
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudieron consultar las restricciones. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros"><div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
            <label>Estado<br><select class="quality-select" wire:model.live="estado"><option value="">Todas</option><option value="activa">Activas</option><option value="inactiva">Inactivas</option></select></label>
        </div></section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if ($periodoValido)
            <section class="quality-panel" style="padding:1.25rem;margin-top:1rem" aria-label="Bloqueos del periodo">
                <header class="quality-panel-heading"><h2>Restricciones del periodo</h2><p>Impida que se programen actividades en determinadas franjas.</p><button type="button" class="institutional-primary" wire:click="nuevo">+ Nueva restricción</button></header>
                @if ($mensaje)<p class="gh-ui-success" role="status">{{ $mensaje }}</p>@endif
                @foreach ($errors->all() as $error)<p class="quality-alert" role="alert">{{ $error }}</p>@endforeach
                @if ($formulario)
                    <form wire:submit="guardarBloqueo" class="gh-ui-editor" aria-label="Formulario de restricción">
                        <h3>{{ $idEdicion ? 'Editar restricción' : 'Nueva restricción' }}</h3>
                        <label>Motivo<input class="quality-select" wire:model="motivo" maxlength="120" required placeholder="Ej. Reunión de docentes"></label>
                        <div class="gh-ui-context">
                            <label>Aplicar a<select class="quality-select" wire:model.live="alcance"><option value="GLOBAL">Todo el periodo</option><option value="CICLO">Ciclo (incluye repetición)</option><option value="DOCENTE">Docente</option><option value="AMBIENTE">Aula o laboratorio</option></select></label>
                            @if ($alcance === 'CICLO')<label>Ciclo<select class="quality-select" wire:model="objetivo" required><option value="">Seleccione</option>@for($c=1;$c<=10;$c++)<option value="{{ $c }}">{{ $c }}</option>@endfor</select></label>
                            @elseif ($alcance === 'DOCENTE')@include('modules.gestion-horarios.selector-horario-buscable',['campo'=>'objetivo','etiqueta'=>'Docente','placeholder'=>'Buscar o seleccionar docente','opciones'=>$docentes])
                            @elseif ($alcance === 'AMBIENTE')@include('modules.gestion-horarios.selector-horario-buscable',['campo'=>'objetivo','etiqueta'=>'Ambiente','placeholder'=>'Buscar aula o laboratorio','opciones'=>$ambientes])@endif
                            <label>Día<select class="quality-select" wire:model="dia">@foreach (\App\Modules\GestionHorarios\Services\BloqueosAcademicosDemo::DIAS as $d)<option value="{{ $d }}">{{ $d === 'SABADO' ? 'Sábado' : ucfirst(strtolower($d)) }}</option>@endforeach</select></label>
                            <label>Desde<select class="quality-select" wire:model="inicioBloqueo">@for($h=7;$h<20;$h++)<option value="{{ $h }}">{{ sprintf('%02d:00',$h) }}</option>@endfor</select></label>
                            <label>Hasta<select class="quality-select" wire:model="finBloqueo">@for($h=8;$h<=20;$h++)<option value="{{ $h }}">{{ sprintf('%02d:00',$h) }}</option>@endfor</select></label>
                        </div>
                        <p>Tipo: Obligatoria. La restricción se aplica en la siguiente generación y en la validación de movimientos.</p>
                        <div class="gh-ui-actions"><button type="submit" class="institutional-primary" wire:loading.attr="disabled">Guardar restricción</button><button type="button" class="institutional-secondary" wire:click="cancelar">Cancelar</button></div>
                    </form>
                @endif
                @if ($bloqueos->isEmpty())<p class="quality-empty">No hay restricciones personalizadas para este filtro.</p>@else
                    <div class="gh-catalogo-tabla"><table><thead><tr><th>Motivo</th><th>Alcance</th><th>Franja</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                    @foreach ($bloqueos as $b)
                        @php($p = json_decode($b->parametros,true))
                        <tr wire:key="bloqueo-{{ $b->id_restriccion }}"><td>{{ $b->nombre }}</td><td>{{ match($p['alcance']) {'GLOBAL'=>'Todo el periodo','CICLO'=>'Ciclo '.$p['objetivo'],'DOCENTE'=>$docentes[$p['objetivo']]??'Docente no disponible','AMBIENTE'=>$ambientes[$p['objetivo']]??'Ambiente no disponible',default=>'Sin identificar'} }}</td><td>{{ $p['dia'] }} · {{ sprintf('%02d:00–%02d:00',$p['inicio'],$p['fin']) }}</td><td>{{ $b->activa?'Activa':'Inactiva' }}</td><td><div class="gh-ui-actions"><button type="button" wire:click="editar({{ $b->id_restriccion }})">Editar</button><button type="button" wire:click="alternarBloqueo({{ $b->id_restriccion }}, '{{ \App\Modules\GestionHorarios\Services\BloqueosAcademicosDemo::huella($b) }}')" wire:loading.attr="disabled">{{ $b->activa?'Desactivar':'Activar' }}</button></div></td></tr>
                    @endforeach</tbody></table></div>
                @endif
                <p><a class="quality-open-link" href="{{ route('gestion-horarios.borradores-qa') }}#gh-generacion-titulo">Revisar y generar horario</a></p>
            </section>
            <section class="quality-panel" style="padding:1.25rem;margin-top:1rem"><h2>Reglas académicas incorporadas</h2><p>Se mantienen las restricciones de cruces, disponibilidad, capacidades, uso de aulas y laboratorios y grupos. Los cursos de repetición pueden programarse el sábado con disponibilidad declarada. Las reglas de base no se modifican desde este formulario.</p><p>La configuración de preferencias adicionales todavía no está disponible.</p></section>
        @else<p class="quality-empty">Seleccione un periodo académico.</p>@endif
        <section class="quality-panel" style="margin-top:1rem" aria-labelledby="reglas-titulo">
            <header class="quality-panel-heading"><div><h2 id="reglas-titulo">Otras reglas registradas</h2><p>Las reglas de docentes y ambientes no tienen periodo explícito en el modelo. La relación con curso o sección aún no está representada.</p></div></header>
            @if ($restricciones->isEmpty())<p>No hay otras reglas registradas.</p>@else
                <div style="overflow-x:auto" tabindex="0" role="region" aria-label="Tabla de restricciones"><table style="width:100%; min-width:760px; text-align:left"><thead><tr><th>Código / regla</th><th>Descripción</th><th>Severidad</th><th>Estado</th><th>Alcance</th></tr></thead><tbody>
                    @foreach ($restricciones as $r)<tr wire:key="restriccion-{{ $r->id_restriccion }}-{{ $r->id_detalle_restriccion ?? 'sin-alcance' }}"><td><span class="quality-code">{{ $r->codigo }}</span> {{ $r->nombre }}</td><td>{{ $r->descripcion }}</td><td>{{ $r->severidad === 'DURA' ? 'Obligatoria (DURA)' : ($r->severidad === 'BLANDA' ? 'Preferencia (BLANDA)' : 'No identificada') }}</td><td>{{ $r->activa ? 'Activa' : 'Inactiva' }}</td><td>{{ $r->ambito ?? 'Sin alcance registrado' }} @if ($r->ambito === 'DOCENTE') · {{ $r->codigo_docente ?: 'Docente no identificado' }} {{ trim(($r->nombres ?? '').' '.($r->apellido_paterno ?? '').' '.($r->apellido_materno ?? '')) }} @elseif ($r->ambito === 'AMBIENTE') · {{ $r->codigo_ambiente ?: 'Ambiente no identificado' }} {{ $r->nombre_ambiente }} @elseif ($r->ambito === 'PERIODO') · Periodo seleccionado @endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>
    @endif
</div>
