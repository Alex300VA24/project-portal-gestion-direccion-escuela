<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportar Estudiante en Riesgo · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-bold text-white">Reporte Docente de Riesgo Estudiantil</h1>
            <a href="{{ route('bienestar.profesor.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Cancelar y Volver</a>
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

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-6">
            <!-- Sección de búsqueda exacta por código -->
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    Buscar Estudiante por Código Universitario Exacto
                </label>
                <div class="flex gap-2">
                    <input type="text" id="inputBuscarCodigo" placeholder="Ej. 1021400222" maxlength="30" class="flex-1 rounded-lg border-slate-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" id="btnBuscar" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition">
                        Verificar Código
                    </button>
                </div>
                <div id="busquedaFeedback" class="text-xs mt-2 font-medium hidden"></div>
            </div>

            <form action="{{ route('bienestar.profesor.store') }}" method="POST" class="space-y-6">
                @csrf

                @if (! empty($estudiantesAsignados))
                    <div>
                        <label for="selectAsignado" class="block text-sm font-bold text-slate-700">O seleccionar estudiante de mi lista de asignados</label>
                        <select id="selectAsignado" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Seleccionar de la nómina del curso --</option>
                            @foreach ($estudiantesAsignados as $est)
                                <option value="{{ $est['codigo'] }}" data-nombre="{{ $est['nombres'] }} {{ $est['apellidos'] }}" data-curso="{{ $est['curso'] }}">
                                    [{{ $est['codigo'] }}] {{ $est['nombres'] }} {{ $est['apellidos'] }} — {{ $est['curso'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="codigo_estudiante" class="block text-sm font-bold text-slate-700">Código Universitario del Estudiante *</label>
                        <input type="text" name="codigo_estudiante" id="codigo_estudiante" value="{{ old('codigo_estudiante') }}" required class="mt-1 block w-full rounded-lg border-slate-300 font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="nombre_estudiante_preview" class="block text-sm font-bold text-slate-700">Nombres y Apellidos</label>
                        <input type="text" id="nombre_estudiante_preview" readonly placeholder="Se completará al verificar código" class="mt-1 block w-full rounded-lg border-slate-200 bg-slate-100 text-slate-600 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="curso" class="block text-sm font-bold text-slate-700">Asignatura / Curso *</label>
                        <input type="text" name="curso" id="curso" value="{{ old('curso', 'Ingeniería de Software I') }}" required maxlength="150" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="prioridad" class="block text-sm font-bold text-slate-700">Nivel de Prioridad Estimado *</label>
                        <select name="prioridad" id="prioridad" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="alta" @selected(old('prioridad') === 'alta')>Alta (Riesgo inminente de inhabilitación o deserción)</option>
                            <option value="media" @selected(old('prioridad', 'media') === 'media')>Media (Bajo rendimiento o reiteradas inasistencias)</option>
                            <option value="baja" @selected(old('prioridad') === 'baja')>Baja (Orientación preventiva o dudas vocacionales)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="motivo_reporte" class="block text-sm font-bold text-slate-700">Motivo del Reporte *</label>
                    <input type="text" name="motivo_reporte" id="motivo_reporte" value="{{ old('motivo_reporte') }}" required maxlength="255" placeholder="Ej. Acumulación crítica de inasistencias y desaprobación en primer parcial" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="descripcion_reporte" class="block text-sm font-bold text-slate-700">Descripción Detallada de la Situación Observada *</label>
                    <textarea name="descripcion_reporte" id="descripcion_reporte" rows="4" required minlength="10" placeholder="Detalla conductas observadas, porcentaje de asistencia estimado, notas obtenidas o motivos expresados por el estudiante..." class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('descripcion_reporte') }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">Esta descripción se almacena cifrada para garantizar la confidencialidad.</p>
                </div>

                <div class="pt-6 border-t border-slate-200 flex items-center justify-end space-x-3">
                    <a href="{{ route('bienestar.profesor.index') }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-bold shadow-sm transition">
                        Enviar Reporte a Bienestar
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnBuscar = document.getElementById('btnBuscar');
            const inputCodigo = document.getElementById('inputBuscarCodigo');
            const inputCodigoForm = document.getElementById('codigo_estudiante');
            const inputPreview = document.getElementById('nombre_estudiante_preview');
            const feedback = document.getElementById('busquedaFeedback');
            const selectAsignado = document.getElementById('selectAsignado');

            if (selectAsignado) {
                selectAsignado.addEventListener('change', function () {
                    const opt = this.options[this.selectedIndex];
                    if (opt.value) {
                        inputCodigoForm.value = opt.value;
                        inputPreview.value = opt.getAttribute('data-nombre') || '';
                        if (opt.getAttribute('data-curso')) {
                            document.getElementById('curso').value = opt.getAttribute('data-curso');
                        }
                    }
                });
            }

            btnBuscar.addEventListener('click', function () {
                const codigo = inputCodigo.value.trim();
                if (!codigo) return;

                feedback.className = 'text-xs mt-2 font-medium text-slate-500 block';
                feedback.textContent = 'Buscando estudiante...';

                fetch(`{{ route('bienestar.profesor.buscar-estudiante') }}?codigo=${encodeURIComponent(codigo)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            feedback.className = 'text-xs mt-2 font-medium text-rose-600 block';
                            feedback.textContent = data.error;
                            inputPreview.value = '';
                        } else {
                            feedback.className = 'text-xs mt-2 font-medium text-emerald-600 block';
                            feedback.textContent = `Estudiante encontrado: ${data.nombres} ${data.apellidos} (${data.escuela})`;
                            inputCodigoForm.value = data.codigo;
                            inputPreview.value = `${data.nombres} ${data.apellidos}`;
                        }
                    })
                    .catch(() => {
                        feedback.className = 'text-xs mt-2 font-medium text-rose-600 block';
                        feedback.textContent = 'Error al comunicarse con el servidor.';
                    });
            });
        });
    </script>
</body>
</html>
