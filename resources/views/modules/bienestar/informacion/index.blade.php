<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Información Académica y Bienestar · UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <!-- Barra superior -->
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-indigo-200 transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg">Bienestar UNT</span>
                </a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-indigo-200 font-medium">Información Académica</span>
            </div>
            <div class="flex items-center space-x-3">
                @if ($puedeGestionar)
                    <a href="{{ route('bienestar.informacion.create') }}" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-3 py-1.5 rounded-lg shadow-sm transition">
                        + Redactar Artículo
                    </a>
                @endif
                <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver al Módulo</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto px-4 py-8 w-full">
        @if (session('status'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-8 text-center max-w-2xl mx-auto">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Información Académica y Bienestar</h1>
            <p class="text-slate-600 mt-2 text-sm">
                Conoce las normativas universitarias, límites de permanencia, régimen disciplinario y procedimientos de bienestar de la Escuela de Informática.
            </p>
        </div>

        <div class="space-y-6">
            @forelse ($articulos as $art)
                <article class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:border-indigo-300 transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-full">
                                {{ $art->fuente_normativa }}
                            </span>
                            @if ($puedeGestionar)
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold {{ $art->estado === 'publicado' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst($art->estado) }}
                                </span>
                            @endif
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mt-2 hover:text-indigo-600 transition">
                            <a href="{{ route('bienestar.informacion.show', $art) }}">
                                {{ $art->titulo }}
                            </a>
                        </h2>
                        <p class="text-slate-600 text-sm mt-2 leading-relaxed">
                            {{ $art->resumen }}
                        </p>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Última revisión: <strong>{{ $art->fecha_ultima_revision?->format('d/m/Y') }}</strong></span>
                        <a href="{{ route('bienestar.informacion.show', $art) }}" class="font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center">
                            Leer detalle normativo &rarr;
                        </a>
                    </div>
                </article>
            @empty
                <div class="text-center py-12 bg-white rounded-xl border border-slate-200 text-slate-500">
                    <p class="text-base font-medium">No se encontraron artículos informativos publicados.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $articulos->links() }}
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        Información sujeta a confirmación oficial con el Reglamento General de la Universidad Nacional de Trujillo.
    </footer>
</body>
</html>
