@php use App\Modules\GestionPracticas\Models\Postulacion; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Mis postulaciones</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[color:var(--portal-border)] text-sm">
                        <thead class="text-left text-[color:var(--portal-muted)]">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Convocatoria</th>
                                <th class="py-2 pe-4 font-medium">Empresa</th>
                                <th class="py-2 pe-4 font-medium">Postulada</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2">¿Fue seleccionado?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[color:var(--portal-border)]">
                            @forelse ($postulaciones as $postulacion)
                                @php($convocatoria = $convocatorias[$postulacion->id_convocatoria] ?? null)
                                <tr>
                                    <td class="py-2 pe-4">{{ $convocatoria?->titulo ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $empresas[$convocatoria?->id_empresa] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $postulacion->postulado_en->format('d/m/Y') }}</td>
                                    <td class="py-2 pe-4">@include('modules.gestion-practicas.partials.estado', ['estado' => $postulacion->estado])</td>
                                    <td class="py-2">
                                        @if ($postulacion->estado === Postulacion::POSTULADO)
                                            <form method="POST" action="{{ route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion) }}" class="flex flex-wrap gap-3">
                                                @csrf
                                                <button name="resultado" value="seleccionado" class="text-[color:var(--portal-green-dark)] hover:underline">Sí, fui seleccionado</button>
                                                <button name="resultado" value="no_seleccionado" class="text-[color:var(--portal-danger)] hover:underline">No fui seleccionado</button>
                                                <button name="resultado" value="desistido" class="text-[color:var(--portal-muted)] hover:underline">Desistí</button>
                                            </form>
                                        @elseif ($postulacion->estado === Postulacion::SELECCIONADO && $postulacion->id_practica === null)
                                            <a class="text-[color:var(--portal-green-dark)] font-bold hover:underline" href="{{ route('gestion-practicas.estudiante.practicas.create', $postulacion) }}">Registrar mi práctica</a>
                                        @elseif ($postulacion->id_practica !== null)
                                            <a class="text-[color:var(--portal-green-dark)] hover:underline" href="{{ route('gestion-practicas.estudiante.practicas.show', $postulacion->id_practica) }}">Ver mi práctica</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-[color:var(--portal-muted)]">Aún no tiene postulaciones. Revise las <a class="text-[color:var(--portal-green-dark)] hover:underline" href="{{ route('gestion-practicas.estudiante.convocatorias.index') }}">convocatorias vigentes</a>.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
