<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isEditing ? 'Editar Artículo' : 'Nuevo Artículo Informativo' }} · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-bold text-white">{{ $isEditing ? 'Editar Artículo' : 'Redactar Nuevo Artículo' }}</h1>
            <a href="{{ route('bienestar.informacion.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Cancelar y Volver</a>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto px-4 py-8 w-full">
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                <p class="font-bold mb-2">Por favor corrige los siguientes errores:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $isEditing ? route('bienestar.informacion.update', $articulo) : route('bienestar.informacion.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <div>
                <label for="titulo" class="block text-sm font-bold text-slate-700">Título del Artículo *</label>
                <input type="text" name="titulo" id="titulo" value="{{ old('titulo', $articulo->titulo) }}" required maxlength="200" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            <div>
                <label for="fuente_normativa" class="block text-sm font-bold text-slate-700">Fuente Normativa (Art. y Ley / Reglamento) *</label>
                <input type="text" name="fuente_normativa" id="fuente_normativa" value="{{ old('fuente_normativa', $articulo->fuente_normativa) }}" required maxlength="255" placeholder="Ej. Ley Universitaria 30220, Art. 102 [Pendiente de verificación UNT]" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            <div>
                <label for="resumen" class="block text-sm font-bold text-slate-700">Resumen en Lenguaje Sencillo *</label>
                <textarea name="resumen" id="resumen" rows="2" required maxlength="500" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('resumen', $articulo->resumen) }}</textarea>
                <p class="text-xs text-slate-400 mt-1">Breve síntesis para que el estudiante comprenda el impacto de inmediato.</p>
            </div>

            <div>
                <label for="detalle" class="block text-sm font-bold text-slate-700">Contenido Detallado (HTML permitido: párrafos, listas, negritas) *</label>
                <textarea name="detalle" id="detalle" rows="8" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">{{ old('detalle', $articulo->detalle) }}</textarea>
                <p class="text-xs text-slate-400 mt-1">El contenido es sanitizado automáticamente eliminando scripts o elementos no autorizados.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="fecha_ultima_revision" class="block text-sm font-bold text-slate-700">Fecha de Última Revisión *</label>
                    <input type="date" name="fecha_ultima_revision" id="fecha_ultima_revision" value="{{ old('fecha_ultima_revision', $articulo->fecha_ultima_revision ? $articulo->fecha_ultima_revision->format('Y-m-d') : date('Y-m-d')) }}" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>

                <div>
                    <label for="estado" class="block text-sm font-bold text-slate-700">Estado de Publicación *</label>
                    <select name="estado" id="estado" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="publicado" @selected(old('estado', $articulo->estado) === 'publicado')>Publicado (Visible para todos)</option>
                        <option value="borrador" @selected(old('estado', $articulo->estado) === 'borrador')>Borrador (Solo personal de Bienestar)</option>
                    </select>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-200 flex items-center justify-end space-x-3">
                <a href="{{ route('bienestar.informacion.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancelar
                </a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold shadow-sm transition">
                    {{ $isEditing ? 'Guardar Cambios' : 'Crear Artículo' }}
                </button>
            </div>
        </form>
    </main>
</body>
</html>
