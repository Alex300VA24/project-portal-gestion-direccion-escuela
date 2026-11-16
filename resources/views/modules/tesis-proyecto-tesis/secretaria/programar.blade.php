@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.secretaria.sustentaciones') }}">Sustentaciones</a> › Programar</p>
            <h1>Programar sustentación</h1>
            <p>Sustentación presencial. Se verifica que el aula, los jurados y el asesor no tengan otra sustentación en el mismo horario.</p>
        </div>
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    <div class="tz-tags">
        <span class="tz-status is-success">3 de 3 conformidades ✓</span>
        <span class="tz-status is-success">Rúbrica {{ $totalCriterios }}/{{ $totalCriterios }} ✓</span>
        @if ($reporte) <span class="tz-status is-success">Turnitin {{ $reporte->porcentaje }} %</span> @endif
        <span class="tz-status is-neutral">Intento {{ $intento }}</span>
    </div>

    @if (! $programable)
        <div class="tz-alert is-info">Este proyecto no está en condiciones de programarse ({{ \App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($proyecto->estado) }}).</div>
    @else
        <div class="tz-grid-2">
            <form method="POST" action="{{ route('tesis-proyecto-tesis.secretaria.sustentaciones.guardar', $proyecto) }}" class="tz-panel" x-data="{ enviando: false }" @submit="enviando = true">
                @csrf
                <div class="tz-panel-head"><h2>Fecha y lugar</h2></div>
                <div class="tz-panel-body tz-fields">
                    <div class="tz-field">
                        <label for="fecha">Fecha *</label>
                        <input id="fecha" name="fecha" type="date" class="tz-input" min="{{ now()->toDateString() }}" value="{{ old('fecha', $fechaSel->toDateString()) }}" required>
                        @error('fecha') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field">
                        <label for="hora">Hora de inicio *</label>
                        <input id="hora" name="hora" type="time" class="tz-input" min="07:00" max="21:00" value="{{ old('hora', '09:00') }}" required>
                        @error('hora') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field is-wide">
                        <label for="ambiente">Aula *</label>
                        <select id="ambiente" name="ambiente" class="tz-select" required>
                            @foreach ($ambientes as $a)
                                <option value="{{ $a['codigo'] }}" @selected($ambienteSel === $a['codigo'])>{{ $a['codigo'] }} · {{ $a['nombre'] }} ({{ $a['capacidad'] }})</option>
                            @endforeach
                        </select>
                        @error('ambiente') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="tz-panel-body">
                    <h3 style="margin-bottom: 10px">Participantes</h3>
                    <ul class="tz-list" style="border: 1px solid var(--tz-border); border-radius: 10px">
                        @foreach ($participantes as $part)
                            <li style="padding: 10px 14px"><span>{{ $part['rol'] }} · {{ $part['nombre'] }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <div class="tz-panel-body tz-actions" style="border-top: 1px solid var(--tz-border)">
                    <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.secretaria.sustentaciones') }}">Cancelar</a>
                    <button class="tz-btn tz-btn-primary" type="submit" :disabled="enviando">Programar y notificar</button>
                </div>
            </form>

            <section class="tz-panel">
                <div class="tz-panel-head">
                    <h2>Agenda de la semana · {{ $ambienteSel }}</h2>
                    <form method="GET" class="tz-actions">
                        <label class="sr-only" for="ver-ambiente">Aula</label>
                        <select id="ver-ambiente" name="ambiente" class="tz-select" style="width: auto">
                            @foreach ($ambientes as $a) <option value="{{ $a['codigo'] }}" @selected($ambienteSel === $a['codigo'])>{{ $a['codigo'] }}</option> @endforeach
                        </select>
                        <label class="sr-only" for="ver-fecha">Semana de</label>
                        <input id="ver-fecha" type="date" name="fecha" class="tz-input" style="width: auto" value="{{ $fechaSel->toDateString() }}">
                        <button class="tz-btn tz-btn-secondary tz-btn-sm" type="submit">Ver</button>
                    </form>
                </div>
                <ul class="tz-list">
                    @foreach ($agenda as $d)
                        <li style="grid-template-columns: 110px 1fr; display: grid">
                            <strong>{{ ucfirst($d['dia']->translatedFormat('D d')) }}</strong>
                            <span>
                                @forelse ($d['eventos'] as $e)
                                    <span class="tz-status is-info">{{ $e['hora'] }} · {{ $e['codigo'] }}</span>
                                @empty
                                    <span class="tz-muted">Libre</span>
                                @endforelse
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    @endif
@endsection
