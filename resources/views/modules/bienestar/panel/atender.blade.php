<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atención de Alerta #{{ $alerta->id_alerta }} · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-bold text-white">Atención y Gestión de Alerta #{{ $alerta->id_alerta }}</h1>
            <a href="{{ route('bienestar.panel.index') }}" class="text-xs text-indigo-200 hover:text-white transition">&larr; Volver al Panel</a>
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto px-4 py-8 w-full space-y-8">
        @if (session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                <p class="font-bold mb-2">Corrige los errores:</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Ficha del estudiante y alerta -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-100 gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-3 py-1 rounded-full">
                        {{ $alerta->origen === 'automatica' ? 'Detección Automática' : 'Reporte Docente' }}
                    </span>
                    <h2 class="text-2xl font-bold text-slate-900 mt-2">
                        {{ $alerta->estudiante?->persona?->nombres }} {{ $alerta->estudiante?->persona?->apellido_paterno }} {{ $alerta->estudiante?->persona?->apellido_materno }}
                    </h2>
                    <p class="text-xs font-mono text-slate-500 mt-0.5">
                        Código Universitario: <strong>{{ $alerta->estudiante?->codigo_universitario }}</strong> · Periodo: {{ $alerta->periodo }}
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs px-3 py-1 rounded-full font-bold {{ $alerta->prioridad === 'alta' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                        Prioridad {{ ucfirst($alerta->prioridad) }}
                    </span>
                    <span class="text-xs px-3 py-1 rounded-full font-bold bg-slate-100 text-slate-800">
                        Estado: {{ ucfirst(str_replace('_', ' ', $alerta->estado)) }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6">
                <div>
                    <h3 class="text-xs font-bold uppercase text-slate-400">Regla Evaluada</h3>
                    <p class="text-sm font-semibold text-slate-800 mt-1">{{ str_replace('_', ' ', ucfirst($alerta->regla)) }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">Curso: {{ $alerta->curso }}</p>
                </div>

                @if ($alerta->docente)
                    <div>
                        <h3 class="text-xs font-bold uppercase text-slate-400">Docente Reportante</h3>
                        <p class="text-sm font-semibold text-slate-800 mt-1">
                            {{ $alerta->docente->persona?->nombres }} {{ $alerta->docente->persona?->apellido_paterno }}
                        </p>
                        <p class="text-xs font-mono text-slate-500 mt-0.5">Cód. {{ $alerta->docente->codigo_docente }}</p>
                    </div>
                @endif

                <div>
                    <h3 class="text-xs font-bold uppercase text-slate-400">Fecha de Detección</h3>
                    <p class="text-sm font-semibold text-slate-800 mt-1">{{ $alerta->fecha_deteccion?->format('d/m/Y H:i') }}</p>
                </div>
            </div>

            @if ($alerta->descripcion_reporte)
                <div class="mt-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-sm">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-amber-800 mb-1">Descripción del Docente:</h4>
                    <p>{{ $alerta->descripcion_reporte }}</p>
                </div>
            @endif

            @if (! empty($alerta->detalles))
                <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700">
                    <h4 class="font-bold uppercase tracking-wider text-slate-500 mb-1">Detalles Técnicos:</h4>
                    <pre class="font-mono text-xs overflow-x-auto">{{ json_encode($alerta->detalles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </div>

        <!-- Si ya cuenta con Caso de Bienestar asociado -->
        @if ($alerta->caso)
            <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 p-6 space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-lg font-bold text-indigo-950">Caso de Bienestar #{{ $alerta->caso->id_caso }}</h3>
                        <p class="text-xs text-slate-500">Estado del caso: <strong>{{ $alerta->caso->estado }}</strong> · Abierto el {{ $alerta->caso->abierto_en?->format('d/m/Y') }}</p>
                    </div>
                    <a href="{{ route('bienestar.panel.derivar', $alerta->caso) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                        + Nueva Derivación a Especialista
                    </a>
                </div>

                <!-- Citas programadas -->
                <div>
                    <h4 class="text-sm font-bold text-slate-800 mb-3">Citas de Atención en Bienestar</h4>
                    <div class="space-y-2">
                        @forelse ($alerta->caso->citas as $cita)
                            <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-800">{{ $cita->inicio?->format('d/m/Y H:i') }} - {{ $cita->fin?->format('H:i') }}</span>
                                    <span class="ml-2 px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold">{{ $cita->modalidad }}</span>
                                    <span class="ml-2 text-slate-500">Estado: {{ $cita->estado }}</span>
                                </div>
                                @if ($cita->notas_atencion)
                                    <div class="text-slate-600 italic">"{{ $cita->notas_atencion }}"</div>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 italic">No hay citas registradas en este caso.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Derivaciones -->
                <div>
                    <h4 class="text-sm font-bold text-slate-800 mb-3">Derivaciones Externas</h4>
                    <div class="space-y-4">
                        @forelse ($alerta->caso->derivaciones as $der)
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-3">
                                <div class="flex items-center justify-between text-xs">
                                    <div>
                                        <span class="font-bold text-slate-900 text-sm">Destino: {{ $der->destino_texto }}</span>
                                        <span class="ml-2 px-2 py-0.5 rounded font-semibold {{ $der->consentimiento_estudiante ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ $der->consentimiento_estudiante ? 'Consentimiento Firmado' : 'Sin Consentimiento' }}
                                        </span>
                                    </div>
                                    <div class="space-x-2">
                                        @if ($der->consentimiento_estudiante)
                                            <a href="{{ route('bienestar.panel.imprimir-derivacion', $der) }}" target="_blank" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-bold transition">
                                                Imprimir Ficha
                                            </a>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-xs text-slate-700"><strong>Motivo:</strong> {{ $der->motivo }}</p>

                                @if ($der->resultado_especialista)
                                    <div class="p-3 bg-white rounded-lg border border-slate-200 text-xs">
                                        <span class="font-bold text-slate-800">Informe de Respuesta del Especialista:</span>
                                        <p class="text-slate-700 mt-1">{{ $der->resultado_especialista }}</p>
                                    </div>
                                @else
                                    <form action="{{ route('bienestar.panel.registrar-resultado', $der) }}" method="POST" class="pt-2 border-t border-slate-200 flex gap-2">
                                        @csrf
                                        <input type="text" name="resultado_especialista" placeholder="Registrar informe / contrarreferencia del especialista..." required class="flex-1 rounded-lg border-slate-300 text-xs">
                                        <select name="estado_derivacion" class="rounded-lg border-slate-300 text-xs font-semibold">
                                            <option value="Atendida">Atendida</option>
                                            <option value="Pendiente">Pendiente</option>
                                            <option value="Cancelada">Cancelada</option>
                                        </select>
                                        <button type="submit" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-bold transition">
                                            Guardar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 italic">No hay derivaciones para este caso.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- Formulario de toma de decisión de la alerta -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Acción Resolutiva de Bienestar</h3>

            <form action="{{ route('bienestar.panel.guardar-atencion', $alerta) }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="p-4 rounded-xl border border-slate-300 cursor-pointer hover:border-indigo-500 transition flex items-start space-x-3">
                        <input type="radio" name="accion" value="abrir_caso" checked class="mt-1 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="block text-sm font-bold text-slate-900">Abrir Caso</span>
                            <span class="block text-xs text-slate-500 mt-1">Inicia acompañamiento psicopedagógico formal y seguimiento continuo.</span>
                        </div>
                    </label>

                    <label class="p-4 rounded-xl border border-slate-300 cursor-pointer hover:border-indigo-500 transition flex items-start space-x-3">
                        <input type="radio" name="accion" value="programar_cita" class="mt-1 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="block text-sm font-bold text-slate-900">Agendar Cita Directa</span>
                            <span class="block text-xs text-slate-500 mt-1">Programa una sesión inmediata en la oficina de Bienestar.</span>
                        </div>
                    </label>

                    <label class="p-4 rounded-xl border border-slate-300 cursor-pointer hover:border-rose-500 transition flex items-start space-x-3">
                        <input type="radio" name="accion" value="descartar" class="mt-1 text-rose-600 focus:ring-rose-500">
                        <div>
                            <span class="block text-sm font-bold text-slate-900">Descartar Alerta</span>
                            <span class="block text-xs text-slate-500 mt-1">Justifica por qué no requiere intervención de Bienestar.</span>
                        </div>
                    </label>
                </div>

                <!-- Campos condicionales para Abrir Caso -->
                <div id="seccionAbrirCaso" class="space-y-4 pt-4 border-t border-slate-100">
                    <div>
                        <label for="id_tipo_caso" class="block text-sm font-bold text-slate-700">Tipo de Caso *</label>
                        <select name="id_tipo_caso" id="id_tipo_caso" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($tiposCaso as $tc)
                                <option value="{{ $tc->id_tipo_caso }}">{{ $tc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="descripcion_caso" class="block text-sm font-bold text-slate-700">Descripción o Diagnóstico Inicial</label>
                        <textarea name="descripcion_caso" id="descripcion_caso" rows="3" placeholder="Detalle inicial de la apertura de caso..." class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                </div>

                <!-- Campos condicionales para Programar Cita -->
                <div id="seccionCita" class="space-y-4 pt-4 border-t border-slate-100 hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="inicio_cita" class="block text-sm font-bold text-slate-700">Fecha y Hora de la Cita *</label>
                            <input type="datetime-local" name="inicio_cita" id="inicio_cita" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="modalidad" class="block text-sm font-bold text-slate-700">Modalidad *</label>
                            <select name="modalidad" id="modalidad" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="Presencial">Presencial (Oficina Bienestar Informática)</option>
                                <option value="Virtual">Virtual (Google Meet)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Campos condicionales para Descartar -->
                <div id="seccionDescartar" class="space-y-4 pt-4 border-t border-slate-100 hidden">
                    <div>
                        <label for="motivo_descarte" class="block text-sm font-bold text-slate-700">Justificación del Descarte *</label>
                        <textarea name="motivo_descarte" id="motivo_descarte" rows="3" placeholder="Explique las razones por las cuales se descarta la alerta..." class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold shadow-sm transition">
                        Guardar Resolución de Alerta
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('input[name="accion"]');
            const secAbrir = document.getElementById('seccionAbrirCaso');
            const secCita = document.getElementById('seccionCita');
            const secDescartar = document.getElementById('seccionDescartar');

            function actualizarVisibilidad() {
                const sel = document.querySelector('input[name="accion"]:checked')?.value;
                secAbrir.classList.toggle('hidden', sel !== 'abrir_caso');
                secCita.classList.toggle('hidden', sel !== 'programar_cita');
                secDescartar.classList.toggle('hidden', sel !== 'descartar');
            }

            radios.forEach(r => r.addEventListener('change', actualizarVisibilidad));
            actualizarVisibilidad();
        });
    </script>
</body>
</html>
