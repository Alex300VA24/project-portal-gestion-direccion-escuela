@extends('modules.bienestar.layout')

@section('title', 'Recepción de Solicitudes · Secretaría Bienestar')
@section('page-label', 'Recepción de Solicitudes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Recepción y Verificación de Solicitudes</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Secretaría: Verifique que la documentación requerida esté conforme. Al remitir a Bienestar debe confirmar bajo declaración que armó el folio físico/externo fuera del sistema.
            </p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card-unt p-4">
        <form method="GET" action="{{ route('bienestar.secretaria.solicitudes') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--unt-texto-suave);">Filtrar por Estado</label>
                <select name="estado" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="enviada" @selected(($filtros['estado'] ?? '') === 'enviada')>Enviada (Nueva sin revisar)</option>
                    <option value="recibida" @selected(($filtros['estado'] ?? '') === 'recibida')>Recibida (En verificación)</option>
                    <option value="observada" @selected(($filtros['estado'] ?? '') === 'observada')>Observada (En manos del alumno)</option>
                    <option value="remitida" @selected(($filtros['estado'] ?? '') === 'remitida')>Remitida a Bienestar</option>
                    <option value="aprobada" @selected(($filtros['estado'] ?? '') === 'aprobada')>Aprobada</option>
                    <option value="rechazada" @selected(($filtros['estado'] ?? '') === 'rechazada')>Rechazada</option>
                </select>
            </div>
        </form>
    </div>


    <div class="card-unt overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--unt-borde)] text-sm">
            <thead class="text-xs uppercase tracking-wider font-bold" style="background-color: var(--unt-fondo-alt); border-bottom: 2px solid var(--unt-azul-tinta); color: var(--unt-azul-tinta);">
                <tr>
                    <th class="px-6 py-3 text-left">N° Solicitud</th>
                    <th class="px-6 py-3 text-left">Estudiante</th>
                    <th class="px-6 py-3 text-left">Trámite UNT</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-center">Folio Externo</th>
                    <th class="px-6 py-3 text-right">Fecha de Envío</th>
                    <th class="px-6 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--unt-borde)] bg-white">
                @forelse ($solicitudes as $sol)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 font-mono font-bold text-[var(--unt-texto)]">
                            #{{ $sol->id_solicitud }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-[var(--unt-texto)]">
                                {{ $sol->estudiante?->persona?->nombres }} {{ $sol->estudiante?->persona?->apellido_paterno }}
                            </div>
                            <div class="text-xs font-mono" style="color: var(--unt-texto-suave);">
                                Cód. {{ $sol->estudiante?->codigo_universitario }} · {{ $ciclos[$sol->id_estudiante] ?? '' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-[var(--unt-texto)]">
                            {{ $sol->tipoSolicitud?->nombre ?? 'General (migrado)' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if ($sol->estado === 'enviada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">Enviada</span>
                            @elseif ($sol->estado === 'recibida')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">Recibida</span>
                            @elseif ($sol->estado === 'observada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-800 border border-red-200">Observada</span>
                            @elseif ($sol->estado === 'remitida')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">Remitida</span>
                            @elseif ($sol->estado === 'aprobada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-unt-azul text-white border border-unt-azul">Aprobada</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">{{ ucfirst($sol->estado) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-xs">
                            @if ($sol->folio_externo_confirmado)
                                <span class="text-unt-azul font-bold">✓ Confirmado</span>
                            @else
                                <span class="text-unt-texto-suave">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs" style="color: var(--unt-texto-suave);">
                            {{ $sol->solicitada_en?->setTimezone('America/Lima')->format('d/m/Y H:i') ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                            <a href="{{ route('bienestar.secretaria.solicitudes.revisar', $sol) }}" class="btn-unt-primary">
                                {{ $sol->puedeRevisarse() ? 'Revisar' : 'Ver' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No hay solicitudes registradas con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $solicitudes->links() }}
    </div>
</div>
@endsection
