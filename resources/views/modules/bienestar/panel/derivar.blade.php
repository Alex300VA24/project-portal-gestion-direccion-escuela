<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva Derivación · Caso #{{ $caso->id_caso }} · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-bold text-white">Derivación a Especialista · Caso #{{ $caso->id_caso }}</h1>
            <a href="{{ route('bienestar.panel.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Cancelar y Volver</a>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto px-4 py-8 w-full">
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                <p class="font-bold mb-2">Por favor revisa los errores:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
            <div class="pb-6 border-b border-slate-100">
                <h2 class="text-xl font-bold text-slate-900">
                    Estudiante: {{ $caso->estudiante?->persona?->nombres }} {{ $caso->estudiante?->persona?->apellido_paterno }}
                </h2>
                <p class="text-xs text-slate-500 font-mono mt-1">
                    Código: {{ $caso->estudiante?->codigo_universitario }} · Tipo de Caso: {{ $caso->tipoCaso?->nombre }}
                </p>
            </div>

            <form action="{{ route('bienestar.panel.guardar-derivacion') }}" method="POST" class="space-y-6">
                @csrf
                <input type="hidden" name="id_caso" value="{{ $caso->id_caso }}">

                <div>
                    <label for="id_especialista_destino" class="block text-sm font-bold text-slate-700">Seleccionar Especialista del Catálogo UNT</label>
                    <select name="id_especialista_destino" id="id_especialista_destino" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- Opcional si se deriva a una entidad externa --</option>
                        @foreach ($especialistas as $esp)
                            <option value="{{ $esp->id_especialista }}" @selected(old('id_especialista_destino') == $esp->id_especialista)>
                                [{{ $esp->especialidad?->nombre }}] {{ $esp->nombre_completo }} — {{ $esp->institucion }} ({{ $esp->contacto }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="entidad_externa" class="block text-sm font-bold text-slate-700">O Entidad Externa de Salud / Asistencia</label>
                    <input type="text" name="entidad_externa" id="entidad_externa" value="{{ old('entidad_externa') }}" placeholder="Ej. Centro de Salud Mental Comunitario Trujillo / Hospital Regional" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="motivo" class="block text-sm font-bold text-slate-700">Motivo de la Derivación *</label>
                    <textarea name="motivo" id="motivo" rows="4" required placeholder="Detalla las razones de la derivación, sintomatología observada o requerimiento especializado..." class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('motivo') }}</textarea>
                </div>

                <!-- Consentimiento obligatorio (Regla 6) -->
                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" name="consentimiento_estudiante" value="1" @checked(old('consentimiento_estudiante')) required class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="block text-sm font-bold text-amber-950">
                                Consentimiento Informado del Estudiante (Obligatorio) *
                            </span>
                            <span class="block text-xs text-amber-800 mt-1">
                                Certifico que el estudiante ha sido informado acerca del propósito de la derivación y ha brindado su consentimiento expreso para la remisión y emisión de la ficha de derivación oficial.
                            </span>
                        </div>
                    </label>
                </div>

                <div class="pt-6 border-t border-slate-200 flex justify-end space-x-3">
                    <a href="{{ route('bienestar.panel.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold shadow-sm transition">
                        Registrar Derivación e Imprimir
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
