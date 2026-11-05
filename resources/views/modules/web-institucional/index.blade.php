<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal institucional para la gestión académica, calidad y servicios de la escuela profesional.">
    <title>Portal Gestión Dirección</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="institutional-body">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="institutional-topline"><span>Plataforma de gestión académica e institucional</span><span>Acceso para la comunidad universitaria</span></div>
    <header class="institutional-header">
        <a class="institutional-brand" href="{{ route('web-institucional.index') }}" aria-label="Portal Gestión Dirección, inicio">
            <span aria-hidden="true">PGD</span>
            <span class="institutional-brand-copy"><strong>Portal Gestión</strong><small>Dirección de Escuela</small></span>
        </a>
        <nav aria-label="Navegación institucional"><a href="#proposito">Propósito</a><a href="#comunidad">Comunidad</a><a href="#modulos">Módulos</a></nav>
        <a class="institutional-login" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Ir al portal' : 'Iniciar sesión' }}<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </header>
    <main id="contenido">
        <section class="institutional-hero">
            <div class="institutional-hero-copy">
                <h1>La escuela avanza cuando su gestión está conectada.</h1>
                <p>Un solo acceso para coordinar procesos académicos, calidad, seguimiento y servicios institucionales con información compartida.</p>
                <div class="institutional-actions"><a class="institutional-primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'Seleccionar un módulo' : 'Ingresar al portal' }}<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a><a class="institutional-secondary" href="{{ route('calidad.index') }}">Explorar Gestión de Calidad</a></div>
                <p class="institutional-access-note"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>El acceso respeta los permisos asignados a cada rol.</p>
            </div>
            <div class="institutional-hero-system" aria-label="Estructura del portal">
                <div class="system-window-head"><span></span><strong>Arquitectura del portal</strong><small>Conectado</small></div>
                <div class="system-core"><span>PGD</span><strong>Identidad institucional</strong><small>Una sesión segura</small></div>
                <ol class="system-flow">
                    <li><span>01</span><div><strong>Gestión académica</strong><small>Periodos, cursos y seguimiento</small></div></li>
                    <li><span>02</span><div><strong>Gestión de Calidad</strong><small>Procesos, indicadores y evidencias</small></div></li>
                    <li><span>03</span><div><strong>Servicios y comunidad</strong><small>Atención para cada rol</small></div></li>
                </ol>
                <div class="system-data-line"><span>core</span><span>seguridad</span><span>calidad</span><span>común</span></div>
            </div>
        </section>
        <section id="proposito" class="institutional-purpose">
            <div><h2>Una fuente de verdad para cada proceso.</h2><p>Los módulos comparten personas, periodos, cursos y seguridad. Cada área conserva sus propias reglas sin duplicar información institucional.</p><a href="#modulos">Ver módulos disponibles <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a></div>
            <dl><div><dt>Acceso</dt><dd>Una sesión para todos los módulos habilitados.</dd></div><div><dt>Datos</dt><dd>Tablas compartidas y trazabilidad por esquema.</dd></div><div><dt>Navegación</dt><dd>Rutas directas para cada tarea institucional.</dd></div></dl>
        </section>
        <section id="comunidad" class="institutional-community">
            <div class="institutional-section-heading"><h2>Diseñado para la comunidad de la escuela.</h2><p>La selección de módulos se adapta al rol y mantiene una experiencia consistente.</p></div>
            <ul><li><span>DE</span><strong>Director de escuela</strong><small>Supervisión, decisiones y calidad.</small></li><li><span>SE</span><strong>Secretaría</strong><small>Operación, registro y documentación.</small></li><li><span>PR</span><strong>Profesor</strong><small>Docencia, investigación y seguimiento.</small></li><li><span>AL</span><strong>Alumno</strong><small>Matrícula, bienestar y avance académico.</small></li></ul>
        </section>
        <section id="modulos" class="institutional-modules">
            <div class="institutional-section-heading"><h2>Calidad como eje de mejora continua.</h2><p>Consulta procesos, metas, mediciones, evidencias y planes usando las tablas actuales del portal.</p></div>
            <a href="{{ auth()->check() ? route('calidad.home') : route('login') }}"><span class="module-route">portal-gestion/calidad/home</span><strong>Abrir Gestión de Calidad</strong><span class="module-description">Procesos, indicadores, documentos y mejora continua.</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </section>
    </main>
    <footer class="institutional-footer"><div><strong>Portal Gestión Dirección</strong><small>Gestión conectada para la comunidad universitaria.</small></div><span>Experiencia pública institucional</span></footer>
</body>
</html>
