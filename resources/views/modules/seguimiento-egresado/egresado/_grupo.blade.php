{{-- Grupo de opciones (radio o casilla) con leyenda y error asociado.
     Variables: $nombre, $etiqueta, $opciones [valor => texto], $seleccion, $tipo (radio|checkbox), $clase (sgs-opts), $requerido (false) --}}
@php
  $tipo = $tipo ?? 'radio';
  $seleccion = array_map('strval', (array) ($seleccion ?? []));
  $campo = $tipo === 'checkbox' ? $nombre.'[]' : $nombre;
  $mensaje = $errors->first($nombre) ?: $errors->first($nombre.'.*');
  $idError = 'err-'.str_replace(['[', ']'], '', $nombre);
@endphp
<fieldset class="sg-field {{ $mensaje ? 'sgs-invalid' : '' }}" @if ($mensaje) aria-invalid="true" aria-describedby="{{ $idError }}" @endif>
  <legend class="sg-label {{ ($requerido ?? false) ? 'sgs-req' : '' }}">{{ $etiqueta }}</legend>
  <div class="{{ $clase ?? 'sgs-opts' }}">
    @foreach ($opciones as $valor => $texto)
      <label class="sgs-opt"><input type="{{ $tipo }}" name="{{ $campo }}" value="{{ $valor }}" @checked(in_array((string) $valor, $seleccion, true))><span>{{ $texto }}</span></label>
    @endforeach
  </div>
  <p class="sg-field-error" id="{{ $idError }}" @if ($mensaje) role="alert" @endif>{{ $mensaje }}</p>
</fieldset>
