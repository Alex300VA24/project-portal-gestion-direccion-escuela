<section class="gh-home__section gh-ui gh-inicio-admin" aria-label="Espacio de trabajo académico">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>match($etapa) { 'preparacion'=>'Preparación académica', 'recursos'=>'Recursos académicos', 'elaboracion'=>'Elaboración de horarios', 'revision'=>'Revisión y publicación', default=>'Gestión de Horarios Académicos' }, 'subtitulo'=>null, 'descripcion'=>$etapa === 'recursos' ? 'Catálogos generales, independientes del periodo académico.' : 'Seleccione el plan y periodo para continuar.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => $etapa, 'workspacePlan' => $planValido ? $plan : '', 'workspacePeriodo' => $periodoValido ? $periodo : ''])
    @if ($errorContexto || ($falloConsulta ?? false))
        <p role="alert">No fue posible consultar el contexto académico. Intente nuevamente.</p>
    @else
        @if ($etapa !== 'recursos')
        <div class="gh-ui-context">
            <label>Plan de estudios<br><select class="quality-select" wire:model.live="plan">
                <option value="">Seleccione un plan</option>
                @foreach ($planes as $item)<option value="{{ $item->id_plan }}">{{ $item->codigo }} · {{ $item->nombre }}</option>@endforeach
            </select></label>
            <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo" @disabled(! $planValido)>
                <option value="">Seleccione un periodo</option>
                @foreach ($periodos as $item)<option value="{{ $item->id_periodo }}">{{ $item->codigo }}</option>@endforeach
            </select></label>
        </div>
        @endif
        <p wire:loading role="status">Actualizando contexto académico…</p>
        @php
            $parametros = ['plan' => $plan, 'periodo' => $periodo];
            $consultaDemo = \App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion::permitida(auth()->user());
        @endphp
        @if ($consultaDemo)
            <p class="gh-home__notice" role="status">Entorno de demostración. La publicación institucional aún no está disponible.</p>
        @endif
        @if (in_array($etapa, ['inicio', 'preparacion'], true))
            @if ($planValido && $periodoValido)
                @if ($etapa === 'inicio')
                    <a class="gh-home__button" href="{{ route('gestion-horarios.oferta', $parametros) }}">Abrir preparación académica</a>
                @else
                    <div class="gh-ui-actions">
                        <button type="button" disabled title="Core administra los periodos">Nuevo periodo</button>
                        <button type="button" disabled title="Core administra los periodos">Editar periodo</button>
                        @if ($consultaDemo)<a class="institutional-primary" href="{{ route('gestion-horarios.borradores-qa', $parametros) }}">Abrir horario</a>@endif
                    </div>
                    <p>Seleccione una pestaña para organizar los cursos, docentes, secciones y restricciones del periodo.</p>
                @endif
            @else
                <p role="status">Seleccione un plan y un periodo válidos para abrir la preparación.</p>
            @endif
        @elseif ($etapa === 'recursos')
            @if ($consultaDemo)
                <p>Seleccione una pestaña para consultar el catálogo general o editar la cola de prioridad docente.</p>
            @else
                <p class="gh-home__notice" role="note">Las consultas de recursos compartidos siguen pendientes de autorización por escuela.</p>
            @endif
        @elseif ($etapa === 'elaboracion')
            <h3>Elaboración de horarios</h3>
            <p>Configure el calendario y genere el horario de trabajo.</p>
            @if ($consultaDemo && $planValido && $periodoValido)
                <nav class="gh-home__step-links gh-ui-links" aria-label="Preparación de generación">
                    <a class="gh-home__step-link" href="{{ route('gestion-horarios.periodos', $parametros) }}"><span class="gh-home__step-copy"><strong>Calendario académico</strong><small>Consultar periodos y bloques horarios.</small></span></a>
                    <a class="gh-home__step-link" href="{{ route('gestion-horarios.preparacion', $parametros) }}"><span class="gh-home__step-copy"><strong>Prevalidación</strong><small>Revisar el diagnóstico de preparación.</small></span></a>
                </nav>
                <div class="gh-ui-links"><a class="gh-home__step-link" href="{{ route('gestion-horarios.borradores-qa') }}"><span class="gh-home__step-copy"><strong>Horario de trabajo</strong><small>Un único horario con historial de revisiones.</small></span></a><a class="gh-home__step-link" href="{{ route('gestion-horarios.borradores-qa', $parametros) }}#gh-generacion-titulo"><span class="gh-home__step-copy"><strong>Generar horario</strong><small>Crear o actualizar la programación.</small></span></a></div>
            @else
                <p class="gh-home__notice" role="note">La generación institucional todavía no está habilitada.</p>
            @endif
        @elseif ($etapa === 'revision')
            <h3>Revisión y publicación</h3>
            <p>Revisión de propuestas, edición, aprobación y publicación.</p>
            @if ($consultaDemo && $planValido && $periodoValido)
                <nav class="gh-home__step-links gh-ui-links" aria-label="Revisión de demostración">
                    <a class="gh-home__step-link" href="{{ route('gestion-horarios.horarios', $parametros) }}"><span class="gh-home__step-copy"><strong>Horarios registrados</strong><small>Consultar versiones existentes y exportar si hay registros.</small></span></a>
                </nav>
                <div class="gh-ui-links"><a class="gh-home__step-link" href="{{ route('gestion-horarios.borradores-qa') }}"><span class="gh-home__step-copy"><strong>Consultar historial del horario QA</strong><small>Consultar revisiones y cambios de programación.</small></span></a><div class="gh-ui-disabled"><strong>Aprobar y publicar</strong><small>Operaciones no habilitadas.</small></div></div>
            @else
                <p class="gh-home__notice" role="note">Las operaciones administrativas de horarios siguen pendientes de integración y autorización.</p>
            @endif
            <a class="gh-home__button" href="{{ route('gestion-horarios.consulta-publicados') }}">Consultar horarios publicados</a>
        @endif
    @endif
</section>
