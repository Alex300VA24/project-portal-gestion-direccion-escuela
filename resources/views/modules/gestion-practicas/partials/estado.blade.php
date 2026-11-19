{{-- Etiqueta de estado del diseño del portal (docs/DESIGN.md §4): .quality-status con is-success | is-warning | is-danger | is-neutral. --}}
@php
    $tonos = [
        'publicada' => 'is-success', 'aprobada' => 'is-success', 'aprobado' => 'is-success', 'seleccionado' => 'is-success',
        'en_curso' => 'is-success', 'activo' => 'is-success', 'subsanada' => 'is-success',
        'en_revision' => 'is-warning', 'pendiente' => 'is-warning',
        'observada' => 'is-danger', 'observado' => 'is-danger', 'no_seleccionado' => 'is-danger', 'anulada' => 'is-danger',
    ];
    $tono = $tonos[mb_strtolower($estado)] ?? 'is-neutral';
@endphp
<span class="quality-status {{ $tono }}">{{ str_replace('_', ' ', $estado) }}</span>
