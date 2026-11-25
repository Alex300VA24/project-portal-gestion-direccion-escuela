<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración de Demostración Requerida · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=roboto-slab:400,600,700,800|inter:400,500,600,700&display=swap" rel="stylesheet">
    @include('modules.bienestar.partials.tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --unt-azul: #12377B;
            --unt-azul-oscuro: #092C6C;
            --u-noche: #0B1F44;
            --unt-dorado: #E6AD09;
            --unt-dorado-oscuro: #BD8E07;
            --u-dorado-texto: #8A6700;
            --unt-negro: #1B1811;
            --unt-fondo-suave: #F5F7FA;
            --u-papel: #F7F3EA;
            --unt-texto: #222222;
            --unt-texto-suave: #5F6670;
            --unt-borde: #E3E6EA;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--unt-fondo-suave);
            color: var(--unt-texto);
        }
        .btn-unt-primary {
            background-color: var(--unt-azul);
            color: #FFFFFF !important;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
        }
        .btn-unt-primary:hover {
            background-color: var(--unt-azul-oscuro);
        }
        .btn-unt-outline {
            background-color: transparent;
            color: var(--unt-azul-tinta) !important;
            border: 1.5px solid var(--unt-azul-tinta);
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
        }
        .btn-unt-outline:hover {
            background-color: var(--unt-azul);
            color: #FFFFFF !important;
        }
        .card-unt {
            background-color: var(--unt-superficie);
            border: 1px solid var(--unt-borde);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between">
    <header class="bg-unt-negro text-white border-b-2 border-[#E6AD09] shadow-md">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-[#E6AD09] transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg" style="font-family: 'Roboto Slab', serif;">Bienestar UNT</span>
                </a>
                <span class="text-slate-500">/</span>
                <span class="text-xs text-slate-300 font-medium">Aviso de Demostración</span>
            </div>
            <a href="{{ route('bienestar.index') }}" class="text-xs text-slate-300 hover:text-[#E6AD09] transition font-semibold">Volver al Módulo</a>
            @include('modules.bienestar.partials.boton-tema')
        </div>
    </header>

    <main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full flex items-center justify-center">
        <div class="card-unt p-8 w-full text-center space-y-6">
            <div class="w-16 h-16 bg-[color:var(--unt-crema)] text-[color:var(--u-dorado-texto)] rounded-2xl flex items-center justify-center mx-auto border border-[#E6AD09]/30">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <div>
                <h1 class="text-xl font-bold text-[color:var(--unt-azul-tinta)] mb-2" style="font-family: 'Roboto Slab', serif;">Datos de Demostración Requeridos</h1>
                <p class="text-sm text-[color:var(--unt-texto)] leading-relaxed font-medium">
                    {!! $mensaje !!}
                </p>
                @if (!empty($detalle) && $detalle !== $mensaje)
                    <p class="text-xs text-[color:var(--unt-texto-suave)] mt-2">
                        Detalle técnico: {!! $detalle !!}
                    </p>
                @endif
            </div>

            <div class="bg-[#0B1F44] text-slate-100 rounded-xl p-4 text-left font-mono text-xs overflow-x-auto shadow-inner border border-[color:var(--unt-azul-tinta)]">
                <div class="text-unt-texto-suave text-[11px] mb-1"># Ejecute el siguiente comando en la terminal de la aplicación:</div>
                <code class="text-[#E6AD09] select-all">php artisan db:seed --class="App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder"</code>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('bienestar.index') }}" class="btn-unt-primary text-xs w-full sm:w-auto">
                    Volver al Módulo de Bienestar
                </a>
                <a href="{{ route('bienestar.informacion.index') }}" class="btn-unt-outline text-xs w-full sm:w-auto">
                    Ver Artículos Normativos Públicos
                </a>
            </div>
        </div>
    </main>

    <footer class="bg-unt-negro text-slate-300 border-t border-[#E6AD09]/30 py-4 text-center text-xs">
        Dirección de Escuela de Informática · Universidad Nacional de Trujillo · 2026
    </footer>
@livewireScriptConfig
</body>
</html>
