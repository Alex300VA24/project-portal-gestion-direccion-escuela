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
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Gestión de Horarios Académicos</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="portal-body quality-body">
        <main id="contenido-horarios">
            @include('modules.gestion-horarios.inicio-contenido')
        </main>
    </body>
    </html>
@endauth
