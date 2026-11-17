<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración de Demostración Requerida · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-indigo-200 transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg">Bienestar UNT</span>
                </a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-indigo-300 font-medium">Aviso de Demostración</span>
            </div>
            <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver al Módulo</a>
        </div>
    </header>

    <main class="flex-1 max-w-3xl mx-auto px-4 py-12 w-full flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-sm border border-amber-200 p-8 w-full text-center space-y-6">
            <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <div>
                <h1 class="text-xl font-bold text-slate-900 mb-2">Datos de Demostración Requeridos</h1>
                <p class="text-sm text-slate-600 leading-relaxed font-medium">
                    {!! $mensaje !!}
                </p>
                @if (!empty($detalle) && $detalle !== $mensaje)
                    <p class="text-xs text-slate-400 mt-2">
                        Detalle técnico: {!! $detalle !!}
                    </p>
                @endif
            </div>

            <div class="bg-slate-900 text-slate-100 rounded-xl p-4 text-left font-mono text-xs overflow-x-auto shadow-inner">
                <div class="text-slate-400 text-[11px] mb-1"># Ejecute el siguiente comando en la terminal de la aplicación:</div>
                <code class="text-emerald-400 select-all">php artisan db:seed --class="App\Modules\Bienestar\Database\Seeders\BienestarDemoSeeder"</code>
            </div>

            <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('bienestar.index') }}" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition">
                    Volver al Módulo de Bienestar
                </a>
                <a href="{{ route('bienestar.informacion.index') }}" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition">
                    Ver Artículos Normativos Públicos
                </a>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        Dirección de Escuela de Informática · Universidad Nacional de Trujillo
    </footer>
</body>
</html>