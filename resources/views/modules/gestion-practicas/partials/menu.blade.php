{{-- Tipografía del portal (docs/DESIGN.md §1): Fira Sans para la interfaz, Fira Code para datos. --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
@php
    $enlaces = [
        ['gestion-practicas.panel', 'Panel', 'gestion-practicas.view', 'gestion-practicas.panel'],
        ['gestion-practicas.empresas.index', 'Empresas', 'gestion-practicas.empresas.gestionar', 'gestion-practicas.empresas.*'],
        ['gestion-practicas.revision.convocatorias.index', 'Revisar convocatorias', 'gestion-practicas.convocatorias.revisar', 'gestion-practicas.revision.convocatorias.*'],
        ['gestion-practicas.revision.practicas.index', 'Validar prácticas', 'gestion-practicas.practicas.validar', 'gestion-practicas.revision.practicas.*'],
        ['gestion-practicas.empresa.perfil.edit', 'Perfil de la empresa', 'gestion-practicas.convocatorias.gestionar', 'gestion-practicas.empresa.perfil.*'],
        ['gestion-practicas.empresa.convocatorias.index', 'Mis convocatorias', 'gestion-practicas.convocatorias.gestionar', 'gestion-practicas.empresa.convocatorias.*'],
        ['gestion-practicas.estudiante.convocatorias.index', 'Convocatorias', 'gestion-practicas.convocatorias.ver', 'gestion-practicas.estudiante.convocatorias.*'],
        ['gestion-practicas.estudiante.postulaciones.index', 'Mis postulaciones', 'gestion-practicas.postulaciones.gestionar', 'gestion-practicas.estudiante.postulaciones.*'],
        ['gestion-practicas.estudiante.practicas.index', 'Mis prácticas', 'gestion-practicas.practicas.registrar', 'gestion-practicas.estudiante.practicas.*'],
        ['gestion-practicas.supervisor.practicas.index', 'Prácticas asignadas', 'gestion-practicas.practicas.supervisar', 'gestion-practicas.supervisor.practicas.*'],
    ];
@endphp
<nav aria-label="Gestión de Prácticas" class="flex flex-wrap gap-2">
    @foreach ($enlaces as [$ruta, $texto, $permiso, $patron])
        @can($permiso)
            <a href="{{ route($ruta) }}"
               @if (request()->routeIs($patron)) aria-current="page" @endif
               class="inline-flex min-h-[40px] items-center rounded-[10px] border px-3.5 text-sm font-bold transition duration-150 {{ request()->routeIs($patron) ? 'border-[color:var(--portal-navy)] bg-[color:var(--portal-navy)] text-white' : 'border-[color:var(--portal-border)] bg-[color:var(--portal-surface)] text-[color:var(--portal-navy)] hover:border-[color:var(--portal-green)] hover:text-[color:var(--portal-green-dark)]' }}">
                {{ $texto }}
            </a>
        @endcan
    @endforeach
</nav>
