<x-app-layout>
    <main class="quality-page gh-ui gh-lectura-page">
        @include('modules.gestion-horarios.ui-estilos')
        <header class="quality-page-heading"><h1>Consulta de horarios</h1></header>
        @include('modules.gestion-horarios.navegacion-lectura')
        <div class="quality-alert" role="alert">No se pudo consultar los horarios publicados. Intente nuevamente o contacte con soporte.</div>
        <a class="institutional-primary" href="{{ route('gestion-horarios.consulta-publicados') }}">Volver a intentar</a>
    </main>
</x-app-layout>
