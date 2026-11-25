<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mi ingreso y nivelación</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
        <div class="rounded-lg bg-white p-5">
            <h2 class="font-semibold text-gray-900">Perfil de ingreso</h2>
            @if ($perfil)
                <p>Puntaje de ingreso {{ $perfil->puntaje_ingreso ?? 'sin dato registrado' }}</p>
                @if ($perfil->fecha_evaluacion)
                    <p>Fecha de evaluación {{ $perfil->fecha_evaluacion }}</p>
                @endif
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @forelse ($brechas as $brecha)
                        <li>{{ $brecha->nombre }} · Puntaje {{ $brecha->puntaje ?? 'sin dato' }} · Nivel {{ $brecha->nivel ?? 'sin dato' }}</li>
                    @empty
                        <li>Sin brechas registradas.</li>
                    @endforelse
                </ul>
            @else
                <p>Tu perfil de ingreso aún no tiene datos registrados.</p>
            @endif
        </div>

        <div class="space-y-4 rounded-lg bg-white p-5">
            <h2 class="font-semibold text-gray-900">Mis inscripciones</h2>
            @forelse ($inscripciones as $inscripcion)
                <article class="space-y-2 border-t pt-3">
                    <h3 class="font-medium">{{ $inscripcion->programa }}</h3>
                    <p>{{ $inscripcion->periodo }} · Grupo {{ $inscripcion->grupo }} · {{ $inscripcion->estado }}</p>
                    <a class="inline-block text-indigo-700 underline" href="{{ route('seguimiento-estudiante.nivelacion.resumen', $inscripcion->id_participante) }}">
                        Descargar mi resumen en PDF
                    </a>
                    @if ($materiales->has($inscripcion->id_participante))
                        <h4 class="font-medium">Materiales publicados</h4>
                        <ul class="list-disc pl-5">
                            @foreach ($materiales->get($inscripcion->id_participante) as $material)
                                <li>
                                    <a class="text-indigo-700 underline"
                                       href="{{ route('seguimiento-estudiante.nivelacion.material', [$inscripcion->id_participante, $material->id_archivo]) }}">
                                        {{ $material->titulo ?? 'Material de nivelación' }} (PDF)
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <h4 class="font-medium">Calificaciones por criterio</h4>
                    <ul class="list-disc pl-5">
                        @forelse ($calificaciones->get($inscripcion->id_participante, []) as $nota)
                            <li>{{ $nota->nombre }} · {{ $nota->nota }} · Peso {{ $nota->peso }} %</li>
                        @empty
                            <li>Sin calificaciones registradas.</li>
                        @endforelse
                    </ul>
                    <h4 class="font-medium">Sesiones y asistencia</h4>
                    <ul class="list-disc pl-5">
                        @forelse ($sesiones->get($inscripcion->id_participante, []) as $sesion)
                            <li>{{ $sesion->fecha }} · {{ $sesion->tema ?? 'Sesión' }} · {{ $sesion->estado ?? 'Asistencia pendiente' }}</li>
                        @empty
                            <li>Sin sesiones registradas.</li>
                        @endforelse
                    </ul>
                </article>
            @empty
                <p>Aún no tienes inscripciones de nivelación registradas.</p>
            @endforelse
            {{ $inscripciones->links() }}
        </div>
    </section>
</x-app-layout>
