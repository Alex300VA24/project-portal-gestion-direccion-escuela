@extends('modules.bienestar.layout')

@section('title', 'Mis Reportes · Bienestar UNT')
@section('page-label', 'Mis Reportes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Mis Reportes de Riesgo Estudiantil</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Docente: <strong>{{ $actor->nombreCompleto }}</strong> ({{ $actor->codigoDocente ?? 'DOC-001' }}) · Seguimiento preventivo.
            </p>
        </div>
        <button type="button" class="btn-unt-secondary" data-abrir-modal="m-reporte">Reportar estudiante</button>
    </div>

    <div class="card-unt overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--unt-borde)] text-sm">
            <thead class="text-xs uppercase tracking-wider font-bold" style="background-color: var(--unt-fondo-alt); border-bottom: 2px solid var(--unt-azul-tinta); color: var(--unt-azul-tinta);">
                <tr>
                    <th class="px-6 py-3 text-left">Estudiante</th>
                    <th class="px-6 py-3 text-left">Curso</th>
                    <th class="px-6 py-3 text-left">Motivo del Reporte</th>
                    <th class="px-6 py-3 text-center">Prioridad</th>
                    <th class="px-6 py-3 text-center">Estado en Bienestar</th>
                    <th class="px-6 py-3 text-right">Fecha de Envío</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--unt-borde)] bg-white">
                @forelse ($reportes as $rep)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-bold text-[var(--unt-texto)]">
                                {{ $rep->estudiante?->persona?->nombres }} {{ $rep->estudiante?->persona?->apellido_paterno }}
                            </div>
                            <div class="text-xs font-mono" style="color: var(--unt-texto-suave);">
                                Cód. {{ $rep->estudiante?->codigo_universitario }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-[var(--unt-texto)]">
                            {{ $rep->curso }}
                        </td>
                        <td class="px-6 py-4 text-xs text-[var(--unt-texto)] max-w-xs truncate" title="{{ $rep->motivo_reporte }}">
                            {{ $rep->motivo_reporte }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if ($rep->prioridad === 'alta')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-800 border border-red-200">Alta</span>
                            @elseif ($rep->prioridad === 'media')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">Media</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">Baja</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if (in_array($rep->estado, ['en_revision', 'pendiente']))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">En revisión por Secretaría</span>
                            @elseif (in_array($rep->estado, ['remitida_bienestar', 'en_atencion']))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">En atención en Bienestar</span>
                            @elseif ($rep->estado === 'atendida')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-unt-azul text-white border border-unt-azul">Atendida</span>
                            @elseif ($rep->estado === 'descartada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200" title="Alerta descartada">Descartado</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">{{ ucfirst(str_replace('_', ' ', $rep->estado)) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs" style="color: var(--unt-texto-suave);">
                            {{ $rep->created_at?->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No has emitido reportes de riesgo estudiantil todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $reportes->links() }}
    </div>
</div>
@include('modules.bienestar.profesor._reportar')
@endsection
