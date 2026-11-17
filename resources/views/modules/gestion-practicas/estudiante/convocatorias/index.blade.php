@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Convocatorias de prácticas</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($convocatorias as $convocatoria)
                    <article class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 flex flex-col">
                        <p class="text-sm text-[color:var(--portal-muted)]">{{ $empresas[$convocatoria->id_empresa] ?? '—' }}</p>
                        <h3 class="text-lg font-bold text-[color:var(--portal-ink)] mt-1">{{ $convocatoria->titulo }}</h3>
                        <p class="text-sm text-[color:var(--portal-muted)] mt-2 line-clamp-3">{{ $convocatoria->descripcion }}</p>
                        <p class="text-xs text-[color:var(--portal-muted)] mt-3">
                            {{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }} ·
                            {{ $convocatoria->vacantes }} vacante(s) · cierra el {{ $convocatoria->fecha_cierre->format('d/m/Y') }}
                        </p>
                        <div class="mt-4 flex items-center gap-3">
                            <a href="{{ route('gestion-practicas.estudiante.convocatorias.show', $convocatoria) }}" class="px-4 py-2 bg-[color:var(--portal-green)] text-white rounded-[10px] text-sm font-bold hover:bg-[color:var(--portal-green-dark)]">Ver detalles</a>
                            @if (in_array((int) $convocatoria->id_convocatoria, $yaPostulo, true))
                                <span class="text-xs font-bold text-[color:var(--portal-green-dark)]">Ya postuló</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="md:col-span-2 bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 text-sm text-[color:var(--portal-muted)]">No hay convocatorias vigentes por ahora.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
