<div class="quality-page gh-ui" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Elaboración de horarios', 'subtitulo'=>'Periodos académicos', 'descripcion'=>'Consulte los periodos institucionales y la configuración disponible para horarios.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'elaboracion', 'workspaceSub' => 'calendario', 'workspacePlan' => $plan ?? session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => $periodo ?? session('gestion_horarios.periodo_seleccionado', '')])
    @if ($error)
        <div class="quality-alert" role="alert">No se pudo consultar la información de periodos. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" style="padding:1rem" aria-label="Filtros de periodos">
            <label>Buscar código <input class="quality-select" type="search" wire:model.live.debounce.400ms="buscar" maxlength="80"></label>
            <label>Estado <input class="quality-select" type="search" wire:model.live.debounce.400ms="estado" maxlength="30" placeholder="Todos los estados"></label>
        </section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if ($periodos->isEmpty())
            <div class="quality-empty"><h2>No se encontraron periodos</h2><p>Revise los filtros o consulte con la administración académica.</p></div>
        @else
            <section class="quality-panel" style="margin-top:1rem"><div style="overflow-x:auto"><table style="width:100%; min-width:620px; text-align:left"><thead><tr><th>Periodo</th><th>Fechas</th><th>Estado</th><th>Selección</th><th>Detalle</th></tr></thead><tbody>
                @foreach ($periodos as $periodo)
                    <tr wire:key="periodo-{{ $periodo->id_periodo }}"><td class="quality-code">{{ $periodo->codigo }}</td><td>{{ $periodo->fecha_inicio }} — {{ $periodo->fecha_fin }}</td><td>{{ $periodo->estado }}</td><td>{{ (int) session('gestion_horarios.periodo_seleccionado') === (int) $periodo->id_periodo ? 'Seleccionado' : '—' }}</td><td><button type="button" class="quality-open-link" wire:click="$set('detalle', '{{ $periodo->id_periodo }}')">Ver detalle</button> <button type="button" class="quality-open-link" wire:click="seleccionar({{ $periodo->id_periodo }})">Seleccionar</button></td></tr>
                @endforeach
            </tbody></table></div></section>
        @endif
        @if ($detallePeriodo)
            <section class="quality-panel" style="margin-top:1rem; padding:1rem" aria-labelledby="periodo-detalle">
                <h2 id="periodo-detalle">Periodo {{ $detallePeriodo->codigo }}</h2><p>{{ $detallePeriodo->tipo }} · {{ $detallePeriodo->anio }} · {{ $detallePeriodo->numero_semanas }} semanas · Estado: {{ $detallePeriodo->estado }}</p>
                <p>La selección se guarda en esta sesión y se utiliza al abrir Oferta académica. Los datos institucionales se muestran en consulta.</p>
                <a class="quality-open-link" href="{{ route('gestion-horarios.oferta', ['periodo' => $detallePeriodo->id_periodo]) }}">Ver oferta del periodo</a>
                <h3>Bloques horarios registrados</h3>
                @forelse ($bloques as $bloque)<p>{{ $bloque->nombre }} · {{ $bloque->hora_inicio }} – {{ $bloque->hora_fin }}</p>@empty<p>No hay bloques horarios registrados para este periodo.</p>@endforelse
                <h3>Bloqueos de ambientes</h3>
                @forelse ($bloqueos as $bloqueo)<p>{{ $bloqueo->nombre }} · {{ $bloqueo->fecha_inicio }} – {{ $bloqueo->fecha_fin }} · {{ $bloqueo->dia_semana ?? 'Todos los días' }} · {{ $bloqueo->hora_inicio ?? 'Todo el día' }}{{ $bloqueo->hora_fin ? ' – '.$bloqueo->hora_fin : '' }} · {{ $bloqueo->motivo }}</p>@empty<p>No hay bloqueos de ambientes registrados.</p>@endforelse
                <h3>Restricciones globales y del periodo</h3>
                @forelse ($restricciones as $restriccion)<p>{{ $restriccion->nombre }} · {{ $restriccion->severidad }} · {{ $restriccion->ambito }}</p>@empty<p>No hay restricciones globales o de periodo registradas.</p>@endforelse
                @if(\App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo::disponible())
                    @php($cal=\App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo::calendario())
                    <h3>Configurar calendario semanal</h3>
                    @if(session('gh_aviso'))<p role="status">{{ session('gh_aviso') }}</p>@endif
                    @foreach($errors->all() as $error)<p role="alert" class="quality-alert">{{ $error }}</p>@endforeach
                    <form method="post" action="{{ route('gestion-horarios.calendario-qa.guardar') }}">
                        @csrf <input type="hidden" name="version" value="{{ $cal['version'] }}">
                        <fieldset><legend>Días laborables</legend><div style="display:flex;gap:1rem;flex-wrap:wrap">@foreach(\App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo::DIAS as $dia)<label><input type="checkbox" name="dias[]" value="{{ $dia }}" @checked(in_array($dia,$cal['dias'],true))> {{ ucfirst(strtolower($dia)) }}</label>@endforeach</div></fieldset>
                        <fieldset style="margin-top:1rem"><legend>Sábados: autorización excepcional para cursos regulares por ciclo</legend><div style="display:flex;gap:1rem;flex-wrap:wrap">@for($c=1;$c<=10;$c++)<label><input type="checkbox" name="sabados[]" value="{{ $c }}" @checked(in_array($c,$cal['sabados_ciclos'],true))> {{ $c }}</label>@endfor</div></fieldset>
                        <p>Repetición: sábado permitido con disponibilidad docente declarada. Jornada de 07:00 a 20:00; receso 13:00–14:00. Los bloques y fechas del periodo siguen gestionados por Core.</p>
                        <button type="submit" class="institutional-primary">Guardar calendario</button>
                    </form>
                    <details class="gh-ui-group"><summary>Historial de configuración</summary>@foreach(\Illuminate\Support\Facades\DB::table(\App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo::CALENDARIO)->orderByDesc('version')->get() as $r)<p>V{{ $r->version }} · {{ $r->actor }} · {{ \Illuminate\Support\Carbon::parse($r->creado_en)->timezone('America/Lima')->format('d/m/Y H:i') }}</p>@endforeach</details>
                @else<p>Prepare el almacenamiento de configuración local.</p>@endif
            </section>
        @endif
    @endif
</div>
