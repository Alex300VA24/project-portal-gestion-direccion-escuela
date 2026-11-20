<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Revisión de aportes de Tutoría</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        @isset($ambitos)
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
            <ul class="divide-y rounded-lg bg-white px-5">
                @forelse ($ambitos as $ambito)
                    <li class="py-4">
                        <a class="font-medium text-indigo-700 underline"
                           href="{{ route('seguimiento-estudiante.planes.presentados', [$ambito->id_escuela, $ambito->id_periodo]) }}">
                            {{ $ambito->escuela }} · {{ $ambito->periodo }}
                        </a>
                    </li>
                @empty
                    <li class="py-4">Tu cuenta aún no tiene un ámbito de Coordinación para un período activo.</li>
                @endforelse
            </ul>
        @else
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.planes.coordinacion') }}">Volver a escuelas y períodos</a>
            <h2 class="font-semibold">{{ $ambito->escuela }} · {{ $ambito->periodo }}</h2>
            @if (session('status'))
                <p role="status" class="rounded-md bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
            @endif
            <x-input-error :messages="$errors->all()" />

            @forelse ($planes as $plan)
                @php($presentado = $plan['presentado'])
                <article class="space-y-3 rounded-lg bg-white p-5">
                    <h3 class="font-semibold">{{ $plan['nombre'] }}</h3>
                    <p>Versión presentada {{ $presentado['version'] }} · {{ $presentado['population'] }} estudiantes</p>
                    <details>
                        <summary class="cursor-pointer font-medium text-indigo-700">Ver aporte presentado</summary>
                        <dl class="mt-3 space-y-2">
                            @foreach ($campos as $campo => $etiqueta)
                                <dt class="font-medium">{{ $etiqueta }}</dt>
                                <dd class="whitespace-pre-wrap break-words">{{ $presentado['fields'][$campo] }}</dd>
                            @endforeach
                        </dl>
                        <h4 class="mt-3 font-medium">Cronograma</h4>
                        <ul class="list-disc pl-5">
                            @foreach ($presentado['activities'] as $actividad)
                                <li class="break-words">
                                    {{ $actividad['title'] }} · {{ $actividad['timing'] }} · {{ $actividad['mode'] }} · {{ $actividad['target'] }} {{ mb_strtolower($actividad['unit']) }}
                                    @if ($actividad['evidence'])<p>{{ $actividad['evidence'] }}</p>@endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                    @if ($plan['pendiente'])
                        <form method="POST" class="space-y-3" action="{{ route('seguimiento-estudiante.planes.revisar', [$idEscuela, $idPeriodo, $plan['id_persona']]) }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ $plan['revision'] }}">
                            <input type="hidden" name="version_presentada" value="{{ $presentado['version'] }}">
                            <x-input-label for="comentario-{{ $plan['id_persona'] }}" value="Comentario de revisión" />
                            <textarea id="comentario-{{ $plan['id_persona'] }}" name="comentario" minlength="10" maxlength="2000" required
                                      class="block w-full rounded-md border-gray-300"></textarea>
                            <x-secondary-button type="submit" name="decision" value="OBSERVADO">Registrar observación</x-secondary-button>
                            <x-primary-button name="decision" value="RECIBIDO">Registrar recepción</x-primary-button>
                        </form>
                    @elseif (isset($presentado['review']))
                        <p>{{ $presentado['review']['decision'] === 'RECIBIDO' ? 'Recepción registrada' : 'Con observaciones' }}</p>
                        <p class="whitespace-pre-wrap break-words">{{ $presentado['review']['comment'] }}</p>
                    @endif
                </article>
            @empty
                <p class="rounded-lg bg-white p-5">Aún no hay aportes presentados para este ámbito.</p>
            @endforelse
            {{ $planes->links() }}
        @endisset
    </section>
</x-app-layout>
