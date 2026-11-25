<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Acceso Restringido · Portal Dirección de Escuela</title>
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
    <!-- Barra superior institucional -->
    <header class="bg-unt-negro text-white border-b-2 border-[#E6AD09] px-6 py-3 shadow-md">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <a class="flex items-center gap-3 text-white no-underline" href="{{ route('bienestar.index') }}">
                <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="w-9 h-9">
                <span>
                    <strong class="text-sm font-bold tracking-wide block" style="font-family: 'Roboto Slab', serif;">Bienestar UNT</strong>
                    <small class="text-xs text-slate-300 block font-sans">Portal Dirección de Escuela</small>
                </span>
            </a>

            <div>
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-slate-300 hover:text-[#E6AD09] transition">
                            Cerrar sesión
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-xs font-semibold text-[#E6AD09] hover:underline">
                        Iniciar sesión
                    </a>
                @endauth
            </div>
            @include('modules.bienestar.partials.boton-tema')
        </div>
    </header>

    <!-- Contenido principal centrado -->
    <main class="flex-1 flex items-center justify-center p-6" tabindex="-1">
        <div class="w-full max-w-lg card-unt p-8 sm:p-10 text-center space-y-6">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-[color:var(--unt-crema)] border border-[#E6AD09]/30 text-[color:var(--u-dorado-texto)] flex items-center justify-center shadow-xs">
                <svg class="w-8 h-8 text-[color:var(--u-dorado-texto)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>

            <div class="space-y-2">
                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold font-mono uppercase tracking-wider bg-[color:var(--unt-fondo-suave)] text-[color:var(--unt-azul-tinta)] border border-[color:var(--unt-azul-tinta)]/20">
                    HTTP 403 · Acceso No Autorizado
                </span>
                <h1 class="text-2xl font-bold text-[color:var(--unt-azul-tinta)] tracking-tight" style="font-family: 'Roboto Slab', serif;">Acceso Restringido</h1>
                <p class="text-sm text-[color:var(--unt-texto-suave)] leading-relaxed">
                    No dispones de los privilegios necesarios para acceder a este recurso institucional. En estricto resguardo de la confidencialidad estudiantil (Ley N° 29733 de Protección de Datos Personales), este apartado se encuentra limitado a los roles autorizados.
                </p>
            </div>

            @auth
                <div class="bg-[color:var(--unt-fondo-suave)] border border-[color:var(--unt-borde)] rounded-xl p-3.5 text-xs text-[color:var(--unt-texto-suave)] text-left flex items-center justify-between">
                    <div>
                        <span class="text-[color:var(--unt-texto-suave)] block text-[11px]">Usuario autenticado:</span>
                        <strong class="text-[color:var(--unt-texto)] font-semibold">{{ auth()->user()->name }}</strong>
                    </div>
                    <span class="px-2 py-1 bg-white border border-[color:var(--unt-borde)] rounded text-[color:var(--unt-azul-tinta)] font-mono text-[11px] font-semibold">
                        {{ Str::of(auth()->user()->getRoleNames()->first() ?? 'Sin rol')->replace('_', ' ') }}
                    </span>
                </div>
            @endauth

            <p class="text-xs text-[color:var(--unt-texto-suave)]">
                Si requieres acceso o consideras que se trata de una inconsistencia en tus credenciales, comunícate con la Secretaría de Bienestar de la Escuela de Informática.
            </p>

            <div class="pt-4 border-t border-[color:var(--unt-borde)] flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('bienestar.index') }}" class="btn-unt-primary text-xs w-full sm:w-auto">
                    &larr; Volver al inicio
                </a>
                <a href="{{ route('dashboard') }}" class="btn-unt-outline text-xs w-full sm:w-auto">
                    Ir al Portal General
                </a>
            </div>
        </div>
    </main>

    <!-- Pie institucional -->
    <footer class="py-4 text-center text-xs text-slate-300 border-t border-[#E6AD09]/30 bg-unt-negro">
        Dirección de Escuela de Informática · Universidad Nacional de Trujillo · 2026
    </footer>
@livewireScriptConfig
</body>
</html>
