<!-- {{ $schema }} -->
@auth
    <x-app-layout>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
        <div class="portal-body quality-body" style="min-height:calc(100vh - 65px)">
            @include('modules.gestion-horarios.inicio-contenido')
        </div>
    </x-app-layout>
@else
    @component('modules.gestion-horarios.layout-publico')
        @include('modules.gestion-horarios.inicio-contenido')
    @endcomponent
@endauth
