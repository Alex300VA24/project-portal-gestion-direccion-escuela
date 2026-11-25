@extends('modules.bienestar.layout')

@section('title', 'Dictamen de Solicitudes Remitidas · Bienestar UNT')
@section('page-label', 'Dictamen de Solicitudes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Dictamen de Solicitudes Remitidas</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Bienestar evalúa y dictamina únicamente las solicitudes que han sido verificadas y remitidas por la Secretaría con foliado externo confirmado.
            </p>
        </div>
        <div>
            <a href="{{ route('bienestar.catalogo-solicitudes.index') }}" class="btn-unt-primary">
                Administrar Catálogo UNT
            </a>
        </div>
    </div>


    <!-- Filtros -->
    <div class="card-unt p-4">
        <form action="{{ route('bienestar.panel.solicitudes.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto-suave);">Estado</label>
                <select name="estado" class="w-full rounded-lg border-[var(--unt-borde)] text-xs focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)]" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="remitida" @selected(($filtros['estado'] ?? '') === 'remitida')>Remitidas por Secretaría (Pendientes de Dictamen)</option>
                    <option value="aprobada" @selected(($filtros['estado'] ?? '') === 'aprobada')>Aprobadas</option>
                    <option value="rechazada" @selected(($filtros['estado'] ?? '') === 'rechazada')>Rechazadas</option>
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
                    <th class="px-6 py-3 text-left">Trámite / Motivo</th>
                    <th class="px-6 py-3 text-center">Folio Externo</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-right">Dictamen de Bienestar</th>
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
                                Cód. {{ $sol->estudiante?->codigo_universitario }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-xs text-[var(--unt-texto)]">
                                {{ $sol->tipoSolicitud?->nombre ?? $sol->beneficio?->nombre ?? 'General (migrado)' }}
                            </div>
                            <div class="text-xs truncate max-w-sm mt-0.5" style="color: var(--unt-texto-suave);" title="{{ $sol->motivo }}">
                                {{ $sol->motivo }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-xs">
                            @if ($sol->folio_externo_confirmado)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-unt-azul text-white border border-unt-azul">✓ Confirmado</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">No foliado</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                {{ $sol->estado === 'remitida' ? 'bg-[color:var(--unt-azul-suave)] text-[color:var(--unt-azul-tinta)] border border-[color:var(--unt-azul-borde)]' : '' }}
                                {{ in_array(strtolower($sol->estado), ['aprobada', 'aprobado']) ? 'bg-unt-azul text-white border border-unt-azul' : '' }}
                                {{ in_array(strtolower($sol->estado), ['rechazada', 'rechazado']) ? 'bg-red-50 text-red-800 border border-red-200' : '' }}
                                {{ in_array($sol->estado, ['enviada', 'recibida', 'observada']) ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' }}">
                                {{ ucfirst($sol->estado) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                            <div class="acciones">
                                @if ($sol->puedeDictaminarse())
                                    <button type="button" class="btn-unt-peligro" data-abrir-modal="m-rechazar" data-accion="{{ route('bienestar.panel.solicitudes.evaluar', $sol) }}">Rechazar</button>
                                    <button type="button" class="btn-unt-primary" data-abrir-modal="m-aprobar" data-accion="{{ route('bienestar.panel.solicitudes.evaluar', $sol) }}">Aprobar</button>
                                @endif
                                <a href="{{ route('bienestar.panel.solicitudes.show', $sol) }}" class="btn-unt-outline">Expediente</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No se encontraron solicitudes registradas en esta vista.
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
@include('modules.bienestar.panel.solicitudes._modales')
@endsection
