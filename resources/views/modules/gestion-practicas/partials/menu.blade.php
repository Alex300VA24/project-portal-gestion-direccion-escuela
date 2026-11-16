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
               class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs($patron) ? 'bg-emerald-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200' }}">
                {{ $texto }}
            </a>
        @endcan
    @endforeach
</nav>
