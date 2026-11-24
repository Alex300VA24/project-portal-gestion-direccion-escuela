<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registro de estudiantes · Pre Matrícula</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('modules.pre-matricula.partials.estilos')
</head>
<body class="quality-gateway-body">
    <main class="quality-gateway">
        <a class="quality-gateway-brand" href="{{ route('web-institucional.index') }}"><img class="quality-gateway-logo" src="{{ asset('img/logo-informatica.png') }}" alt=""> Portal Gestión Dirección</a>
        <div>
            <p class="quality-route">/pre-matricula/registro</p>
            <h1>Registro de estudiantes</h1>
            <p>Crea tu cuenta para la prematrícula. Tu contraseña inicial será tu código universitario.</p>

            @if (session('error'))<div class="pm-flash is-danger" role="alert"><span>{{ session('error') }}</span></div>@endif
            @if ($errors->any())
                <div class="pm-flash is-danger" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form class="pm-form" method="POST" action="{{ route('pre-matricula.registro.store') }}">
                @csrf
                <label class="pm-field"><span>Código universitario (10 dígitos)</span><input name="codigo_universitario" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" value="{{ old('codigo_universitario') }}" required></label>
                <label class="pm-field"><span>Nombres</span><input name="nombres" maxlength="100" value="{{ old('nombres') }}" required></label>
                <label class="pm-field"><span>Apellidos</span><input name="apellidos" maxlength="150" value="{{ old('apellidos') }}" required></label>
                <label class="pm-field"><span>Correo institucional</span><input type="email" name="correo_institucional" maxlength="255" value="{{ old('correo_institucional') }}" placeholder="usuario@unitru.edu.pe" required></label>
                <button class="pm-button" type="submit">Registrarme</button>
            </form>
            <a href="{{ route('login') }}">Ya tengo cuenta: iniciar sesión <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
    </main>
</body>
</html>
