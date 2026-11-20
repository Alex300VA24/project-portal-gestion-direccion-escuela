<div class="quality-page" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    <nav aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios</a> / Preparación de generación</nav>
    <header class="quality-page-heading"><div><span class="quality-eyebrow">Gestión de Horarios Académicos</span><h1>Preparación de generación</h1><p>Revise el periodo y compruebe sus datos antes de planificar horarios.</p></div></header>
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar la información. Verifique con soporte que las actualizaciones de Gestión de Horarios estén instaladas e intente nuevamente.</div>
    @else
        <section class="quality-panel" style="padding:1.25rem">
            <label for="preparacion-periodo">Periodo académico</label><br>
            <select id="preparacion-periodo" class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select>
            @if ($detalle)
                <p><strong>{{ $detalle->codigo }}</strong> · {{ $detalle->estado }} · {{ $detalle->fecha_inicio }} – {{ $detalle->fecha_fin }}</p>
                @if ($demanda)<p>Demanda vigente: versión {{ $demanda->version }} @if ($demanda->requiere_revision) · requiere revisión @endif</p>@else<p>No hay demanda vigente recibida para este periodo.</p>@endif
            @elseif ($periodo !== '')<p role="alert">No se encontró el periodo seleccionado.</p>@else<p>Seleccione un periodo para consultar su información.</p>@endif
        </section>
        <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="comprobar-titulo">
            <h2 id="comprobar-titulo">Comprobación de datos</h2>
            <p>La comprobación es parcial y no garantiza que exista un horario factible. No ejecuta el motor ni modifica datos.</p>
            <button type="button" class="institutional-primary" wire:click="comprobar" wire:loading.attr="disabled" wire:target="comprobar">Comprobar datos del periodo</button>
            <p wire:loading wire:target="comprobar" role="status" aria-live="polite">Comprobando los datos…</p>
            @if ($falloComprobacion)<p class="quality-alert" role="alert">No se pudo completar la comprobación. Intente nuevamente o comuníquese con soporte.</p>@endif
            @if ($comprobado)
                <div aria-live="polite">
                    <p><strong>Resultado:</strong> {{ collect($resultados)->where('nivel', 'ERROR')->count() }} errores · {{ collect($resultados)->where('nivel', 'ADVERTENCIA')->count() }} advertencias · {{ collect($resultados)->where('nivel', 'INFORMACION')->count() }} informaciones</p>
                    <ul>@foreach ($resultados as $resultado)<li><strong>{{ $resultado['nivel'] === 'INFORMACION' ? 'INFORMACIÓN' : $resultado['nivel'] }}:</strong> {{ $resultado['mensaje'] }}</li>@endforeach</ul>
                </div>
            @endif
        </section>
        @if ($detalle)
            <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="prioridad-docente-titulo">
                <h2 id="prioridad-docente-titulo">Prioridad docente</h2>
                <p>La administración del orden de prioridad requiere verificar la escuela autorizada y contar con una fuente institucional versionada. Todavía no está disponible para este periodo.</p>
                <p>La prioridad es una preferencia del cálculo y no sustituye las restricciones obligatorias. Su edición se está comprobando con datos ficticios aislados.</p>
            </section>
            <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="servicio-calculo-titulo">
                <h2 id="servicio-calculo-titulo">Estado del servicio de cálculo</h2>
                <p>La generación institucional sigue deshabilitada. El motor y la cola requieren una integración y una configuración de infraestructura verificadas antes de su uso.</p>
                <p>Conexión de cola configurada: {{ config('queue.default') }}. Esta información no confirma que haya un trabajador activo ni que Python esté disponible.</p>
                <p>Las ejecuciones ficticias se limitan a pruebas aisladas y no son horarios institucionales.</p>
            </section>
            <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="compatibilidad-titulo">
                <h2 id="compatibilidad-titulo">Compatibilidad con el motor CP-SAT</h2>
                <p><strong>Contrato institucional: pendiente.</strong> La generación no está habilitada.</p>
                <p>La preparación conserva las referencias de curso, plan, escuela, oferta y sección cuando están disponibles. La consulta detallada por escuela requiere completar la autorización institucional.</p>
                <p>Se pueden revisar la identidad del periodo, {{ count($calendario['bloques']) }} franjas registradas y {{ count($poblacion['secciones']) }} secciones. Estos datos aún no constituyen una entrada completa.</p>
                <p>Faltan población acreditada, cohortes, subgrupos, calendario semanal, patrones de actividad y políticas aprobadas. Las asociaciones deben superar la comprobación de integridad.</p>
                <p>El motor se ha probado con datos ficticios; esas pruebas no verifican las restricciones con datos de este periodo. Ejecute «Comprobar datos del periodo» para consultar los diagnósticos disponibles.</p>
            </section>
            <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="calendario-titulo">
                <h2 id="calendario-titulo">Calendario y sesiones académicas</h2>
                <p>Periodo {{ $detalle->codigo }} · Estado del calendario: {{ match ($calendario['estado']) { 'PARCIAL' => 'información parcial', 'INCONSISTENTE' => 'requiere revisión', 'INEXISTENTE' => 'sin configuración registrada', default => 'comprobado' } }}.</p>
                <p>Días académicos y recesos: no confirmados. No se habilita sábado por la sola existencia de disponibilidad docente.</p>
                <details><summary>Franjas registradas ({{ count($calendario['bloques']) }})</summary><ul>@forelse ($calendario['bloques'] as $bloque)<li>{{ $bloque['nombre'] ?? 'Bloque' }} · {{ $bloque['hora_inicio'] }} – {{ $bloque['hora_fin'] }}</li>@empty<li>No hay franjas registradas.</li>@endforelse</ul></details>
                <details><summary>Bloqueos de ambientes ({{ count($calendario['bloqueos']) }})</summary><ul>@forelse ($calendario['bloqueos'] as $bloqueo)<li>{{ $bloqueo['nombre'] }} · {{ $bloqueo['fecha_inicio'] }} – {{ $bloqueo['fecha_fin'] }}</li>@empty<li>No hay bloqueos registrados.</li>@endforelse</ul></details>
                <p>Actividades con patrón confirmado: {{ $calendario['patrones_definidos'] }} · Pendientes de patrón: {{ $calendario['patrones_pendientes'] }}.</p>
                <p>Sesiones candidatas: no verificables hasta contar con calendario y patrones aprobados. Esta revisión no genera horarios.</p>
                @if ($calendario['estado'] === 'INCONSISTENTE')<p class="quality-alert" role="alert">Revise las fechas y franjas: existen datos inválidos o superpuestos.</p>@endif
            </section>
            <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="poblacion-titulo">
                <h2 id="poblacion-titulo">Datos de población académica</h2>
                <p>Periodo {{ $detalle->codigo }} · Secciones examinadas: {{ count($poblacion['secciones']) }} · Población conocida: {{ collect($poblacion['secciones'])->where('estado', 'CONOCIDA')->count() }} · Sin información suficiente: {{ collect($poblacion['secciones'])->where('estado', 'DESCONOCIDA')->count() }}</p>
                <p>La demanda {{ $demanda ? 'tiene una versión recibida' : 'no está disponible' }} y representa solicitudes agregadas, no estudiantes asignados a una sección.</p>
                <p>Cohortes y subgrupos: información no identificada. El equipo académico y el proveedor institucional deben confirmar la población por sección y las relaciones entre grupos.</p>
                @if ($poblacion['diagnosticos'])<p class="quality-alert" role="alert">Se detectaron datos de población que requieren revisión.</p>@endif
            </section>
            <section class="quality-panel" style="padding:1.25rem; margin-top:1rem" aria-labelledby="consultas-titulo">
                <h2 id="consultas-titulo">Consultar información del periodo</h2>
                <p>Use estas pantallas para revisar los datos registrados. El periodo seleccionado se conserva durante la sesión.</p>
                <ul>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.oferta', ['periodo' => $detalle->id_periodo]) }}">Oferta académica y demanda vigente</a></li>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.secciones') }}">Secciones y capacidades</a></li>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.asignacion-docente') }}">Actividades y asignaciones docentes</a></li>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.disponibilidad-docente') }}">Disponibilidad docente</a></li>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.ambientes') }}">Ambientes y laboratorios</a></li>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.restricciones') }}">Restricciones académicas</a></li>
                    <li><a class="quality-open-link" href="{{ route('gestion-horarios.periodos') }}">Detalle y bloques del periodo</a></li>
                </ul>
            </section>
        @endif
    @endif
</div>
