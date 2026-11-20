<x-app-layout>
    <main class="portal-body" style="min-height:70vh;padding:2rem">
        <h1>Horarios publicados</h1>
        <p role="alert">No se pudo consultar los horarios publicados. Intente nuevamente o contacte con soporte.</p>
        <a href="{{ route('gestion-horarios.consulta-publicados') }}">Volver a intentar</a>
        <a href="{{ route('gestion-horarios.index') }}">Volver a Gestión de Horarios</a>
    </main>
</x-app-layout>
