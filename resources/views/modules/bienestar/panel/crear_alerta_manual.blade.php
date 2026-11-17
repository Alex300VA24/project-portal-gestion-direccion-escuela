<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva Alerta Manual de Riesgo · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-bold text-white">Registro de Alerta Manual · Bienestar</h1>
            <a href="{{ route('bienestar.panel.index') }}" class="text-xs text-indigo-200 hover:text-white transition">&larr; Volver al Panel</a>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto px-4 py-8 w-full">
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                <p class="font-bold mb-2">Corrige los siguientes errores:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
            <div class="p-4 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs">
                <strong>Alerta Manual de Bienestar:</strong> Permite registrar preventivamente a cualquier estudiante detectado en riesgo (por consulta espontánea, derivación de tutor o solicitud directa). Esta alerta tendrá origen <code class="font-mono bg-indigo-100 px-1 py-0.5 rounded text-indigo-800">manual_bienestar</code> y el proceso automático nocturno nunca la modificará ni reabrirá.
            </div>

            <form action="{{ route('bienestar.panel.alertas.store-manual') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="codigo_estudiante" class="block text-sm font-bold text-slate-700">Código Universitario del Estudiante *</label>
                        <input type="text" name="codigo_estudiante" id="codigo_estudiante" value="{{ old('codigo_estudiante') }}" required placeholder="Ej. 1021400121" class="mt-1 block w-full rounded-lg border-slate-300 font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="curso" class="block text-sm font-bold text-slate-700">Curso o Contexto Académico</label>
                        <input type="text" name="curso" id="curso" value="{{ old('curso', 'GLOBAL') }}" maxlength="150" placeholder="GLOBAL o nombre de asignatura" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="prioridad" class="block text-sm font-bold text-slate-700">Nivel de Prioridad *</label>
                        <select name="prioridad" id="prioridad" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="alta" @selected(old('prioridad') === 'alta')>Alta (Situación crítica o urgente)</option>
                            <option value="media" @selected(old('prioridad', 'media') === 'media')>Media (Acompañamiento regular preventivo)</option>
                            <option value="baja" @selected(old('prioridad') === 'baja')>Baja (Consulta informativa o seguimiento leve)</option>
                        </select>
                    </div>

                    <div>
                        <label for="motivo_reporte" class="block text-sm font-bold text-slate-700">Motivo de la Alerta *</label>
                        <input type="text" name="motivo_reporte" id="motivo_reporte" value="{{ old('motivo_reporte') }}" required maxlength="255" placeholder="Ej. Dificultad socioeconómica familiar y riesgo de abandono" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="descripcion_reporte" class="block text-sm font-bold text-slate-700">Descripción Detallada de la Situación *</label>
                    <textarea name="descripcion_reporte" id="descripcion_reporte" rows="4" required minlength="10" placeholder="Detalle antecedentes, factores observados y plan de orientación..." class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion_reporte') }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">Este contenido se cifra en base de datos para preservar la confidencialidad.</p>
                </div>

                <div class="pt-6 border-t border-slate-200 flex justify-end space-x-3">
                    <a href="{{ route('bienestar.panel.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold shadow-sm transition">
                        Registrar Alerta Manual
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
