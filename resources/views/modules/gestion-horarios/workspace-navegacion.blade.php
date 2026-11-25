@php
    $ghEtapas = [
        'inicio' => 'Inicio',
        'preparacion' => 'Preparación académica',
        'recursos' => 'Recursos académicos',
        'elaboracion' => 'Elaboración de horarios',
        'revision' => 'Revisión y publicación',
    ];
    $ghParametros = array_filter(['plan' => $workspacePlan ?? '', 'periodo' => $workspacePeriodo ?? ''], fn ($valor) => (is_string($valor) || is_int($valor)) && ctype_digit((string) $valor) && strlen((string) $valor) < 19);
    $ghEtapa = $workspaceEtapa ?? 'inicio';
    $ghSub = $workspaceSub ?? '';
    $ghQa = \App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion::permitida(auth()->user());
    // La autorización QA ya verificó que hay exactamente un plan y un periodo.
    // Esto evita perder el contexto al abrir una URL directa o un catálogo global.
    if ($ghQa) {
        $ghParametros = [
            'plan' => (string) \Illuminate\Support\Facades\DB::table('core.plan_estudios')->where('codigo','QA-HORARIOS')->value('id_plan'),
            'periodo' => (string) \Illuminate\Support\Facades\DB::table('core.periodo_academico')->where('codigo','2026-II')->value('id_periodo'),
        ];
    }
    $ghContexto = isset($ghParametros['plan'], $ghParametros['periodo']);
    $ghPrincipal = [];
    if ($ghQa) {
        $ghPrincipal['recursos'] = route('gestion-horarios.catalogo-recursos-qa', ['recurso'=>'cursos'] + $ghParametros);
        if ($ghContexto) {
            $ghPrincipal['preparacion'] = route('gestion-horarios.oferta', $ghParametros);
            $ghPrincipal['elaboracion'] = route('gestion-horarios.borradores-qa', $ghParametros);
            $ghPrincipal['revision'] = route('gestion-horarios.revision-trabajo-qa', $ghParametros);
        }
    }
    $ghTabs = match ($ghEtapa) {
        'preparacion' => [
            ['oferta', 'Oferta académica', 'gestion-horarios.oferta', [], $ghContexto],
            ['asignacion', 'Asignación docente', 'gestion-horarios.asignacion-docente', [], $ghQa && $ghContexto],
            ['disponibilidad', 'Disponibilidad docente', 'gestion-horarios.disponibilidad-docente', [], $ghQa && $ghContexto],
            ['secciones', 'Secciones', 'gestion-horarios.secciones', [], $ghContexto],
            ['restricciones', 'Restricciones académicas', 'gestion-horarios.restricciones', [], $ghQa && $ghContexto],
        ],
        'recursos' => [
            ['cursos', 'Cursos', 'gestion-horarios.catalogo-recursos-qa', ['recurso'=>'cursos'], $ghQa],
            ['docentes', 'Docentes', 'gestion-horarios.catalogo-recursos-qa', ['recurso'=>'docentes'], $ghQa],
            ['prioridad', 'Prioridad de docentes', 'gestion-horarios.prioridad-docente', [], $ghQa],
            ['aulas', 'Aulas', 'gestion-horarios.catalogo-recursos-qa', ['recurso'=>'aulas'], $ghQa],
            ['laboratorios', 'Laboratorios', 'gestion-horarios.catalogo-recursos-qa', ['recurso'=>'laboratorios'], $ghQa],
        ],
        'elaboracion' => [
            ['horario', 'Horario de trabajo', 'gestion-horarios.borradores-qa', [], $ghQa && $ghContexto],
            ['calendario', 'Calendario académico', 'gestion-horarios.periodos', [], $ghQa && $ghContexto],
            ['comprobar', 'Revisar datos', 'gestion-horarios.preparacion', [], $ghQa && $ghContexto],
        ],
        'revision' => [
            ['trabajo', 'Revisión del horario', 'gestion-horarios.revision-trabajo-qa', [], $ghQa && $ghContexto],
            ['registrados', 'Horarios registrados', 'gestion-horarios.horarios', [], $ghQa && $ghContexto],
            ['oferta-revision', 'Revisiones de oferta', 'gestion-horarios.revisiones', [], true],
            ['publicados', 'Horarios publicados', 'gestion-horarios.consulta-publicados', [], true],
        ],
        default => [],
    };
@endphp
<style>
    .gh-workspace-nav { display:flex; flex-wrap:wrap; gap:.35rem; margin:1rem 0; border-bottom:1px solid var(--portal-border,#d7dcd5); padding-bottom:.55rem; }
    .gh-workspace-nav a { padding:.65rem .85rem; border-radius:.4rem; color:inherit; text-decoration:none; font-weight:600; }
    .gh-workspace-nav a[aria-current="page"] { background:var(--portal-navy,#17224d); color:#fff; box-shadow:none; }
    .gh-workspace-nav a:hover { text-decoration:underline; }
    .gh-workspace-nav a:focus-visible { outline:2px solid currentColor; outline-offset:2px; }
    .gh-subnav { display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; padding:.35rem; margin:.75rem 0 1.1rem; background:var(--quality-surface,var(--portal-surface,#fff)); border:1px solid var(--portal-border,#dfe3ea); border-radius:10px; }
    .gh-subnav :is(a,span) { padding:.62rem .85rem; border-radius:6px; color:var(--quality-ink,var(--portal-ink,#141a33)); text-decoration:none; font-size:.88rem; line-height:1.3; }
    .gh-subnav a[aria-current="page"] { color:#fff; background:var(--portal-navy,#17224d); font-weight:700; }
    .gh-subnav a:hover { background:var(--portal-surface-alt,#eef0f3); }
    .gh-subnav [aria-disabled="true"] { opacity:.55; }
    .gh-subnav a:focus-visible { outline:3px solid var(--portal-focus,#f2a900); outline-offset:2px; }
</style>
<nav class="gh-workspace-nav" aria-label="Etapas de gestión de horarios">
    @foreach ($ghEtapas as $clave => $etiqueta)
        <a href="{{ $ghPrincipal[$clave] ?? route('gestion-horarios.administracion', ['etapa' => $clave] + $ghParametros) }}" @if (($workspaceEtapa ?? 'inicio') === $clave) aria-current="page" @endif>{{ $etiqueta }}</a>
    @endforeach
</nav>

@if ($ghTabs !== [])
    <nav class="gh-subnav" aria-label="Secciones de {{ $ghEtapas[$ghEtapa] }}">
        @foreach ($ghTabs as [$clave, $etiqueta, $ruta, $extra, $habilitado])
            @if ($habilitado)
                <a href="{{ route($ruta, $ruta === 'gestion-horarios.consulta-publicados' ? [] : $extra + $ghParametros) }}" @if ($ghSub === $clave) aria-current="page" @endif>{{ $etiqueta }}</a>
            @else
                <span aria-disabled="true" title="Requiere contexto académico o autorización disponible">{{ $etiqueta }}</span>
            @endif
        @endforeach
    </nav>
@endif
