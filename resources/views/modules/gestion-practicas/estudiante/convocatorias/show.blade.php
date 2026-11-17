@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">{{ $convocatoria->titulo }}</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <p class="font-medium text-[color:var(--portal-ink)]">{{ $empresa }}</p>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-[color:var(--portal-muted)]">Tipo</dt><dd>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Modalidad</dt><dd>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Vacantes</dt><dd>{{ $convocatoria->vacantes }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Cierre de postulación</dt><dd>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Descripción</dt><dd class="whitespace-pre-line">{{ $convocatoria->descripcion }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Requisitos</dt><dd class="whitespace-pre-line">{{ $convocatoria->requisitos }}</dd></div>
                </dl>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <a href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener" class="px-4 py-2 border border-[#bbc2ce] rounded-[10px] text-sm font-bold text-[color:var(--portal-navy)] hover:bg-[color:var(--portal-surface-alt)]">Acceder al enlace de postulación</a>

                    @if (! $vigente)
                        <span class="text-sm text-[color:var(--portal-muted)]">La convocatoria ya no está vigente.</span>
                    @elseif ($yaPostulo)
                        <span class="text-sm font-bold text-[color:var(--portal-green-dark)]">Ya registró su postulación.</span>
                    @else
                        <form method="POST" action="{{ route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria) }}">
                            @csrf
                            <x-primary-button>Registrar mi postulación</x-primary-button>
                        </form>
                    @endif
                </div>
                <p class="text-xs text-[color:var(--portal-muted)]">Postule primero en el enlace de la empresa y luego registre aquí su postulación para llevar el control del proceso.</p>
            </div>
        </div>
    </div>
</x-app-layout>
