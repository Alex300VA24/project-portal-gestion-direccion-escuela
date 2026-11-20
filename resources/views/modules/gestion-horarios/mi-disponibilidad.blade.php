<div class="quality-page gh-docente" style="padding:2rem 1rem;margin:0 auto;max-width:1180px">
    <style>
        .gh-docente__hero{padding:26px;border-radius:16px;background:#17224d;color:#fff;margin:20px 0}
        .gh-docente__hero h1{color:#fff;margin:6px 0;font-size:clamp(1.7rem,3vw,2.3rem)}
        .gh-docente__hero p{color:#e5eaf3;margin:0}
        .gh-docente__nav{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0}
        .gh-docente__nav a{padding:10px 14px;border:1px solid #d9e1e8;background:#fff;border-radius:10px;color:#09613f;font-weight:700}
        .gh-docente__panel{padding:22px;margin-top:18px;border:1px solid #d9e1e8;border-radius:14px;background:#fff;scroll-margin-top:30px}
        .gh-docente__panel h2{margin:0 0 8px;font-size:20px}
        .gh-docente__panel p{line-height:1.6}
        .gh-docente__courses{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:11px;margin-top:16px}
        .gh-docente__course{padding:15px;border-radius:10px;background:#f1f5f4;border:1px solid #e2e8e7}
        .gh-docente__course strong{display:block;margin-bottom:6px}
        .gh-docente__course small{color:#4a5170}
        .gh-docente__slots{display:flex;flex-wrap:wrap;gap:8px;padding:0;list-style:none}
        .gh-docente__slots li{padding:9px 12px;background:#e7f4ec;border-radius:8px;color:#09613f;font-weight:600}
        .gh-docente__status{padding:13px 15px;background:#fff4d6;border-left:4px solid #c28c25;border-radius:8px}
        .gh-docente a:focus-visible,.gh-docente select:focus-visible{outline:3px solid #16a273;outline-offset:3px}
    </style>
    <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.index') }}">Gestión de Horarios Académicos</a> / Mi espacio docente</nav>
    <header class="gh-docente__hero">
        <span>Universidad Nacional de Trujillo · Gestión de Horarios</span>
        <h1>Mi espacio docente</h1>
        <p>Sus cursos, declaración de disponibilidad y horario académico en un solo lugar.</p>
    </header>
    @if ($error_consulta)
        <div class="quality-alert" role="alert">No se pudo consultar la información. Intente nuevamente o contacte con soporte.</div>
    @elseif (! $acceso)
        <div class="quality-alert" role="status">
            <strong>Acceso docente confirmado.</strong>
            <p>Ingresó con el rol de profesor. No se pudo verificar la vinculación docente de su cuenta, por lo que todavía no se pueden mostrar cursos ni disponibilidad personales.</p>
            <p>La administración del portal debe vincular su cuenta con el registro docente institucional. Mientras tanto, puede consultar los horarios publicados desde la portada.</p>
        </div>
    @elseif ($periodos->isEmpty())
        <div class="quality-empty"><h2>No hay asignaciones confirmadas</h2><p>Secretaría o Dirección debe revisar sus asignaciones y la oferta vigente.</p></div>
    @else
        <section class="gh-docente__panel">
            <label for="periodo-propio"><strong>Periodo académico</strong></label>
            <p>Seleccione un periodo. Sus asignaciones pueden pertenecer a distintos ciclos y secciones.</p>
            <select id="periodo-propio" class="quality-select" wire:model.live="periodo">
                <option value="">Seleccione un periodo</option>
                @foreach ($periodos as $p)
                    <option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>
                @endforeach
            </select>
            <p wire:loading role="status">Actualizando información…</p>
        </section>
        @if ($periodo_valido)
            <nav class="gh-docente__nav" aria-label="Secciones docentes">
                <a href="#mis-cursos">Mis cursos asignados</a>
                <a href="#mi-declaracion">Mi disponibilidad</a>
                <a href="#mi-horario">Mi horario académico</a>
            </nav>
            <section class="gh-docente__panel" id="mis-cursos" aria-labelledby="mis-cursos-titulo">
                <h2 id="mis-cursos-titulo">Mis cursos asignados</h2>
                <p>Actividades confirmadas del periodo, incluso si corresponden a diferentes ciclos.</p>
                @if ($asignaciones->isEmpty())
                    <p>No hay asignaciones confirmadas para este periodo.</p>
                @else
                    <div class="gh-docente__courses">
                        @foreach ($asignaciones as $a)
                            <div class="gh-docente__course">
                                <strong>{{ $a->curso }} · {{ $a->nombre }}</strong>
                                <small>Ciclo {{ $a->ciclo }} · Sección {{ $a->seccion }} · {{ $a->tipo_actividad }}</small>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="gh-docente__panel" id="mi-declaracion" aria-labelledby="mi-declaracion-titulo">
                <h2 id="mi-declaracion-titulo">Mi disponibilidad</h2>
                @switch($estado)
                    @case('SIN_DECLARACION') <p>No hay declaración registrada para el periodo.</p> @break
                    @case('NO_PRESENTADA') <p>Hay una declaración pendiente o no presentada.</p> @break
                    @case('PRESENTADA_SIN_FRANJAS') <p>La declaración fue presentada sin franjas. Esto no implica disponibilidad total.</p> @break
                    @case('PRESENTADA_CON_FRANJAS') <p>La declaración fue presentada con franjas registradas.</p> @break
                    @default <p role="alert">Los datos requieren revisión por Secretaría o Dirección.</p>
                @endswitch
                @if ($franjas->isNotEmpty())
                    <ul class="gh-docente__slots">
                        @foreach ($franjas as $franja)
                            <li>{{ $franja->dia_semana }} · {{ $franja->hora_inicio }}–{{ $franja->hora_fin }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="gh-docente__status" role="note">Actualmente esta pantalla es de consulta. Registrar o modificar disponibilidades requiere habilitación institucional y validación de las reglas del periodo.</p>
            </section>
            <section class="gh-docente__panel" id="mi-horario" aria-labelledby="mi-horario-titulo">
                <h2 id="mi-horario-titulo">Mi horario académico</h2>
                <p>Esta vista reunirá las sesiones de todos sus cursos, ciclos y secciones, con día, hora, actividad y ambiente.</p>
                <p class="gh-docente__status" role="status">La consulta personal de sesiones programadas aún no está integrada. Las asignaciones confirmadas no equivalen a un horario publicado.</p>
            </section>
        @else
            <div class="quality-empty"><h2>Seleccione un periodo con asignaciones</h2><p>Podrá consultar sus cursos y declaración correspondiente.</p></div>
        @endif
    @endif
</div>
