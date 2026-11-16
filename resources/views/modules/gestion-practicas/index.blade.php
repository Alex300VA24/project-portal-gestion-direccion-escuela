<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $module }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: 'Fira Sans', system-ui, sans-serif; background: #f4f4ef; color: #141a33; }
        main { max-width: 52rem; margin: 0 auto; padding: 4rem 1.5rem; }
        h1 { font-size: 2.4rem; letter-spacing: -.03em; margin: 0 0 .5rem; }
        p { color: #4a5170; line-height: 1.55; }
        code { background: #eef0f3; padding: .1rem .4rem; border-radius: .25rem; }
        ol { color: #4a5170; line-height: 1.8; padding-left: 1.2rem; }
        a.cta { display: inline-block; margin-top: 1rem; background: #0d7a50; color: #fff; padding: .7rem 1.2rem; border-radius: .5rem; font-weight: 700; text-decoration: none; }
        a.cta:hover { background: #09613f; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $module }}</h1>
        <p>Esquema PostgreSQL: <code>{{ $schema }}</code></p>
        <p>Administra el proceso de prácticas preprofesionales de la Escuela de Informática, desde la publicación de la convocatoria hasta la calificación final.</p>
        <ol>
            <li><strong>Empresa:</strong> publica convocatorias y las corrige si el administrador las observa.</li>
            <li><strong>Administrador:</strong> gestiona empresas, aprueba convocatorias y valida las prácticas.</li>
            <li><strong>Estudiante:</strong> consulta convocatorias, postula y registra su práctica.</li>
            <li><strong>Docente supervisor:</strong> realiza el seguimiento, evalúa y cierra la práctica.</li>
        </ol>
        @auth
            <a class="cta" href="{{ route('gestion-practicas.panel') }}">Ir al panel de prácticas</a>
        @else
            <a class="cta" href="{{ route('login') }}">Iniciar sesión</a>
        @endauth
    </main>
</body>
</html>
