@php
    $mapa = [
        'VALIDA' => ['is-success', 'Válida'],
        'PENDIENTE_RESULTADOS' => ['is-warning', 'Pendiente de resultados'],
        'REQUIERE_AJUSTE' => ['is-danger', 'Requiere ajuste'],
        'ENVIADA' => ['is-success', 'Registrada'],
        'BORRADOR' => ['is-neutral', 'Borrador'],
        'CORRECCION' => ['is-warning', 'En corrección'],
        'ORDINARIA' => ['is-success', 'Ordinaria'],
        'EXTRAORDINARIA' => ['is-warning', 'Extraordinaria'],
        'APROBADO' => ['is-success', 'Aprobado'],
        'DESAPROBADO' => ['is-danger', 'Desaprobado'],
        'EN_CURSO' => ['is-warning', 'En curso'],
        'RETIRADO' => ['is-neutral', 'Retirado'],
        'crear' => ['is-success', 'Nuevo'],
        'actualizar' => ['is-warning', 'Actualiza'],
        'sin_cambios' => ['is-neutral', 'Sin cambios'],
    ];
    [$clase, $texto] = $mapa[$valor ?? ''] ?? ['is-neutral', $valor ? Str::of($valor)->replace('_', ' ')->lower()->ucfirst() : 'Sin validar'];
@endphp
<span class="quality-status {{ $clase }}">{{ $texto }}</span>
