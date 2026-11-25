{{--
    Campo de formulario de DESIGN.md §4: etiqueta de 14 px en peso 700 sobre el campo, 8 px de separación,
    borde #bbc2ce, radio 10 px, foco verde con halo y aria-invalid con el mensaje de error debajo.

    Parámetros: $name, $label; opcionales: $tipo (text|email|url|date|number|password|file|textarea|select),
    $valor, $requerido (true), $extra (atributos HTML en un arreglo), $span (ocupa las dos columnas),
    $opciones (valor => texto, para select), $vacio (opción inicial de un select), $ayuda, $filas.
--}}
@php
    $tipo = $tipo ?? 'text';
    $requerido = $requerido ?? true;
    $extra = $extra ?? [];
    $actual = $tipo === 'file' || $tipo === 'password' ? null : old($name, $valor ?? null);
    $atributos = new \Illuminate\View\ComponentAttributeBag($extra);
@endphp
<div @class(['gp-field', 'gp-span' => $span ?? false])>
    <label for="{{ $name }}">{{ $label }}@if (! $requerido && ! str_contains(mb_strtolower($label), 'opcional')) <small>(opcional)</small>@endif</label>
    @if ($tipo === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $filas ?? 4 }}" class="gp-control" @required($requerido) @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $atributos }}>{{ $actual }}</textarea>
    @elseif ($tipo === 'select')
        <select id="{{ $name }}" name="{{ $name }}" class="gp-control" @required($requerido) @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $atributos }}>
            @isset($vacio)<option value="">{{ $vacio }}</option>@endisset
            @foreach (($opciones ?? []) as $clave => $texto)
                <option value="{{ $clave }}" @selected((string) $actual === (string) $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $tipo }}" @if ($actual !== null && $tipo !== 'file') value="{{ $actual }}" @endif class="gp-control" @required($requerido) @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $atributos }}>
    @endif
    @isset($ayuda)<small>{{ $ayuda }}</small>@endisset
    @error($name)<p class="gp-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
</div>
