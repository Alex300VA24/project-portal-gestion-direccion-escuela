<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Acceso Restringido · Portal Dirección de Escuela</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="quality-body min-h-screen flex flex-col justify-between">
    <!-- Barra superior institucional -->
    <header class="quality-topbar">
        <a class="quality-brand" href="{{ route('bienestar.index') }}" style="border-bottom: 0; padding: 0;">
            <img class="quality-brand-mark" src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" style="width: 38px; height: 38px;">
            <span style="color: var(--quality-ink);"><strong style="font-size: 15px;">Bienestar UNT</strong><small style="color: var(--quality-muted);">Portal Dirección de Escuela</small></span>
        </a>

        <div class="quality-topbar-actions">
            @auth
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="quality-icon-button" style="width: auto; padding: 0 14px; font-size: 13px; font-weight: 600;" aria-label="Cerrar sesión">
                        Cerrar sesión
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="quality-icon-button" style="width: auto; padding: 0 14px; font-size: 13px; font-weight: 600;">
                    Iniciar sesión
                </a>
            @endauth
        </div>
    </header>

    <!-- Contenido principal centrado -->
    <main class="flex-1 flex items-center justify-center p-6" tabindex="-1">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-sm border border-slate-200 p-8 sm:p-10 text-center space-y-6">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-900 flex items-center justify-center shadow-xs">
                <svg class="w-8 h-8 text-indigo-800" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>

            <div class="space-y-2">
                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold font-mono uppercase tracking-wider bg-slate-100 text-slate-700">
                    HTTP 403 · Acceso No Autorizado
                </span>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Acceso Restringido</h1>
                <p class="text-sm text-slate-600 leading-relaxed">
                    No dispones de los privilegios necesarios para acceder a este recurso institucional. En estricto resguardo de la confidencialidad estudiantil (Ley N° 29733 de Protección de Datos Personales), este apartado se encuentra limitado a los roles autorizados.
                </p>
            </div>

            @auth
                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3.5 text-xs text-slate-600 text-left flex items-center justify-between">
                    <div>
                        <span class="text-slate-500 block text-[11px]">Usuario autenticado:</span>
                        <strong class="text-slate-900 font-semibold">{{ auth()->user()->name }}</strong>
                    </div>
                    <span class="px-2 py-1 bg-white border border-slate-200 rounded text-slate-700 font-mono text-[11px] font-semibold">
                        {{ Str::of(auth()->user()->getRoleNames()->first() ?? 'Sin rol')->replace('_', ' ') }}
                    </span>
                </div>
            @endauth

            <p class="text-xs text-slate-500">
                Si requieres acceso o consideras que se trata de una inconsistencia en tus credenciales, comunícate con la Secretaría Académica de la Escuela de Informática.
            </p>

            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('bienestar.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-indigo-900 hover:bg-indigo-800 transition shadow-sm">
                    &larr; Volver al inicio
                </a>
                <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition">
                    Ir al Portal General
                </a>
            </div>
        </div>
    </main>

    <!-- Pie institucional -->
    <footer class="py-4 text-center text-xs text-slate-500 border-t border-slate-200 bg-white">
        Dirección de Escuela de Informática · Universidad Nacional de Trujillo · 2026
    </footer>
</body>
</html>
