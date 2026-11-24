<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $module }} · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="quality-gateway-body">
    <main class="quality-gateway">
        <a class="quality-gateway-brand" href="{{ route('web-institucional.index') }}"><img class="quality-gateway-logo" src="{{ asset('img/logo-informatica.png') }}" alt=""> Portal Gestión Dirección</a>
        <div>
            <p class="quality-route">/gestion-practicas</p>
            <h1>{{ $module }}</h1>
            <p>Convocatorias, postulaciones, seguimiento y evaluación de las prácticas preprofesionales, usando el esquema <code>{{ $schema }}</code> del portal. Participan la empresa, el estudiante, el docente supervisor y el administrador.</p>
            <a href="{{ auth()->check() ? route('gestion-practicas.panel') : route('login') }}">{{ auth()->check() ? 'Abrir módulo' : 'Iniciar sesión' }} <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </main>
</body>
</html>
