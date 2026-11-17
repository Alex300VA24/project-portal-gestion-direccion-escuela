<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $articulo->titulo }} · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('bienestar.informacion.index') }}" class="text-sm text-indigo-200 hover:text-white transition inline-flex items-center">
                &larr; Volver a la lista de artículos
            </a>
            @if ($puedeGestionar)
                <a href="{{ route('bienestar.informacion.edit', $articulo) }}" class="text-xs bg-indigo-700 hover:bg-indigo-600 text-white font-semibold px-3 py-1.5 rounded-lg transition">
                    Editar Artículo
                </a>
            @endif
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto px-4 py-8 w-full">
        @if (session('status'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <article class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <div class="flex items-center space-x-3 mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-3 py-1 rounded-full">
                    {{ $articulo->fuente_normativa }}
                </span>
                @if ($puedeGestionar)
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold {{ $articulo->estado === 'publicado' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ ucfirst($articulo->estado) }}
                    </span>
                @endif
                <span class="text-xs text-slate-400">
                    Última revisión: {{ $articulo->fecha_ultima_revision?->format('d/m/Y') }}
                </span>
            </div>

            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
                {{ $articulo->titulo }}
            </h1>

            <div class="mt-4 p-4 rounded-xl bg-slate-50 border-l-4 border-indigo-600 text-slate-700 text-sm font-medium">
                {{ $articulo->resumen }}
            </div>

            <div class="mt-8 prose prose-slate max-w-none text-slate-700 text-base leading-relaxed border-t border-slate-100 pt-6">
                {!! $articulo->detalle !!}
            </div>

            <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
                <p>Dirección de Bienestar Universitario · Escuela de Informática</p>
                <a href="{{ route('bienestar.informacion.index') }}" class="font-semibold text-indigo-600 hover:text-indigo-800">
                    &larr; Volver al catálogo normativo
                </a>
            </div>
        </article>
    </main>
</body>
</html>
