{{-- Lista desplegable con etiqueta y error asociado.
     Variables: $id, $nombre, $etiqueta, $opciones [valor => texto], $seleccion, $requerido (false), $atributos (texto), $contenedorId, $oculto --}}
@php
  $clave = str_replace(['[', ']'], ['.', ''], $nombre);
  $mensaje = $errors->first($clave);
  $requerido = $requerido ?? false;
@endphp
<div class="sg-field" @isset($contenedorId) id="{{ $contenedorId }}" @endisset @if ($oculto ?? false) hidden @endif>
  <label class="sg-label {{ $requerido ? 'sgs-req' : '' }}" for="{{ $id }}">{{ $etiqueta }}</label>
  <select class="sg-select" id="{{ $id }}" name="{{ $nombre }}"
          @if ($requerido) aria-required="true" @endif
          @if ($mensaje) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
          {!! $atributos ?? '' !!}>
    <option value="">Seleccione…</option>
    @foreach ($opciones as $valor => $texto)
      <option value="{{ $valor }}" @selected((string) ($seleccion ?? '') === (string) $valor)>{{ $texto }}</option>
    @endforeach
  </select>
  <p class="sg-field-error" id="{{ $id }}-error" @if ($mensaje) role="alert" @endif>{{ $mensaje }}</p>
</div>
