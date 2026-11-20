<x-app-layout>
    <main style="max-width:72rem;margin:auto;padding:1.5rem">
        <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.consulta-publicados') }}">Horarios publicados</a> / Horario semanal</nav>
        <h1>Horario semanal publicado</h1>
        <p>Periodo {{ $periodo->codigo }} · {{ $descripcion }}@if (! empty($filtros['seccion'])) · Sección {{ $filtros['seccion'] }}@endif</p>
        @forelse (['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'] as $dia)
            @php($actividades = $sesiones->where('dia_semana', $dia))
            @if ($actividades->isNotEmpty())
                <section aria-labelledby="dia-{{ $dia }}">
                    <h2 id="dia-{{ $dia }}">{{ $dia }}</h2>
                    <div style="overflow-x:auto"><table>
                        <caption>Actividades del {{ $dia }}</caption>
                        <thead><tr><th scope="col">Hora</th><th scope="col">Curso</th><th scope="col">Actividad</th><th scope="col">Plan / ciclo / sección</th><th scope="col">Ambiente</th><th scope="col">Docente</th><th scope="col">Publicación</th></tr></thead>
                        <tbody>@foreach ($actividades as $a)
                            <tr><td>{{ $a->hora_inicio }}–{{ $a->hora_fin }}</td><td>{{ $a->curso_codigo }} · {{ $a->curso_nombre }}</td><td>{{ $a->tipo_actividad }}</td><td>{{ $a->plan_nombre ?? 'Plan no disponible' }} / {{ $a->ciclo }} / {{ $a->seccion }}</td><td>{{ $a->ambiente ?? 'No indicado' }}</td><td>{{ trim(($a->nombres ?? '').' '.($a->apellido_paterno ?? '').' '.($a->apellido_materno ?? '')) ?: 'No indicado' }}</td><td>{{ $a->ambito }} · versión {{ $a->version }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                </section>
            @endif
        @empty
        @endforelse
        @if ($sesiones->isEmpty()) <p role="status">No se encontraron actividades publicadas para los filtros seleccionados.</p> @endif
        <p><a href="{{ route('gestion-horarios.consulta-publicados', $filtros) }}">Volver a la consulta</a></p>
    </main>
</x-app-layout>
