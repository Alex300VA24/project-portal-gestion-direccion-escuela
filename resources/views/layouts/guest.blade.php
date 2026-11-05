<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Acceso seguro al Portal Gestión Dirección.">
    <title>Acceso · Portal Gestión Dirección</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <a class="skip-link" href="#contenido">Saltar al formulario</a>
    <main id="contenido" class="auth-shell">
        <aside class="auth-context" aria-label="Portal Gestión Dirección">
            <a class="auth-brand" href="{{ route('web-institucional.index') }}" aria-label="Volver a la web institucional">
                <span aria-hidden="true">PGD</span>
                <span><strong>Portal Gestión</strong><small>Dirección de Escuela</small></span>
            </a>

            <div class="auth-context-copy">
                <h2>Una sola identidad para toda la gestión.</h2>
                <p>Ingresa con tu cuenta institucional y continúa en los módulos habilitados para tu rol.</p>
                <ul>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Acceso centralizado</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Rutas independientes por módulo</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Información institucional compartida</li>
                </ul>
            </div>

            <div class="auth-network" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
                <svg viewBox="0 0 620 260"><path d="M8 209 169 73l147 94L468 38l144 93"/><path d="M8 209h604"/></svg>
            </div>
            <p class="auth-context-footer">Portal institucional · Acceso protegido</p>
        </aside>

        <section class="auth-panel">
            <a class="auth-back" href="{{ route('web-institucional.index') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
                Web institucional
            </a>
            <div class="auth-form-frame">
                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
