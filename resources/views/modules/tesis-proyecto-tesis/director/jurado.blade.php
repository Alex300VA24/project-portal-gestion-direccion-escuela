@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.director.panel'), 'a' => 'Panel general'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.director.panel') }}">Panel general</a> › Jurado</p>
            <h1>{{ $puedeAsignar ? 'Asignar jurado' : 'Jurado del proyecto' }}</h1>
            <p>El mismo jurado acompaña la etapa de proyecto y la de tesis. Solo se cambia por algún inconveniente.</p>
        </div>
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    @if ($conformidad)
        <div class="tz-tags">
            <span class="tz-status is-{{ $conformidad['rubricaConforme'] ? 'success' : 'neutral' }}">Rúbrica del asesor {{ $conformidad['rubricaConforme'] ? '✓' : 'pendiente' }}</span>
            @if ($reporte)
                <span class="tz-status is-success">Turnitin {{ $reporte->porcentaje }} %</span>
            @endif
        </div>
    @endif

    @if ($puedeAsignar)
        <form method="POST" action="{{ route('tesis-proyecto-tesis.director.jurado.asignar', $proyecto) }}" class="tz-panel"
              x-data="{ sel: { Presidente: @js(old('Presidente', '')), Secretario: @js(old('Secretario', '')), Vocal: @js(old('Vocal', '')) }, enviando: false }" @submit="setTimeout(() => enviando = true)">
            @csrf
            <div class="tz-panel-head"><h2>Cargos</h2><span class="tz-muted tz-small">El asesor no aparece en la lista: no puede ser jurado de su propio asesorado.</span></div>
            <div class="tz-panel-body tz-fields">
                @foreach (['Presidente', 'Secretario', 'Vocal'] as $cargo)
                    <div class="tz-field">
                        <label for="{{ $cargo }}">{{ $cargo }} del jurado *</label>
                        <select id="{{ $cargo }}" name="{{ $cargo }}" class="tz-select" x-model="sel.{{ $cargo }}" required>
                            <option value="">Selecciona</option>
                            @foreach ($candidatos as $c)
                                <option value="{{ $c['codigo'] }}" :disabled="Object.entries(sel).some(([k, v]) => k !== '{{ $cargo }}' && v === '{{ $c['codigo'] }}')">{{ $c['nombre'] }} · {{ $c['categoria'] ?? '—' }} · jurado en {{ $c['carga'] }}</option>
                            @endforeach
                        </select>
                        @error($cargo) <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            </div>
            <div class="tz-panel-body tz-actions" style="border-top: 1px solid var(--tz-border)">
                <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.director.panel') }}">Cancelar</a>
                <button class="tz-btn tz-btn-primary" type="submit" :disabled="enviando || !sel.Presidente || !sel.Secretario || !sel.Vocal">Asignar jurado y notificar</button>
            </div>
        </form>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Docentes disponibles</h2><span class="tz-muted tz-small">Ordenados por menor carga como jurado</span></div>
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Docente</th><th scope="col">Categoría</th><th scope="col">Especialista en la línea</th><th scope="col">Carga como jurado</th></tr></thead>
                <tbody>
                    @foreach ($candidatos as $c)
                        <tr><td>{{ $c['nombre'] }}</td><td>{{ $c['categoria'] ?? '—' }}</td><td>{{ $c['especialista'] ? 'Sí' : '—' }}</td><td>{{ $c['carga'] }} {{ $c['carga'] === 1 ? 'proyecto' : 'proyectos' }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>
    @elseif ($miembros !== [])
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Jurado vigente</h2></div>
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Cargo</th><th scope="col">Docente</th><th scope="col">Avance en la etapa actual</th></tr></thead>
                <tbody>
                    @foreach ($miembros as $m)
                        <tr><td>{{ $m['cargo'] }}</td><td>{{ $m['nombre'] }}</td><td><span class="tz-status is-{{ $m['estado'] === 'Conforme' ? 'success' : ($m['estado'] === 'Sin iniciar' ? 'neutral' : 'warning') }}">{{ $m['estado'] }}</span></td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>

        @if ($puedeReemplazar)
            <form method="POST" action="{{ route('tesis-proyecto-tesis.director.jurado.reemplazar', $proyecto) }}" class="tz-panel">
                @csrf
                <div class="tz-panel-head"><h2>Reemplazar a un miembro</h2><span class="tz-muted tz-small">Solo por inconveniente (licencia, renuncia u otro). Queda en el historial.</span></div>
                <div class="tz-panel-body tz-fields">
                    <div class="tz-field">
                        <label for="cargo">Cargo</label>
                        <select id="cargo" name="cargo" class="tz-select" required>
                            @foreach (['Presidente', 'Secretario', 'Vocal'] as $cargo) <option value="{{ $cargo }}" @selected(old('cargo') === $cargo)>{{ $cargo }}</option> @endforeach
                        </select>
                    </div>
                    <div class="tz-field">
                        <label for="docente">Nuevo docente</label>
                        <select id="docente" name="docente" class="tz-select" required>
                            <option value="">Selecciona</option>
                            @foreach ($candidatos as $c)
                                @unless (in_array($c['nombre'], array_column($miembros, 'nombre'), true))
                                    <option value="{{ $c['codigo'] }}">{{ $c['nombre'] }} · jurado en {{ $c['carga'] }}</option>
                                @endunless
                            @endforeach
                        </select>
                        @error('docente') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field is-wide">
                        <label for="motivo">Motivo *</label>
                        <input id="motivo" name="motivo" class="tz-input" maxlength="500" value="{{ old('motivo') }}" required>
                        @error('motivo') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="tz-panel-body tz-actions" style="border-top: 1px solid var(--tz-border)"><button class="tz-btn tz-btn-navy" type="submit">Reemplazar y notificar</button></div>
            </form>
        @endif

        @if ($historialJurado->isNotEmpty())
            <section class="tz-panel">
                <div class="tz-panel-head"><h2>Historial de cambios</h2></div>
                <ul class="tz-list">
                    @foreach ($historialJurado as $h)
                        <li><span><strong>{{ $h->cargo }}</strong> · hasta {{ $h->fecha_fin?->format('d/m/Y') }} · {{ $h->motivo_cambio }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif
    @else
        <div class="tz-alert is-info">El proyecto aún no tiene la conformidad del asesor ({{ \App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($proyecto->estado) }}).</div>
    @endif
@endsection
