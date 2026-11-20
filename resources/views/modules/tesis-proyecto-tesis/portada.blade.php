<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $module }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-sans:400,700|fira-code:600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f4ef; color: #141a33; font: 400 16px/1.55 'Fira Sans', sans-serif; }
        main { width: min(100% - 32px, 560px); background: #fff; border: 1px solid #dfe3ea; border-radius: 16px; padding: 32px; box-shadow: 0 16px 44px rgba(23, 34, 77, .09); }
        p.eyebrow { font: 600 12px 'Fira Code', monospace; letter-spacing: .06em; text-transform: uppercase; color: #0d7a50; margin: 0 0 8px; }
        h1 { margin: 0 0 10px; color: #17224d; font-size: 2rem; line-height: 1.15; }
        a { display: inline-flex; margin-top: 20px; min-height: 48px; align-items: center; padding: 0 20px; border-radius: 10px; background: #0d7a50; color: #fff; font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid #f2a900; outline-offset: 3px; }
        code { font-family: 'Fira Code', monospace; font-size: 13px; color: #4a5170; }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">Escuela de Ingeniería Informática</p>
        <h1>{{ $module }}</h1>
        <p>Registro del proyecto de tesis, avances con el asesor, revisión del jurado, sustentación y actas.</p>
        <p><code>Esquema: {{ $schema }}</code></p>
        <a href="{{ route('login') }}">Iniciar sesión para continuar</a>
    </main>
</body>
</html>
