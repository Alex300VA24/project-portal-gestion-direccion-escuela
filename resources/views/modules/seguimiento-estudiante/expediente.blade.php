<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Expediente de seguimiento</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6" aria-labelledby="estudiante">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
        <div class="rounded-lg bg-white p-5">
            <h2 id="estudiante" class="text-lg font-semibold text-gray-900">
                {{ $perfil->persona->nombres }} {{ $perfil->persona->apellido_paterno }}
                {{ $perfil->persona->apellido_materno }}
            </h2>
            <dl class="mt-3 space-y-2 text-gray-700">
                <div>
                    <dt class="font-medium">Código universitario</dt>
                    <dd>{{ $perfil->codigo_universitario }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Estado académico</dt>
                    <dd>{{ $perfil->estado }}</dd>
                </div>
            </dl>
        </div>

        @if (session('status'))
            <p role="status" class="rounded-md bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
        @endif

        @if ((int) $perfil->id_persona === $persona)
            <a class="inline-block text-indigo-700 underline" href="{{ route('seguimiento-estudiante.nivelacion.ingreso', $perfil->id_estudiante) }}">
                Consultar mi ingreso y nivelación
            </a>
        @endif

        @if ($puedeSolicitar)
            <div class="rounded-lg bg-white p-5">
                <h2 class="font-semibold text-gray-900">Solicitar tutoría</h2>
                @if ($periodos->isEmpty())
                    <p class="mt-2 text-gray-700">La solicitud estará disponible cuando haya un período activo.</p>
                @else
                    <form method="POST" action="{{ route('seguimiento-estudiante.solicitudes.tutoria.store', $perfil->id_estudiante) }}"
                          class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="clave_envio" value="{{ $claveEnvio }}">
                        <x-input-error :messages="$errors->get('clave_envio')" />
                        <div>
                            <x-input-label for="id_periodo" value="Período" />
                            <select id="id_periodo" name="id_periodo" required class="mt-1 rounded-md border-gray-300">
                                @foreach ($periodos as $periodo)
                                    <option value="{{ $periodo->id_periodo }}" @selected((string) old('id_periodo') === (string) $periodo->id_periodo)>
                                        {{ $periodo->codigo }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('id_periodo')" />
                        </div>
                        <div>
                            <x-input-label for="motivo" value="Motivo de la solicitud" />
                            <textarea id="motivo" name="motivo" rows="3" required minlength="5" maxlength="2000"
                                      class="mt-1 block w-full rounded-md border-gray-300">{{ old('motivo') }}</textarea>
                            <x-input-error :messages="$errors->get('motivo')" />
                        </div>
                        <x-primary-button>Enviar solicitud</x-primary-button>
                    </form>
                @endif
            </div>
        @endif

        <div class="space-y-3 rounded-lg bg-white p-5">
            <h2 class="font-semibold text-gray-900">Solicitudes de tutoría</h2>
            <ul class="divide-y divide-gray-200">
                @forelse ($solicitudes as $solicitud)
                    <li class="py-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span class="font-medium text-gray-900">{{ $solicitud->estado }}</span>
                            <span class="text-sm text-gray-600">{{ $solicitud->periodo_codigo }}</span>
                            <span class="text-sm text-gray-600">
                                {{ \Illuminate\Support\Carbon::parse($solicitud->solicitada_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap break-words text-gray-700">{{ $solicitud->descripcion }}</p>
                        @if ($solicitud->estado === 'Programada' && ! $solicitud->responsable_vigente)
                            <p class="mt-2 text-sm text-amber-800">Cita pendiente de revisión por Coordinación.</p>
                        @elseif ($solicitud->fecha_hora)
                            <p class="mt-2 text-sm text-gray-700">
                                {{ \Illuminate\Support\Carbon::parse($solicitud->fecha_hora)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                (hora de Lima) · {{ $solicitud->duracion_minutos }} min · {{ $solicitud->modalidad }}
                            </p>
                            <p class="break-words text-sm text-gray-700">{{ $solicitud->lugar }}</p>
                        @endif
                        @if ($solicitud->estado === 'Atendida' && filled($solicitud->acuerdos))
                            <p class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-700">Acuerdos: {{ $solicitud->acuerdos }}</p>
                            @if ($solicitud->id_compromiso)
                                <p class="text-sm text-gray-700">Compromiso {{ $solicitud->cumplido ? 'cumplido' : 'pendiente' }}</p>
                            @endif
                        @endif
                        @if ((int) $perfil->id_persona === $persona && $solicitud->estado === 'Atendida' && $solicitud->fecha_hora)
                            <a class="mt-2 inline-block text-indigo-700 underline" href="{{ route('seguimiento-estudiante.solicitudes.tutoria.constancia', $solicitud->id_solicitud) }}">
                                Descargar constancia personal de Tutoría
                            </a>
                        @endif
                        @if ($solicitud->cambio_tipo)
                            <div class="mt-3 space-y-1 text-sm text-gray-700">
                                <p class="font-medium">
                                    Petición de {{ $solicitud->cambio_tipo === 'CANCELACION' ? 'cancelación' : 'reprogramación' }}
                                    · {{ $solicitud->cambio_decision ?? 'Pendiente de revisión' }}
                                </p>
                                <p class="whitespace-pre-wrap break-words">{{ $solicitud->cambio_motivo }}</p>
                                @if ($solicitud->cambio_respuesta)
                                    <p class="whitespace-pre-wrap break-words">{{ $solicitud->cambio_respuesta }}</p>
                                @endif
                            </div>
                        @endif
                        @if ($puedeSolicitar)
                            @include('modules.seguimiento-estudiante.solicitar-cambio-tutoria')
                        @endif
                        @if ($puedeProgramar)
                            @include('modules.seguimiento-estudiante.acciones-tutoria')
                        @endif
                    </li>
                @empty
                    <li class="text-gray-700">No hay solicitudes de tutoría disponibles en este expediente.</li>
                @endforelse
            </ul>
            {{ $solicitudes->links() }}
        </div>
        @if ($historicas?->isNotEmpty())
            <div class="space-y-3 rounded-lg bg-white p-5">
                <h2 class="font-semibold text-gray-900">Historial de solicitudes</h2>
                <ul class="divide-y divide-gray-200">
                    @foreach ($historicas as $historica)
                        <li class="py-3">
                            <p class="font-medium text-gray-900">{{ $historica->asunto }}</p>
                            <p class="text-sm text-gray-600">
                                {{ \Illuminate\Support\Carbon::parse($historica->solicitada_en)->setTimezone('America/Lima')->format('d/m/Y') }}
                                · {{ $historica->periodo_codigo ? 'Tutoría · '.$historica->periodo_codigo : 'Pendiente de clasificación' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
                {{ $historicas->links() }}
            </div>
        @endif
        @include('modules.seguimiento-estudiante.evidencia-expediente')
    </section>
</x-app-layout>
