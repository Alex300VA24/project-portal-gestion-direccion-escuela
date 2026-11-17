<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Mis convocatorias</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-[color:var(--portal-muted)]">Cree una convocatoria y envíela a revisión del administrador para que se publique.</p>
                    <a href="{{ route('gestion-practicas.empresa.convocatorias.create') }}" class="px-4 py-2 bg-[color:var(--portal-green)] text-white rounded-[10px] text-sm font-bold hover:bg-[color:var(--portal-green-dark)]">Nueva convocatoria</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[color:var(--portal-border)] text-sm">
                        <thead class="text-left text-[color:var(--portal-muted)]">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Título</th>
                                <th class="py-2 pe-4 font-medium">Cierre</th>
                                <th class="py-2 pe-4 font-medium">Vacantes</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[color:var(--portal-border)]">
                            @forelse ($convocatorias as $convocatoria)
                                <tr>
                                    <td class="py-2 pe-4">{{ $convocatoria->titulo }}</td>
                                    <td class="py-2 pe-4">{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</td>
                                    <td class="py-2 pe-4">{{ $convocatoria->vacantes }}</td>
                                    <td class="py-2 pe-4">
                                        @include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])
                                        @if ($convocatoria->observaciones_pendientes > 0)
                                            <span class="ms-1 text-xs text-[color:var(--portal-danger)]">{{ $convocatoria->observaciones_pendientes }} observación(es)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-end"><a class="text-[color:var(--portal-green-dark)] hover:underline" href="{{ route('gestion-practicas.empresa.convocatorias.show', $convocatoria) }}">Ver</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-[color:var(--portal-muted)]">Aún no ha creado convocatorias.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
