<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Resultados de Investigación Formativa</h2></x-slot>
    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')
        <header>
            <h1 class="text-2xl font-bold">{{ $seccion->cursoPlan->curso->nombre }} · {{ $seccion->codigo }}</h1>
            <p>Periodo {{ $seccion->periodo->codigo }} · Estado académico: {{ $seccion->estado }}</p>
            <a class="text-indigo-700 underline" href="{{ route('investigacion-formativa.director.index', request()->only(['escuela', 'periodo'])) }}">Volver al resumen e historial por periodo</a>
        </header>
        <nav class="flex flex-wrap gap-4" aria-label="Secciones de resultados">
            <a class="text-indigo-700 underline" href="#encuesta">Encuesta</a>
            <a class="text-indigo-700 underline" href="#calificaciones">Calificaciones</a>
            <a class="text-indigo-700 underline" href="{{ route('investigacion-formativa.director.index', ['periodo' => $seccion->periodo->codigo]) }}">Historial del periodo</a>
        </nav>
        <section id="encuesta" class="space-y-4 rounded-lg border bg-white p-4" aria-labelledby="titulo-encuesta">
            <h2 id="titulo-encuesta" class="text-lg font-semibold">Resultados de la encuesta</h2>
            <p>Estado: {{ $reporte->estado ?? 'Sin encuesta' }}. Respuestas: {{ $reporte->respuestas_count }}.</p>
            <p>Participantes activos actuales: {{ $reporte->roster_activo }}.
                Participación: {{ $reporte->participacion_porcentaje === null ? 'No calculable sin participantes activos' : $reporte->participacion_porcentaje.'%' }}.</p>
            <p class="text-sm text-gray-600">La tasa usa el padrón activo actual. Puede superar el 100% después de retiros; las respuestas anteriores se conservan.</p>
            @if ($reporte->respuestas_count === 0)
                <p>Todavía no hay respuestas registradas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="text-left font-medium">Promedios y distribución de las respuestas (escala 1–5)</caption>
                        <thead><tr><th scope="col" class="p-2">Pregunta</th><th scope="col" class="p-2">Promedio</th>@foreach ([1, 2, 3, 4, 5] as $valor)<th scope="col" class="p-2">Valor {{ $valor }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ($reporte->preguntas as $pregunta)
                            <tr><th scope="row" class="p-2">{{ $pregunta->texto }}</th><td class="p-2">{{ $pregunta->valor_promedio ?? 'Sin respuestas' }}</td>
                                @foreach ($pregunta->distribucion as $valor)<td class="p-2">{{ $valor->conteo }}</td>@endforeach
                            </tr>
                        @endforeach</tbody>
                    </table>
                </div>
            @endif
            <h3 class="font-semibold">Comentarios confidenciales</h3>
            <p class="text-sm text-gray-600">Las respuestas se almacenan con una identidad. Esta vista omite la identidad de quien responde; no son respuestas anónimas.</p>
            <form method="GET" action="{{ route('investigacion-formativa.director.resultados', $seccion) }}">
                @foreach (request()->only(['escuela', 'periodo']) as $nombre => $valor)<input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">@endforeach
                <label for="por-pagina">Comentarios por página</label>
                <select id="por-pagina" name="por_pagina" class="rounded border-gray-300">
                    @foreach (array_unique([10, 25, 50, 100, $porPagina]) as $cantidad)<option value="{{ $cantidad }}" @selected($porPagina === $cantidad)>{{ $cantidad }}</option>@endforeach
                </select>
                <button class="rounded bg-indigo-700 px-3 py-2 text-white" type="submit">Aplicar</button>
            </form>
            @forelse ($reporte->comentarios as $comentario)
                <blockquote class="whitespace-pre-wrap break-words border-l-4 border-gray-200 pl-4">{{ $comentario->comentario }}</blockquote>
            @empty
                <p>No hay comentarios registrados.</p>
            @endforelse
            {{ $reporte->comentarios->fragment('encuesta')->links() }}
        </section>
        <section id="calificaciones" class="space-y-4 rounded-lg border bg-white p-4" aria-labelledby="titulo-calificaciones">
            <h2 id="titulo-calificaciones" class="text-lg font-semibold">Calificaciones de los proyectos</h2>
            <p class="text-sm text-gray-600">Escala de 0 a 20. Se usa la última versión de cada unidad. Una nueva versión sin evaluar queda pendiente. La nota final requiere las tres unidades evaluadas.</p>
            <p>Avances actuales evaluados: {{ $reporte->entregas_evaluadas }}.</p>
            @if ($reporte->grupos === [])
                <p>No hay grupos registrados.</p>
            @else
                <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
                    <thead><tr><th scope="col" class="p-2">Grupo</th>@foreach ([1, 2, 3] as $unidad)<th scope="col" class="p-2">Unidad {{ $unidad }}</th>@endforeach<th scope="col" class="p-2">Evaluadas</th><th scope="col" class="p-2">Nota final</th></tr></thead>
                    <tbody>@foreach ($reporte->grupos as $grupo)
                        <tr><th scope="row" class="p-2">{{ $grupo->nombre }}</th>@foreach ($grupo->notas_unidades as $nota)<td class="p-2">{{ $nota ?? 'Pendiente' }}</td>@endforeach<td class="p-2">{{ $grupo->entregas_evaluadas }}/3</td><td class="p-2">{{ $grupo->nota_final ?? 'Pendiente' }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
            @if ($reporte->promedio_denominador === 0)
                <p>No hay proyectos con las tres unidades evaluadas para calcular el promedio o la clasificación.</p>
            @else
                <p>Promedio del curso: {{ $reporte->promedio_curso }}. Proyectos incluidos: {{ $reporte->promedio_denominador }}.</p>
                @if ($reporte->promedio_denominador === 1)<p>Solo hay un proyecto completo; ocupa tanto la mejor como la peor posición.</p>@endif
                <h3 class="font-semibold">Mejor nota: {{ $reporte->mejor_nota }}</h3>
                <ul class="list-inside list-disc">@foreach ($reporte->grupos_mejor as $grupo)<li>{{ $grupo->nombre }}</li>@endforeach</ul>
                <h3 class="font-semibold">Peor nota: {{ $reporte->peor_nota }}</h3>
                <ul class="list-inside list-disc">@foreach ($reporte->grupos_peor as $grupo)<li>{{ $grupo->nombre }}</li>@endforeach</ul>
                <p class="text-sm text-gray-600">Se incluyen todos los empates. Los proyectos incompletos quedan fuera del promedio y de la clasificación.</p>
            @endif
        </section>
    </main>
</x-app-layout>
