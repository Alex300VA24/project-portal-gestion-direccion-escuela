{{-- Campo de texto con etiqueta y error asociado (aria-invalid / aria-describedby).
     Variables: $id, $nombre, $etiqueta, $valor, $tipo (text), $requerido (false), $attrs [atributo => valor],
                $contenedorId, $oculto (false). Los errores se buscan por el nombre en notación de puntos. --}}
@php
  $clave = str_replace(['[', ']'], ['.', ''], $nombre);
  $mensaje = $errors->first($clave);
  $requerido = $requerido ?? false;
@endphp
<div class="sg-field" @isset($contenedorId) id="{{ $contenedorId }}" @endisset @if ($oculto ?? false) hidden @endif>
  <label class="sg-label {{ $requerido ? 'sgs-req' : '' }}" for="{{ $id }}">{{ $etiqueta }}</label>
  <input class="sg-input" id="{{ $id }}" type="{{ $tipo ?? 'text' }}" name="{{ $nombre }}" value="{{ ($tipo ?? 'text') === 'password' ? '' : ($valor ?? '') }}"
         @if ($requerido) aria-required="true" @endif
         @if ($mensaje) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
         @foreach (($attrs ?? []) as $atributo => $contenido) {{ $atributo }}="{{ $contenido }}" @endforeach>
  <p class="sg-field-error" id="{{ $id }}-error" @if ($mensaje) role="alert" @endif>{{ $mensaje }}</p>
</div>
