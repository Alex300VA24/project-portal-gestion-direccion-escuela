@php
    use App\Modules\GestionPracticas\Models\Convocatoria;
    use App\Modules\GestionPracticas\Support\Catalogos;

    $editable = in_array($convocatoria->estado, [Convocatoria::BORRADOR, Convocatoria::OBSERVADA], true);
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">{{ $convocatoria->titulo }}</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <div class="flex items-center justify-between">
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])
                    <span class="text-sm text-[color:var(--portal-muted)]">{{ $postulantes }} postulante(s)</span>
                </div>

                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-[color:var(--portal-muted)]">Tipo</dt><dd>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Modalidad</dt><dd>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Vacantes</dt><dd>{{ $convocatoria->vacantes }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Cierre</dt><dd>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Descripción</dt><dd class="whitespace-pre-line">{{ $convocatoria->descripcion }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Requisitos</dt><dd class="whitespace-pre-line">{{ $convocatoria->requisitos }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Enlace de postulación</dt><dd><a class="text-[color:var(--portal-green-dark)] hover:underline break-all" href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener">{{ $convocatoria->enlace_postulacion }}</a></dd></div>
                </dl>

                <div class="flex flex-wrap gap-3 pt-2">
                    @if ($editable)
                        <a href="{{ route('gestion-practicas.empresa.convocatorias.edit', $convocatoria) }}" class="px-4 py-2 border border-[#bbc2ce] rounded-[10px] text-sm font-bold text-[color:var(--portal-navy)] hover:bg-[color:var(--portal-surface-alt)]">Corregir</a>
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.enviar', $convocatoria) }}">@csrf<x-primary-button>Enviar a revisión</x-primary-button></form>
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.anular', $convocatoria) }}">@csrf<button class="px-4 py-2 text-sm text-[color:var(--portal-danger)] hover:underline">Anular</button></form>
                    @endif
                    @if ($convocatoria->estado === Convocatoria::PUBLICADA)
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.cerrar', $convocatoria) }}">@csrf<button class="px-4 py-2 border border-[#bbc2ce] rounded-[10px] text-sm font-bold text-[color:var(--portal-navy)] hover:bg-[color:var(--portal-surface-alt)]">Cerrar convocatoria</button></form>
                    @endif
                </div>
            </div>

            @if ($observaciones->isNotEmpty())
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                    <h3 class="font-bold text-[color:var(--portal-ink)] mb-3">Observaciones del administrador</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach ($observaciones as $observacion)
                            <li class="border-s-4 {{ $observacion->estado === 'pendiente' ? 'border-[color:var(--portal-danger)]' : 'border-[color:var(--portal-green)]' }} ps-3">
                                <p>{{ $observacion->descripcion }}</p>
                                <p class="text-xs text-[color:var(--portal-muted)] mt-1">{{ $observacion->registrado_en->format('d/m/Y H:i') }} · @include('modules.gestion-practicas.partials.estado', ['estado' => $observacion->estado])</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
