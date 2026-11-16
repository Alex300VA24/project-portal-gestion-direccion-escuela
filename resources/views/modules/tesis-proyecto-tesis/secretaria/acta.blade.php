@extends('modules.tesis-proyecto-tesis.layout')

@php
    $nombreDoc = fn ($id) => ($docentes[$id]['nombre'] ?? '—');
    $codigoDoc = fn ($id) => ($docentes[$id]['codigo'] ?? '—').(! empty($docentes[$id]['orcid']) ? ' · ORCID '.$docentes[$id]['orcid'] : '');
    $graduados = collect($integrantes)->pluck('nombre')->map(fn ($n) => 'Br. '.$n)->implode(' y ');
    $marca = fn (string $valor) => $acta->resultado === $valor ? 'X' : ' ';
@endphp

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.secretaria.actas') }}">Actas y dictámenes</a> › {{ $proyecto->codigo }}</p>
            <h1>{{ $acta->tipo === 'Dictamen' ? 'Dictamen de aprobación del proyecto' : 'Acta de sustentación' }}</h1>
            <p>N.° {{ $acta->numero }} · {{ $acta->resultado }}</p>
        </div>
        <button type="button" class="tz-btn tz-btn-secondary" onclick="window.print()">Imprimir</button>
    </div>

    <article class="tz-doc" aria-label="Documento para imprimir">
        <p class="tz-doc-center"><strong>UNIVERSIDAD NACIONAL DE TRUJILLO</strong><br>FACULTAD DE CIENCIAS FÍSICAS Y MATEMÁTICAS<br>ESCUELA PROFESIONAL DE INGENIERÍA INFORMÁTICA</p>

        @if ($acta->tipo === 'Dictamen')
            <h2>DICTAMEN DE APROBACIÓN DEL PROYECTO DE TESIS N.° {{ $acta->numero }}</h2>
            <p>Señor Director de la Escuela Profesional de Ingeniería Informática:</p>
            <p>Los miembros del Jurado que suscriben, luego de revisar con la rúbrica de verificación el proyecto de tesis titulado
                «{{ $proyecto->titulo }}», presentado por {{ $graduados }}, con el asesoramiento de {{ $asesor['nombre'] ?? '—' }},
                dictaminan que el proyecto <strong>reúne las condiciones para ser ejecutado</strong>, por lo que se aprueba por unanimidad.</p>
            <p>Trujillo, {{ $acta->emitida_en?->translatedFormat('d \d\e F \d\e Y') }}.</p>
        @else
            <h2>ACTA DE SUSTENTACIÓN DE TESIS PARA OPTAR EL TÍTULO DE INGENIERO INFORMÁTICO</h2>
            <p>En la ciudad de Trujillo, en {{ $ambiente }} de la Escuela Profesional de Ingeniería Informática, Facultad de Ciencias Físicas y Matemáticas,
                siendo las {{ $sustentacion?->fecha_hora?->format('H:i') }} horas del día {{ $sustentacion?->fecha_hora?->translatedFormat('d \d\e F \d\e Y') }},
                se reunió el Jurado conformado por:</p>
            <ul style="list-style: none; padding: 0">
                @foreach ($jurado as $j)
                    <li>{{ $j['cargo'] }}(a): {{ $nombreDoc($j['id_docente']) }} · código {{ $codigoDoc($j['id_docente']) }}</li>
                @endforeach
                <li>Asesor(a): {{ $nombreDoc($idAsesor) }} · código {{ $codigoDoc($idAsesor) }}</li>
            </ul>
            <p>Para el acto de sustentación de la tesis titulada «{{ $proyecto->titulo }}», con el fin de optar el Título Profesional de Ingeniero Informático
                por {{ count($integrantes) > 1 ? 'los graduados' : 'el graduado' }}: {{ $graduados }}.</p>
            <p>Concluido el acto de sustentación (intento N.° {{ $sustentacion?->numero_intento }}, hora de término {{ $sustentacion?->hora_fin ? substr($sustentacion->hora_fin, 0, 5) : '—' }}),
                y luego de que {{ count($integrantes) > 1 ? 'los Bachilleres han' : 'el Bachiller ha' }} dado respuesta a las preguntas respectivas, el Jurado Evaluador declara:</p>
            <p style="font-family: 'Fira Code', monospace; font-size: 14px">
                1. ( {{ $marca('Aprobado con mención honrosa') }} ) Aprobado, con mención honrosa.<br>
                2. ( {{ $marca('Aprobado por unanimidad') }} ) Aprobado, por unanimidad.<br>
                3. ( {{ $marca('Aprobado por mayoría') }} ) Aprobado, por mayoría.<br>
                4. ( {{ $marca('Desaprobado') }} ) Desaprobado.
            </p>
            <p>Por lo tanto, {{ count($integrantes) > 1 ? 'los graduados se encuentran' : 'el graduado se encuentra' }} expeditos ( {{ $acta->condicion === 'Expedito' ? 'X' : ' ' }} ),
                impedidos ( {{ $acta->condicion === 'Impedido' ? 'X' : ' ' }} ) para realizar los trámites correspondientes.</p>
        @endif

        <div class="tz-firmas">
            @foreach ($jurado as $j)
                <div>{{ $nombreDoc($j['id_docente']) }}<br>{{ $j['cargo'] }}</div>
            @endforeach
            @if ($acta->tipo !== 'Dictamen')
                <div>{{ $nombreDoc($idAsesor) }}<br>Asesor(a)</div>
            @endif
        </div>
    </article>

    <div class="tz-grid-2">
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Documento firmado</h2></div>
            <div class="tz-panel-body">
                @if ($acta->id_archivo_firmado)
                    <p>Registrado el {{ $acta->firmada_en?->format('d/m/Y H:i') }} · <a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['acta-firmada', $acta->uuid]) }}" target="_blank" rel="noopener">Ver PDF firmado</a></p>
                @else
                    <form method="POST" action="{{ route('tesis-proyecto-tesis.secretaria.actas.firmada', $acta) }}" enctype="multipart/form-data" class="tz-form">
                        @csrf
                        <div class="tz-field">
                            <label for="archivo">Escaneo firmado (PDF, máximo 10 MB) *</label>
                            <input id="archivo" name="archivo" type="file" accept="application/pdf,.pdf" class="tz-input" required>
                            @error('archivo') <span class="tz-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="tz-actions"><button class="tz-btn tz-btn-primary" type="submit">Subir documento firmado</button></div>
                    </form>
                @endif
            </div>
        </section>
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Informar al Director</h2></div>
            <div class="tz-panel-body">
                @if ($acta->informado_director_en)
                    <p>Informado el {{ $acta->informado_director_en->format('d/m/Y H:i') }}.</p>
                @else
                    <form method="POST" action="{{ route('tesis-proyecto-tesis.secretaria.actas.informar', $acta) }}" class="tz-form">
                        @csrf
                        <p class="tz-muted">{{ $acta->tipo === 'Sustentacion' && $acta->condicion === 'Expedito' ? 'Al informar, el estado del proyecto cambia a «Tesis aprobada».' : 'Se envía el aviso con el documento firmado.' }}</p>
                        @error('informar') <span class="tz-error">{{ $message }}</span> @enderror
                        <div class="tz-actions"><button class="tz-btn tz-btn-navy" type="submit" @disabled(! $acta->id_archivo_firmado)>Informar al Director</button></div>
                    </form>
                @endif
            </div>
        </section>
    </div>
@endsection
