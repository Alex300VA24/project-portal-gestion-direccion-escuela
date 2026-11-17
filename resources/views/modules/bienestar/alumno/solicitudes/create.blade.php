<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva Solicitud de Apoyo · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-indigo-200 transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg">Bienestar UNT</span>
                </a>
                <span class="text-indigo-400">/</span>
                <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="text-sm text-indigo-200 hover:text-white transition">Mis Solicitudes</a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-indigo-300 font-medium">Nueva Solicitud</span>
            </div>
            <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Cancelar</a>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto px-4 py-8 w-full space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <h1 class="text-2xl font-bold text-slate-900 mb-2">Solicitar Apoyo o Beneficio de Bienestar</h1>
            <p class="text-sm text-slate-600 mb-6">
                Complete el siguiente formulario explicando su situación socioeconómica o académica para que la Unidad de Bienestar Universitario pueda evaluar la asignación del apoyo.
            </p>

            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('bienestar.alumno.solicitudes.store') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label for="id_beneficio" class="block text-sm font-semibold text-slate-700 mb-1">
                        Beneficio o Programa Solicitado <span class="text-rose-500">*</span>
                    </label>
                    <select id="id_beneficio" name="id_beneficio" required
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('id_beneficio') border-rose-400 @enderror">
                        <option value="">-- Seleccione un beneficio disponible --</option>
                        @foreach ($beneficios as $beneficio)
                            <option value="{{ $beneficio->id_beneficio }}" @selected(old('id_beneficio') == $beneficio->id_beneficio)>
                                {{ $beneficio->nombre }} (Tipo: {{ $beneficio->tipo }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="motivo" class="block text-sm font-semibold text-slate-700 mb-1">
                        Motivo y Sustento de la Solicitud <span class="text-rose-500">*</span>
                    </label>
                    <p class="text-xs text-slate-500 mb-2">
                        Describa con claridad las circunstancias particulares, académicas o socioeconómicas que sustentan su petición (mínimo 10 caracteres).
                    </p>
                    <textarea id="motivo" name="motivo" rows="5" required
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('motivo') border-rose-400 @enderror"
                        placeholder="Explique detalladamente su situación actual...">{{ old('motivo') }}</textarea>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.alumno.solicitudes.index') }}"
                        class="px-4 py-2 border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition">
                        Cancelar
                    </a>
                    <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                        Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>