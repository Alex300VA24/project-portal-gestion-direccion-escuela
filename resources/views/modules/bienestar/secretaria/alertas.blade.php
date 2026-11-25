@extends('modules.bienestar.layout')

@section('title', 'Bandeja de Alertas · Secretaría Bienestar')
@section('page-label', 'Alertas en Revisión')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Bandeja de Filtrado de Alertas</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Secretaría: Evaluación administrativa inicial. Las alertas válidas se remiten a Bienestar y las inconsistentes se descartan con justificación en bitácora.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">
                Ley N° 29733: Datos confidenciales protegidos
            </span>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="card-unt p-4">
        <form method="GET" action="{{ route('bienestar.secretaria.alertas') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--unt-texto-suave);">Estado</label>
                <select name="estado" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs" onchange="this.form.submit()">
                    <option value="en_revision" @selected(($filtros['estado'] ?? 'en_revision') === 'en_revision')>En Revisión (Pendientes)</option>
                    <option value="remitida_bienestar" @selected(($filtros['estado'] ?? '') === 'remitida_bienestar')>Remitidas a Bienestar</option>
                    <option value="descartada" @selected(($filtros['estado'] ?? '') === 'descartada')>Descartadas</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--unt-texto-suave);">Prioridad</label>
                <select name="prioridad" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <option value="alta" @selected(($filtros['prioridad'] ?? '') === 'alta')>Alta / Urgente</option>
                    <option value="media" @selected(($filtros['prioridad'] ?? '') === 'media')>Media</option>
                    <option value="baja" @selected(($filtros['prioridad'] ?? '') === 'baja')>Baja</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--unt-texto-suave);">Origen</label>
                <select name="origen" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs" onchange="this.form.submit()">
                    <option value="">Todos los orígenes</option>
                    <option value="automatica" @selected(($filtros['origen'] ?? '') === 'automatica')>Detección Automática</option>
                    <option value="reporte_docente" @selected(($filtros['origen'] ?? '') === 'reporte_docente')>Reporte Docente</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--unt-texto-suave);">Buscar Estudiante</label>
                <div class="flex gap-2">
                    <input type="text" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código o apellidos..." class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                    <button type="submit" class="btn-unt-primary">Buscar</button>
                </div>
            </div>
        </form>
    </div>


    <!-- Tabla con datos mínimos requeridos (Ley 29733) -->
    <div class="card-unt overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--unt-borde)] text-sm">
            <thead class="text-xs uppercase tracking-wider font-bold" style="background-color: var(--unt-fondo-alt); border-bottom: 2px solid var(--unt-azul-tinta); color: var(--unt-azul-tinta);">
                <tr>
                    <th class="px-6 py-3 text-left">Estudiante (Adm.)</th>
                    <th class="px-6 py-3 text-left">Tipo / Regla</th>
                    <th class="px-6 py-3 text-left">Categoría del motivo</th>
                    <th class="px-6 py-3 text-center">Urgencia</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-right">Acción de Secretaría</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--unt-borde)] bg-white">
                @forelse ($alertas as $alerta)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-[var(--unt-texto)]">
                                {{ $alerta->estudiante?->persona?->nombres }} {{ $alerta->estudiante?->persona?->apellido_paterno }}
                            </div>
                            <div class="text-xs font-mono" style="color: var(--unt-texto-suave);">
                                Cód. {{ $alerta->estudiante?->codigo_universitario }} · {{ $ciclos[$alerta->id_estudiante] ?? '' }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if ($alerta->origen === 'automatica')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">
                                    Automática
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">
                                    Docente
                                </span>
                            @endif
                            <div class="text-xs font-medium text-[var(--unt-texto)] mt-1">
                                {{ ucwords(str_replace('_', ' ', $alerta->regla)) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs" style="color: var(--unt-texto-suave);">
                            {{ $alerta->categoriaTexto() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if (in_array(strtolower($alerta->prioridad), ['alta', 'urgente']))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-800 border border-red-200">Alta</span>
                            @elseif (strtolower($alerta->prioridad) === 'media')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">Media</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">Baja</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium 
                                {{ $alerta->estado === 'en_revision' ? 'bg-unt-papel text-unt-dorado-texto border border-unt-dorado' : '' }}
                                {{ $alerta->estado === 'remitida_bienestar' ? 'bg-unt-azul text-white border border-unt-azul' : '' }}
                                {{ $alerta->estado === 'descartada' ? 'bg-slate-100 text-slate-700 border border-slate-200' : '' }}">
                                {{ ucwords(str_replace('_', ' ', $alerta->estado)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                            @if ($alerta->estado === 'en_revision')
                                @php $quien = trim($alerta->estudiante?->persona?->nombres.' '.$alerta->estudiante?->persona?->apellido_paterno); @endphp
                                <div class="acciones">
                                    @if ($alerta->noPuedeDescartarse())
                                        <span class="etiqueta etiqueta-dorado" title="Las alertas automáticas de riesgo alto no se pueden descartar">Pase obligatorio</span>
                                    @else
                                        <button type="button" class="btn-unt-outline" data-abrir-modal="m-descartar" data-accion="{{ route('bienestar.secretaria.alertas.filtrar', $alerta) }}" data-campos="{{ json_encode(['detalle' => 'Alerta #'.$alerta->id_alerta.' de '.$quien]) }}">Descartar</button>
                                    @endif
                                    <button type="button" class="btn-unt-primary" data-abrir-modal="m-pasar" data-accion="{{ route('bienestar.secretaria.alertas.filtrar', $alerta) }}" data-campos="{{ json_encode(['detalle' => 'Alerta #'.$alerta->id_alerta.' de '.$quien]) }}">Pasar a Bienestar</button>
                                </div>
                            @else
                                <span class="text-unt-texto-suave italic">Procesada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No se encontraron alertas en la bandeja con los filtros seleccionados.
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
<x-bienestar::modal-unt id="m-pasar" titulo="Pasar a Bienestar" detalle="Alerta seleccionada" boton="Pasar a Bienestar">
    <input type="hidden" name="accion" value="pasar_bienestar">
    <label class="campo">Nota para la bitácora<textarea name="motivo" rows="3" minlength="5" maxlength="1000">{{ old('accion') === 'pasar_bienestar' ? old('motivo') : '' }}</textarea><small>Opcional.</small></label>
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-descartar" titulo="Descartar alerta" detalle="Alerta seleccionada" boton="Descartar" tono="peligro">
    <input type="hidden" name="accion" value="descartar">
    <label class="campo">Motivo *<textarea name="motivo" rows="4" required minlength="5" maxlength="1000">{{ old('accion') === 'descartar' ? old('motivo') : '' }}</textarea><small>Queda en la bitácora. El docente no lo verá.</small></label>
</x-bienestar::modal-unt>
@endsection
