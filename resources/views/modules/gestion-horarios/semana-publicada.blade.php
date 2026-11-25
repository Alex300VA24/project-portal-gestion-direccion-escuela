<x-app-layout>
    <section class="quality-page gh-ui gh-publicada" style="max-width:76rem;margin:auto;padding:1.5rem">
    @include('modules.gestion-horarios.ui-estilos')
        <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.consulta-publicados') }}">Horarios publicados</a> / Horario semanal</nav>
        <header style="margin:1rem 0 1.5rem"><h1 style="font-size:clamp(1.75rem,4vw,2.6rem);font-weight:700;line-height:1.15">Horario semanal publicado</h1>
        <p>Periodo {{ $periodo->codigo }} · {{ $descripcion }}@if (! empty($filtros['seccion'])) · Sección {{ $filtros['seccion'] }}@endif</p></header>
        @if ($sesiones->isNotEmpty())
            @include('modules.gestion-horarios.cuadricula-semanal')
        @endif
        @if ($sesiones->isEmpty()) <p role="status">No se encontraron actividades publicadas para los filtros seleccionados.</p> @endif
        <p><a href="{{ route('gestion-horarios.consulta-publicados', $filtros) }}">Volver a la consulta</a></p>
    </section>
</x-app-layout>
