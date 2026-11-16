@php
    $colores = [
        'borrador' => 'bg-gray-100 text-gray-700',
        'registrada' => 'bg-gray-100 text-gray-700',
        'postulado' => 'bg-blue-100 text-blue-800',
        'en_revision' => 'bg-amber-100 text-amber-800',
        'pendiente' => 'bg-amber-100 text-amber-800',
        'observada' => 'bg-red-100 text-red-800',
        'observado' => 'bg-red-100 text-red-800',
        'no_seleccionado' => 'bg-red-100 text-red-800',
        'publicada' => 'bg-emerald-100 text-emerald-800',
        'aprobado' => 'bg-emerald-100 text-emerald-800',
        'aprobada' => 'bg-emerald-100 text-emerald-800',
        'seleccionado' => 'bg-emerald-100 text-emerald-800',
        'en_curso' => 'bg-blue-100 text-blue-800',
        'finalizada' => 'bg-slate-200 text-slate-800',
        'cerrada' => 'bg-slate-200 text-slate-800',
        'activo' => 'bg-emerald-100 text-emerald-800',
        'inactivo' => 'bg-gray-200 text-gray-700',
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colores[mb_strtolower($estado)] ?? 'bg-gray-100 text-gray-700' }}">
    {{ str_replace('_', ' ', $estado) }}
</span>
