@extends('modules.bienestar.layout')
@section('title', 'Alerta #'.$alerta->id_alerta.' · Bienestar')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.panel.index'), 'texto' => 'Panel de alertas'])@endsection
@section('page-label', 'Alerta #'.$alerta->id_alerta)
@section('content')
<div class="space-y-6">
    <div class="encabezado">
        <div>
            <h1>{{ $alerta->estudiante?->persona?->nombres }} {{ $alerta->estudiante?->persona?->apellido_paterno }} {{ $alerta->estudiante?->persona?->apellido_materno }}</h1>
            <p>Código {{ $alerta->estudiante?->codigo_universitario }} · periodo {{ $alerta->periodo }}</p>
        </div>
        @if ($alerta->puedeAtenderse() && ! $alerta->caso)
            <div class="acciones">
                <button type="button" class="btn-unt-outline" data-abrir-modal="m-descartar">Descartar</button>
                <button type="button" class="btn-unt-secondary" data-abrir-modal="m-agendar">Agendar cita</button>
                <button type="button" class="btn-unt-primary" data-abrir-modal="m-abrir">Abrir caso</button>
            </div>
        @endif
    </div>

    <section class="card-unt p-6">
        <dl class="datos">
            <div><dt>Origen</dt><dd>{{ ['automatica' => 'Detección automática', 'reporte_docente' => 'Reporte docente'][$alerta->origen] ?? 'Registro de Bienestar' }}</dd></div>
            <div><dt>Regla</dt><dd>{{ ucfirst(str_replace('_', ' ', $alerta->regla)) }}</dd></div>
            <div><dt>Curso</dt><dd>{{ $alerta->curso }}</dd></div>
            <div><dt>Prioridad</dt><dd><span class="etiqueta {{ in_array($alerta->prioridad, ['alta', 'urgente']) ? 'etiqueta-rojo' : 'etiqueta-dorado' }}">{{ ucfirst($alerta->prioridad) }}</span></dd></div>
            <div><dt>Estado</dt><dd>{{ ucfirst(str_replace('_', ' ', $alerta->estado)) }}</dd></div>
            <div><dt>Detectada</dt><dd>{{ $alerta->fecha_deteccion?->setTimezone('America/Lima')->format('d/m/Y H:i') }}</dd></div>
            @if ($alerta->docente)<div><dt>Docente</dt><dd>{{ $alerta->docente->persona?->nombres }} {{ $alerta->docente->persona?->apellido_paterno }}</dd></div>@endif
            <div><dt>Categoría</dt><dd>{{ $alerta->categoriaTexto() }}</dd></div>
            @foreach (collect($alerta->detalles)->filter(fn ($v) => is_scalar($v)) as $clave => $valor)
                <div><dt>{{ ucfirst(str_replace('_', ' ', $clave)) }}</dt><dd>{{ is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor }}</dd></div>
            @endforeach
        </dl>
        @if ($alerta->motivo_reporte)<p class="mt-5 text-sm"><b>Motivo:</b> {{ $alerta->motivo_reporte }}</p>@endif
        @if ($alerta->descripcion_reporte)<div class="card-unt-editorial p-4 mt-4 text-sm">{{ $alerta->descripcion_reporte }}</div>@endif
    </section>

    @if ($alerta->caso)
        @include('modules.bienestar.panel._caso', ['caso' => $alerta->caso])
    @endif

    <section class="card-unt p-6">
        <h3 class="subtitulo">Bitácora</h3>
        <ol class="historial">
            @forelse ($alerta->bitacoras as $b)
                <li><b>{{ ucfirst(str_replace('_', ' ', $b->accion)) }}</b><small>{{ $b->creado_en?->setTimezone('America/Lima')->format('d/m/Y H:i') }} · {{ $b->usuario?->correo_institucional }}</small><p>{{ $b->motivo }}</p></li>
            @empty
                <li><p>Sin decisiones registradas.</p></li>
            @endforelse
        </ol>
    </section>
</div>

@if ($alerta->puedeAtenderse() && ! $alerta->caso)
    @php $url = route('bienestar.panel.guardar-atencion', $alerta); @endphp
    <x-bienestar::modal-unt id="m-abrir" titulo="Abrir caso" detalle="Inicia el acompañamiento formal del estudiante." :accion="$url" boton="Abrir caso">
        <input type="hidden" name="accion" value="abrir_caso">
        <label class="campo">Tipo de caso *<select name="id_tipo_caso" required>@foreach ($tiposCaso as $tc)<option value="{{ $tc->id_tipo_caso }}" @selected(old('id_tipo_caso') == $tc->id_tipo_caso)>{{ $tc->nombre }}</option>@endforeach</select></label>
        <label class="campo">Descripción inicial<textarea name="descripcion_caso" rows="4">{{ old('descripcion_caso') }}</textarea></label>
    </x-bienestar::modal-unt>
    <x-bienestar::modal-unt id="m-agendar" titulo="Agendar cita" detalle="Abre un caso de riesgo académico y programa una hora de atención." :accion="$url" boton="Agendar" tono="secondary">
        <input type="hidden" name="accion" value="programar_cita">
        <label class="campo">Fecha y hora *<input type="datetime-local" name="inicio_cita" value="{{ old('inicio_cita') }}" min="{{ now('America/Lima')->format('Y-m-d\TH:i') }}" required></label>
        <label class="campo">Modalidad *<select name="modalidad"><option>Presencial</option><option @selected(old('modalidad') === 'Virtual')>Virtual</option></select></label>
    </x-bienestar::modal-unt>
    <x-bienestar::modal-unt id="m-descartar" titulo="Descartar alerta" detalle="El motivo queda en la bitácora y podrás reabrirla." :accion="$url" boton="Descartar" tono="peligro">
        <input type="hidden" name="accion" value="descartar">
        <label class="campo">Motivo *<textarea name="motivo_descarte" rows="4" required minlength="5">{{ old('motivo_descarte') }}</textarea></label>
    </x-bienestar::modal-unt>
@endif
@endsection
