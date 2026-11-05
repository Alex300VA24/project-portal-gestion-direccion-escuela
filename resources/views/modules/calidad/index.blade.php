<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Calidad · Portal Gestión Dirección</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="quality-gateway-body">
    <main class="quality-gateway">
        <a class="quality-gateway-brand" href="{{ route('web-institucional.index') }}"><span aria-hidden="true">PG</span> Portal Gestión Dirección</a>
        <div><p class="quality-route">/calidad</p><h1>{{ $module }}</h1><p>Gestión de procesos, indicadores, evidencias y planes de mejora usando el esquema <code>{{ $schema }}</code> del portal.</p><a href="{{ auth()->check() ? route('calidad.home') : route('login') }}">{{ auth()->check() ? 'Abrir módulo' : 'Iniciar sesión' }} <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></div>
    </main>
</body>
</html>
