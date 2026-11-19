@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.jurado.sustentaciones') }}">Sustentaciones</a> › {{ $proyecto->codigo }}</p>
            <h1>Resultado de la sustentación</h1>
            <p>{{ $proyecto->titulo }} · {{ collect($integrantes)->pluck('nombre')->map(fn ($n) => 'Br. '.$n)->implode(' y ') }}</p>
        </div>
    </div>

    <div class="tz-grid">
        <article class="tz-card"><span class="tz-eyebrow">Fecha</span><strong>{{ ucfirst($sustentacion->fecha_hora->translatedFormat('D d M Y')) }}</strong></article>
        <article class="tz-card"><span class="tz-eyebrow">Hora de inicio</span><strong>{{ $sustentacion->fecha_hora->format('H:i') }}</strong></article>
        <article class="tz-card"><span class="tz-eyebrow">Aula</span><strong>{{ $lugar }} · presencial</strong></article>
        <article class="tz-card"><span class="tz-eyebrow">Intento</span><strong>{{ $sustentacion->numero_intento }}.ª sustentación</strong></article>
    </div>

    <div class="tz-grid-2">
        @if ($sustentacion->estado === 'Programada' && $mio === null)
            @if ($sustentacion->fecha_hora->startOfDay()->gt(now()))
                <section class="tz-panel">@include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Aún no es el día de la sustentación', 'texto' => 'Podrás registrar tu resultado desde el día programado.'])</section>
            @else
                <form method="POST" action="{{ route('tesis-proyecto-tesis.jurado.resultado.registrar', $sustentacion) }}" class="tz-panel" x-data="{ r: @js(old('resultado', '')), enviando: false }" @submit="setTimeout(() => enviando = true)">
                    @csrf
                    <div class="tz-panel-head"><h2>Tu resultado como {{ $miCargo }}</h2></div>
                    <div class="tz-panel-body tz-form">
                        <fieldset style="border: 0; margin: 0; padding: 0; display: grid; gap: 10px">
                            <legend class="sr-only">Resultado</legend>
                            <label class="tz-check"><input type="radio" name="resultado" value="Aprobatorio" x-model="r"> <span><strong>Aprobatorio.</strong> Los sustentantes demostraron dominio del tema.</span></label>
                            <label class="tz-check"><input type="radio" name="resultado" value="Desaprobatorio" x-model="r"> <span><strong>Desaprobatorio.</strong> Se deberá programar una nueva sustentación.</span></label>
                        </fieldset>
                        @error('resultado') <span class="tz-error">{{ $message }}</span> @enderror
                        <label class="tz-check" x-show="r === 'Aprobatorio'" x-cloak><input type="checkbox" name="mencion" value="1"> Propongo mención honrosa (solo se otorga si el resultado es por unanimidad y los tres la proponen).</label>
                        <div class="tz-field" style="max-width: 220px">
                            <label for="hora_fin">Hora de término</label>
                            <input id="hora_fin" name="hora_fin" type="time" class="tz-input" value="{{ old('hora_fin') }}">
                        </div>
                        <div class="tz-actions"><button class="tz-btn tz-btn-primary" type="submit" :disabled="enviando || r === ''">Registrar mi resultado</button></div>
                    </div>
                </form>
            @endif
        @else
            <section class="tz-panel tz-panel-body" style="display: grid; gap: 10px">
                <h2>Tu resultado</h2>
                @if ($mio)
                    <span class="tz-status is-{{ $mio->resultado === 'Aprobatorio' ? 'success' : 'danger' }}">{{ $mio->resultado }}{{ $mio->propone_mencion ? ' · propusiste mención honrosa' : '' }}</span>
                    <span class="tz-muted tz-small">Registrado el {{ $mio->registrado_en->format('d/m/Y H:i') }}</span>
                @endif
                @if ($sustentacion->resultado)
                    <div class="tz-alert is-{{ $sustentacion->resultado === 'Aprobado' ? 'success' : 'danger' }}">
                        <strong>{{ $sustentacion->resultado === 'Aprobado' ? 'Aprobado por '.($sustentacion->modo_resultado === 'Unanimidad' ? 'unanimidad' : 'mayoría') : 'Desaprobado' }}</strong>
                        {{ $sustentacion->mencion_honrosa ? '· con mención honrosa' : '' }} ({{ $sustentacion->total_aprobatorios }} aprobatorios)
                    </div>
                @endif
            </section>
        @endif

        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Registro del jurado</h2></div>
            <ul class="tz-list">
                @foreach ($registro as $r)
                    <li style="display: flex; justify-content: space-between; align-items: center">
                        <span>{{ $r['nombre'] }} · {{ $r['cargo'] }}</span>
                        @if ($r['resultado'])
                            <span class="tz-status is-{{ $r['resultado'] === 'Aprobatorio' ? 'success' : 'danger' }}">{{ $r['resultado'] }}</span>
                        @else
                            <span class="tz-status is-{{ $r['registrado'] ? 'info' : 'neutral' }}">{{ $r['registrado'] ? 'Registrado' : 'Por registrar' }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="tz-panel-body tz-small tz-muted">
                Los resultados individuales se muestran cuando los tres hayan registrado. 3 aprobatorios: aprobado por unanimidad · 2: por mayoría · 1 o 0: desaprobado (se programa una nueva fecha). El acta no lleva nota.
            </div>
        </section>
    </div>
@endsection
