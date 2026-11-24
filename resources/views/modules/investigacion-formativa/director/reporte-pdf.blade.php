<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Investigación Formativa</title>
    <style>
        @page { margin: 16mm 13mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17224d; font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.35; }
        h1 { margin: 2mm 0 1mm; font-size: 17pt; }
        h2 { margin: 6mm 0 2mm; padding-bottom: 1mm; border-bottom: 1px solid #c9d2e3; font-size: 12pt; }
        h3 { margin: 0 0 1.5mm; font-size: 10.5pt; }
        p { margin: 0 0 2mm; }
        .eyebrow { margin: 0; color: #4b5b7c; font-size: 8.5pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .meta { margin: 0 0 1mm; color: #4b5b7c; font-size: 9pt; }
        .nota { color: #4b5b7c; font-size: 8.5pt; }
        .alerta { margin: 3mm 0; padding: 2.5mm 3mm; background: #fff6e5; border: 1px solid #e5c27a; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 3mm; }
        th, td { padding: 1.6mm 2mm; border: 1px solid #c9d2e3; text-align: left; vertical-align: top; }
        td { word-wrap: break-word; }
        th { background: #eef2f9; font-size: 8.5pt; }
        td.num, th.num { text-align: right; }
        td.num { white-space: nowrap; }
        tr { page-break-inside: avoid; }
        .bloque { page-break-inside: avoid; }
        .barra { height: 3mm; background: #e7ecf5; border: 1px solid #c9d2e3; }
        .barra span { display: block; height: 2.4mm; background: #2f7d5c; }
        .pie { margin-top: 5mm; padding-top: 2mm; border-top: 1px solid #c9d2e3; color: #4b5b7c; font-size: 8pt; }
    </style>
</head>
<body>
    <p class="eyebrow">Investigación Formativa · Reporte de dirección</p>
    <h1>{{ $seccion->cursoPlan->curso->nombre }}</h1>
    <p class="meta">
        Sección {{ $seccion->codigo }} · Periodo {{ $seccion->periodo->codigo }} ·
        Escuela {{ $escuela ?? 'No disponible' }}
    </p>
    <p class="meta">
        Generado el {{ $generadoEn }} (hora de Lima) · Filtro de línea:
        {{ $linea ?? 'Todas las líneas' }}
    </p>

    @if ($linea !== null)
        <p class="alerta">
            Las calificaciones y las decisiones se limitan a la línea {{ $linea }}.
            La encuesta corresponde a la sección completa y no se desagrega por línea.
        </p>
    @endif

    <h2>Resumen de la sección</h2>
    <table>
        <tbody>
            <tr>
                <th>Encuesta</th>
                <td>{{ $reporte->estado ?? 'Sin encuesta' }}</td>
                <th>Respuestas</th>
                <td class="num">{{ $reporte->respuestas_count }}</td>
                <th>Participantes activos</th>
                <td class="num">{{ $reporte->roster_activo }}</td>
            </tr>
            <tr>
                <th>Participación</th>
                <td>{{ $reporte->participacion_porcentaje === null ? 'No calculable' : $reporte->participacion_porcentaje.'%' }}</td>
                <th>Proyectos en alcance</th>
                <td class="num">{{ count($reporte->grupos) }}</td>
                <th>Proyectos completos</th>
                <td class="num">{{ $reporte->promedio_denominador }}</td>
            </tr>
            <tr>
                <th>Avances evaluados</th>
                <td class="num">{{ $reporte->entregas_evaluadas }}</td>
                <th>Trabajos aprobados</th>
                <td class="num">{{ $reporte->aprobados_count }}</td>
                <th>Trabajos destacados</th>
                <td class="num">{{ $reporte->destacados_count }}</td>
            </tr>
        </tbody>
    </table>

    @php
        $satisfaccionIndicador = $reporte->indicador_satisfaccion;
        $logroIndicador = $reporte->indicador_logro_competencias;
    @endphp

    <h2>Indicador 3.2 de la sección</h2>
    <p>{{ $satisfaccionIndicador->nombre }}</p>
    <table>
        <tbody>
            <tr>
                <th>Valor</th>
                <td class="num">
                    {{ $satisfaccionIndicador->valor === null ? 'No calculable' : $satisfaccionIndicador->valor.'%' }}
                </td>
                <th>Satisfechos</th>
                <td class="num">{{ $satisfaccionIndicador->numerador }}</td>
                <th>Respondientes</th>
                <td class="num">{{ $satisfaccionIndicador->denominador }}</td>
            </tr>
            <tr>
                <th>Pregunta</th>
                <td colspan="5">
                    {{ $satisfaccionIndicador->pregunta ?? 'Sin pregunta configurada' }}
                </td>
            </tr>
        </tbody>
    </table>
    <p class="nota">
        <strong>Regla:</strong> {{ $satisfaccionIndicador->regla }}
    </p>
    @if ($satisfaccionIndicador->provisional)
        <p class="nota">Configuración provisional. La fuente debe sustituirla antes de un uso institucional.</p>
    @endif
    @if ($satisfaccionIndicador->fuente !== null)
        <p class="nota">Fuente: {{ $satisfaccionIndicador->fuente }}</p>
    @endif
    @if ($satisfaccionIndicador->motivo !== null)
        <p class="alerta">{{ $satisfaccionIndicador->motivo }}</p>
    @endif

    @if ($satisfaccionIndicador->categorias !== [])
        <table>
            <thead>
                <tr>
                    <th class="num">Valor</th>
                    <th>Categoría</th>
                    <th class="num">Estudiantes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($satisfaccionIndicador->categorias as $categoria)
                    <tr>
                        <td class="num">{{ $categoria->valor }}</td>
                        <td>
                            {{ $categoria->etiqueta }}
                            {{ $categoria->satisfecho ? ' · Satisfactoria' : '' }}
                        </td>
                        <td class="num">{{ $categoria->conteo }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="nota">
        El indicador agregado 3.1 se calcula en el resumen de dirección con el alcance de escuela,
        periodo y curso. Este PDF de sección no lo duplica.
    </p>

    <h2>Indicador 3.3 de la sección</h2>
    <p>{{ $logroIndicador->nombre }}</p>
    <table>
        <tbody>
            <tr>
                <th>Valor</th>
                <td class="num">
                    {{ $logroIndicador->valor === null ? 'No calculable' : $logroIndicador->valor.'%' }}
                </td>
                <th>En banda lograda</th>
                <td class="num">{{ $logroIndicador->numerador }}</td>
                <th>Estudiantes válidos</th>
                <td class="num">{{ $logroIndicador->denominador }}</td>
            </tr>
            <tr>
                <th>Cobertura</th>
                <td class="num">
                    {{ $logroIndicador->cobertura === null ? 'No calculable' : $logroIndicador->cobertura.'%' }}
                </td>
                <th>Esperados</th>
                <td class="num">{{ $logroIndicador->esperados }}</td>
                <th>Estado</th>
                <td>{{ $logroIndicador->estado }}</td>
            </tr>
        </tbody>
    </table>
    <p class="nota">
        <strong>Regla:</strong> {{ $logroIndicador->regla }}
    </p>
    @if ($logroIndicador->provisional)
        <p class="nota">Configuración provisional. La matriz, los pesos y las bandas deben sustituirla antes de un uso institucional.</p>
    @endif
    @if ($logroIndicador->fuente !== null)
        <p class="nota">Fuente: {{ $logroIndicador->fuente }}</p>
    @endif
    @if ($logroIndicador->motivo !== null)
        <p class="alerta">{{ $logroIndicador->motivo }}</p>
    @endif

    @if ($logroIndicador->competencias !== [])
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Competencia</th>
                    <th class="num">Logro</th>
                    <th class="num">Cobertura</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logroIndicador->competencias as $competencia)
                    <tr>
                        <td>{{ $competencia->codigo }}</td>
                        <td>{{ $competencia->descripcion }}</td>
                        <td class="num">
                            {{ $competencia->valor === null ? 'No calculable' : $competencia->valor.'%' }}
                        </td>
                        <td class="num">
                            {{ $competencia->cobertura === null ? 'No calculable' : $competencia->cobertura.'%' }}
                        </td>
                        <td>{{ $competencia->estado }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Calificaciones por grupo</h2>
    @if ($reporte->grupos === [])
        <p>No hay grupos registrados en la sección para el filtro seleccionado.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 24%;">Grupo</th>
                    <th style="width: 16%;">Línea</th>
                    <th class="num">Unidad 1</th>
                    <th class="num">Unidad 2</th>
                    <th class="num">Unidad 3</th>
                    <th class="num">Nota final</th>
                    <th class="num">Avances evaluados</th>
                    <th style="width: 14%;">Decisión docente</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporte->grupos as $grupo)
                    <tr>
                        <td>{{ $grupo->nombre }}</td>
                        <td>{{ $grupo->linea_nombre ?? 'Sin proyecto' }}</td>
                        @foreach ([1, 2, 3] as $unidad)
                            <td class="num">{{ $grupo->notas_unidades[$unidad] ?? ($grupo->id_proyecto === null ? '—' : 'Pendiente') }}</td>
                        @endforeach
                        <td class="num">{{ $grupo->nota_final ?? 'Pendiente' }}</td>
                        <td class="num">{{ $grupo->entregas_evaluadas }} / 3</td>
                        <td>
                            {{ $grupo->aprobado ? 'Aprobado' : 'Sin aprobación' }}
                            {{ $grupo->destacado ? ' · Destacado' : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="nota">
            Una unidad queda pendiente cuando su versión más reciente no tiene evaluación.
            Los proyectos incompletos no reciben una nota final y no se convierten en ceros.
        </p>
    @endif

    <h2>Clasificación y trabajo destacado</h2>
    @if ($reporte->promedio_denominador === 0)
        <p>No hay proyectos con las tres unidades evaluadas para calcular el promedio o la clasificación.</p>
    @else
        <p>
            Promedio de la sección: <strong>{{ $reporte->promedio_curso }}</strong>.
            Proyectos incluidos: {{ $reporte->promedio_denominador }}.
        </p>
        <p>
            Mejor nota: <strong>{{ $reporte->mejor_nota }}</strong> ·
            {{ implode(', ', array_map(static fn (object $grupo): string => $grupo->nombre, $reporte->grupos_mejor)) }}
        </p>
        <p>
            Peor nota: <strong>{{ $reporte->peor_nota }}</strong> ·
            {{ implode(', ', array_map(static fn (object $grupo): string => $grupo->nombre, $reporte->grupos_peor)) }}
        </p>
        @if ($reporte->promedio_denominador === 1)
            <p class="nota">Solo hay un proyecto completo; ocupa tanto la mejor como la peor posición.</p>
        @endif
    @endif

    @if ($reporte->seleccion_destacada === null)
        <p>Sin trabajo destacado vigente para el filtro seleccionado.</p>
    @else
        <div class="bloque">
            <p>
                <strong>{{ $reporte->seleccion_destacada->nombre }}</strong> ·
                {{ $reporte->seleccion_destacada->titulo }}
            </p>
            <p class="nota">
                Selección manual del docente responsable.
                @if ($reporte->seleccion_destacada->desactualizada)
                    La evidencia cambió; la decisión requiere confirmación.
                @elseif ($reporte->seleccion_destacada->decidido_en !== null)
                    Registrada el {{ $reporte->seleccion_destacada->decidido_en }}.
                @endif
            </p>
        </div>
    @endif

    <h2>Resultados de la encuesta</h2>
    @if ($reporte->preguntas === [])
        <p>No hay preguntas de encuesta registradas en la sección.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Pregunta</th>
                    <th class="num">Respuestas</th>
                    <th class="num">Promedio</th>
                    @foreach (range(1, 5) as $valor)
                        <th class="num">Valor {{ $valor }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($reporte->preguntas as $pregunta)
                    <tr>
                        <td>{{ $pregunta->texto }}</td>
                        <td class="num">
                            {{ array_sum(array_map(static fn (object $fila): int => $fila->conteo, $pregunta->distribucion)) }}
                        </td>
                        <td class="num">{{ $pregunta->valor_promedio ?? 'Sin respuestas' }}</td>
                        @foreach ($pregunta->distribucion as $fila)
                            <td class="num">{{ $fila->conteo }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3>Distribución por pregunta</h3>
        @foreach ($reporte->preguntas as $pregunta)
            <div class="bloque">
                <p class="nota">{{ $pregunta->texto }}</p>
                @php
                    $totalPregunta = array_sum(array_map(static fn (object $fila): int => $fila->conteo, $pregunta->distribucion));
                @endphp
                <table>
                    <tbody>
                        @foreach ($pregunta->distribucion as $fila)
                            <tr>
                                <th>Valor {{ $fila->valor }}</th>
                                <td class="num">{{ $fila->conteo }}</td>
                                <td>
                                    <div class="barra">
                                        <span style="width: {{ $totalPregunta > 0 ? round($fila->conteo / $totalPregunta * 100, 1) : 0 }}%;"></span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
        <p class="nota">
            Escala 1 a 5. Los promedios y las distribuciones son estadística descriptiva.
            No definen satisfacción estudiantil ni un indicador oficial.
        </p>
    @endif

    <h2>Notas de lectura</h2>
    <ul>
        <li>La participación usa respuestas / participantes activos actuales. Puede superar el 100% después de retiros. Sin participantes activos, la tasa no se calcula.</li>
        <li>La aprobación final y el trabajo destacado son decisiones explícitas del docente. No se deducen de la nota ni del estado del título.</li>
        <li>Este reporte no incluye comentarios de la encuesta. La pantalla conserva su paginación confidencial.</li>
        <li>Los conteos descriptivos no son los indicadores 3.1, 3.2 y 3.3. Los indicadores 3.2 y 3.3 identifican su configuración provisional y no reemplazan las definiciones institucionales.</li>
        <li>El reporte se generó una vez con los datos vigentes al momento indicado. No crea registros de archivo.</li>
    </ul>

    <p class="pie">
        Portal Gestión Dirección · Investigación Formativa · Documento descriptivo, no oficial.
    </p>
</body>
</html>
