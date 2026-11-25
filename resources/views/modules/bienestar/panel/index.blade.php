@extends('modules.bienestar.layout')

@section('title', 'Panel de alertas · Bienestar')
@section('page-label', 'Panel de alertas')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Panel de alertas</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">Gestión especializada de alertas remitidas por Secretaría, atención de casos y derivaciones.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn-unt-secondary" data-abrir-modal="m-alerta">Nueva alerta</button>
            <a href="{{ route('bienestar.panel.solicitudes.index') }}" class="btn-unt-primary">
                Dictaminar Solicitudes Remitidas
            </a>
        </div>
    </div>

    <!-- Pestañas de Navegación Operativa -->
    <div class="flex border-b border-[var(--unt-borde)] gap-4 text-xs font-bold">
        <a href="{{ route('bienestar.panel.index', ['tab' => 'activas']) }}" class="pb-3 border-b-2 {{ ($tab ?? 'activas') === 'activas' ? 'border-[color:var(--unt-azul-tinta)] text-[color:var(--unt-azul-tinta)] font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Alertas Remitidas por Secretaría ({{ $totales['remitidas'] ?? 0 }})
        </a>
        <a href="{{ route('bienestar.panel.index', ['tab' => 'descartadas']) }}" class="pb-3 border-b-2 {{ ($tab ?? '') === 'descartadas' ? 'border-red-700 text-red-700 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
            Alertas Descartadas / Auditoría ({{ $totales['descartadas'] ?? 0 }})
        </a>
    </div>


    <!-- Métricas resumidas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card-unt p-5">
            <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--unt-texto-suave);">Alertas en Atención</span>
            <p class="text-3xl font-extrabold mt-2" style="color: var(--unt-azul-tinta);">{{ $totales['remitidas'] ?? 0 }}</p>
        </div>
        <div class="card-unt p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-red-700">Alta Prioridad</span>
            <p class="text-3xl font-extrabold text-red-700 mt-2">{{ $totales['alta_prioridad'] ?? 0 }}</p>
        </div>
        <div class="card-unt p-5">
            <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--unt-texto-suave);">Alertas Descartadas</span>
            <p class="text-3xl font-extrabold mt-2" style="color: var(--unt-texto-suave);">{{ $totales['descartadas'] ?? 0 }}</p>
        </div>
        <div class="card-unt p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-unt-dorado-accessible">Casos Abiertos</span>
            <p class="text-3xl font-extrabold mt-2 text-unt-dorado-accessible">{{ $totales['casos_abiertos'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="card-unt p-4">
        <form action="{{ route('bienestar.panel.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <input type="hidden" name="tab" value="{{ $tab ?? 'activas' }}">
            <div>
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto-suave);">Origen</label>
                <select name="origen" class="w-full rounded-lg border-[var(--unt-borde)] text-xs focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)]" onchange="this.form.submit()">
                    <option value="">Todos los orígenes</option>
                    <option value="automatica" @selected(($filtros['origen'] ?? '') === 'automatica')>Detección Automática</option>
                    <option value="reporte_docente" @selected(($filtros['origen'] ?? '') === 'reporte_docente')>Reporte Docente</option>
                    <option value="manual_bienestar" @selected(($filtros['origen'] ?? '') === 'manual_bienestar')>Registro Manual Bienestar</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto-suave);">Prioridad</label>
                <select name="prioridad" class="w-full rounded-lg border-[var(--unt-borde)] text-xs focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)]" onchange="this.form.submit()">
                    <option value="">Todas las prioridades</option>
                    <option value="alta" @selected(($filtros['prioridad'] ?? '') === 'alta')>Alta / Urgente</option>
                    <option value="media" @selected(($filtros['prioridad'] ?? '') === 'media')>Media</option>
                    <option value="baja" @selected(($filtros['prioridad'] ?? '') === 'baja')>Baja</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto-suave);">Buscar Estudiante</label>
                <div class="flex gap-2">
                    <input type="text" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código o apellidos..." class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                    <button type="submit" class="btn-unt-primary">Buscar</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de alertas -->
    <div class="card-unt overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--unt-borde)] text-sm">
            <thead class="text-xs uppercase tracking-wider font-bold" style="background-color: var(--unt-fondo-alt); border-bottom: 2px solid var(--unt-azul-tinta); color: var(--unt-azul-tinta);">
                <tr>
                    <th class="px-6 py-3 text-left">Estudiante</th>
                    <th class="px-6 py-3 text-left">Origen & Regla</th>
                    <th class="px-6 py-3 text-left">Curso / Motivo</th>
                    <th class="px-6 py-3 text-center">Prioridad</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--unt-borde)] bg-white">
                @forelse ($alertas as $alt)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-[var(--unt-texto)]">
                                {{ $alt->estudiante?->persona?->nombres }} {{ $alt->estudiante?->persona?->apellido_paterno }}
                            </div>
                            <div class="text-xs font-mono" style="color: var(--unt-texto-suave);">
                                Cód. {{ $alt->estudiante?->codigo_universitario }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-2">
                                @if ($alt->origen === 'automatica')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">
                                        {{ $alt->origen }}
                                    </span>
                                @elseif ($alt->origen === 'manual_bienestar')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">
                                        {{ $alt->origen }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">
                                        {{ $alt->origen }}
                                    </span>
                                @endif
                                <span class="text-xs font-semibold text-[var(--unt-texto)]">
                                    {{ str_replace('_', ' ', ucfirst($alt->regla)) }}
                                </span>
                            </div>
                            @if (($tab ?? '') === 'descartadas')
                                @php
                                    $bitacoraDescarte = $alt->bitacoras->firstWhere('accion', 'descartado');
                                @endphp
                                @if ($bitacoraDescarte)
                                    <div class="p-2 bg-red-50 border border-red-200 rounded text-xs text-red-900 mt-2">
                                        <strong>Motivo de descarte (Secretaría):</strong> {{ $bitacoraDescarte->motivo }}
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs" style="color: var(--unt-texto);">
                            {{ $alt->curso ?: 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if (in_array(strtolower($alt->prioridad), ['alta', 'urgente']))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-800 border border-red-200">Alta</span>
                            @elseif (strtolower($alt->prioridad) === 'media')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">Media</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">Baja</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if (in_array($alt->estado, ['remitida_bienestar', 'en_atencion', 'en_revision']))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">
                                    {{ ucfirst(str_replace('_', ' ', $alt->estado)) }}
                                </span>
                            @elseif ($alt->estado === 'atendida')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-unt-azul text-white border border-unt-azul">
                                    {{ ucfirst(str_replace('_', ' ', $alt->estado)) }}
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ ucfirst(str_replace('_', ' ', $alt->estado)) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            @if (($tab ?? '') === 'descartadas')
                                <button type="button" class="btn-unt-secondary" data-abrir-modal="m-reabrir" data-accion="{{ route('bienestar.panel.alertas.reabrir', $alt) }}" data-campos="{{ json_encode(['detalle' => 'Alerta #'.$alt->id_alerta.' · vuelve a las alertas activas.']) }}">Reabrir</button>
                            @else
                                <a href="{{ route('bienestar.panel.atender', $alt) }}" class="btn-unt-primary">
                                    {{ $alt->puedeAtenderse() && ! $alt->id_caso ? 'Atender' : 'Ver' }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No se encontraron alertas en esta sección.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $alertas->links() }}
    </div>
</div>
<x-bienestar::modal-unt id="m-reabrir" titulo="Reabrir alerta" detalle="Vuelve a las alertas activas." boton="Reabrir" tono="secondary">
    <label class="campo">Justificación *<textarea name="motivo" rows="4" required minlength="5" maxlength="1000">{{ old('motivo') }}</textarea></label>
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-alerta" titulo="Nueva alerta manual" detalle="Registro directo de Bienestar." :accion="route('bienestar.panel.alertas.store-manual')" boton="Registrar" ancho="42rem">
    @include('modules.bienestar.panel._alerta_campos')
</x-bienestar::modal-unt>
@endsection
