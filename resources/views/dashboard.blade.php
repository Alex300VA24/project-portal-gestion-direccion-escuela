<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Seleccionar módulo · Portal Gestión Dirección</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="portal-body">
    @php
        $role = Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario institucional')->replace('_', ' ');
        $modules = [
            ['name' => 'Calidad', 'route' => 'calidad.home', 'description' => 'Procesos, indicadores, evidencias y mejora continua.'],
            ['name' => 'Pre Matrícula', 'route' => 'pre-matricula.index', 'description' => 'Solicitudes y planificación de la matrícula.'],
            ['name' => 'Bienestar', 'route' => 'bienestar.index', 'description' => 'Atención, servicios y acompañamiento.'],
            ['name' => 'Investigación Formativa', 'route' => 'investigacion-formativa.index', 'description' => 'Proyectos, líneas y competencias de investigación.'],
            ['name' => 'Seguimiento al Estudiante', 'route' => 'seguimiento-estudiante.index', 'description' => 'Tutoría, alertas y avance académico.'],
            ['name' => 'Gestión de Horarios', 'route' => 'gestion-horarios.index', 'description' => 'Oferta, disponibilidad y programación académica.'],
            ['name' => 'COTECU', 'route' => 'cotecu.index', 'description' => 'Coordinación técnica curricular.'],
            ['name' => 'Gestión de Prácticas', 'route' => 'gestion-practicas.index', 'description' => 'Prácticas preprofesionales y convenios.'],
            ['name' => 'Tesis y Proyecto de Tesis', 'route' => 'tesis-proyecto-tesis.index', 'description' => 'Seguimiento de proyectos y tesis.'],
            ['name' => 'Gestión de Matrículas', 'route' => 'gestion-matriculas.index', 'description' => 'Matrícula e historial académico.'],
            ['name' => 'Seguimiento al Egresado', 'route' => 'seguimiento-egresado.index', 'description' => 'Trayectoria y empleabilidad del egresado.'],
        ];
    @endphp
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('dashboard') }}"><span aria-hidden="true">PG</span><strong>Portal Gestión Dirección</strong></a>
        <div class="portal-account"><span class="portal-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>{{ $role }}</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Salir</button></form></div>
    </header>
    <main class="portal-content">
        <header class="portal-intro"><div><h1>Elige dónde trabajar.</h1><p>Cada módulo usa la misma identidad institucional y conserva rutas independientes.</p></div><span class="portal-role">{{ $role }}</span></header>
        <section class="portal-featured" aria-labelledby="featured-title">
            <div><span class="portal-module-code">CAL</span><h2 id="featured-title">Gestión de Calidad</h2><p>La experiencia de projectCalidad, integrada con procesos, periodos, usuarios y archivos del portal.</p></div>
            <a href="{{ route('calidad.home') }}">Ingresar a Calidad <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </section>
        <section class="portal-module-list" aria-labelledby="modules-title">
            <div class="portal-list-heading"><h2 id="modules-title">Todos los módulos</h2><span>{{ count($modules) }} disponibles</span></div>
            <div class="portal-module-grid">
                @foreach ($modules as $index => $item)
                    <a class="portal-module-item" href="{{ route($item['route']) }}"><span class="portal-module-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><span><strong>{{ $item['name'] }}</strong><small>{{ $item['description'] }}</small></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                @endforeach
            </div>
        </section>
    </main>
@livewireScriptConfig
</body>
</html>
