<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $module }}</title>
</head>
<body>
    <main>
        <h1>{{ $module }}</h1>
        <p>Esquema PostgreSQL: <code>{{ $schema }}</code></p>
        @isset($creating)
            <p>Formulario base de creación.</p>
        @endisset
    </main>
</body>
</html>
