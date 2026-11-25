@extends('modules.bienestar.layout')

@section('title', 'Mis Solicitudes · Bienestar UNT')
@section('page-label', 'Mis Solicitudes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Mis Solicitudes y Trámites UNT</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Estudiante: <strong>{{ $actor->nombreCompleto }}</strong> · Cód. {{ $actor->codigoEstudiante }}
            </p>
        </div>
        <button type="button" class="btn-unt-primary" data-abrir-modal="m-solicitud">Nueva solicitud</button>
    </div>


    <div class="card-unt overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--unt-borde)] text-sm">
            <thead class="text-xs uppercase tracking-wider font-bold" style="background-color: var(--unt-fondo-alt); border-bottom: 2px solid var(--unt-azul-tinta); color: var(--unt-azul-tinta);">
                <tr>
                    <th class="px-6 py-3 text-left">N° Trámite</th>
                    <th class="px-6 py-3 text-left">Procedimiento / Trámite UNT</th>
                    <th class="px-6 py-3 text-left">Motivo Explicado</th>
                    <th class="px-6 py-3 text-center">Fecha de Envío</th>
                    <th class="px-6 py-3 text-center">Estado del Trámite</th>
                    <th class="px-6 py-3 text-right">Acción / Resolución</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--unt-borde)] bg-white">
                @forelse ($solicitudes as $sol)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-[var(--unt-texto)]">
                            #{{ $sol->id_solicitud }}
                        </td>
                        <td class="px-6 py-4 text-xs font-bold text-[var(--unt-texto)]">
                            {{ $sol->tipoSolicitud?->nombre ?? $sol->beneficio?->nombre ?? 'General (migrado)' }}
                        </td>
                        <td class="px-6 py-4 text-xs max-w-xs truncate" style="color: var(--unt-texto-suave);" title="{{ $sol->motivo }}">
                            {{ $sol->motivo }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-xs font-mono" style="color: var(--unt-texto-suave);">
                            {{ $sol->solicitada_en?->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="etiqueta etiqueta-{{ \App\Modules\Bienestar\Support\EstadoSolicitud::tono($sol->estado) }}">{{ \App\Modules\Bienestar\Support\EstadoSolicitud::texto($sol->estado) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                            <div class="acciones">
                                @if ($sol->estado === 'observada')<button type="button" class="btn-unt-secondary" data-abrir-modal="m-subsanar-{{ $sol->id_solicitud }}">Corregir</button>@endif
                                <a href="{{ route('bienestar.alumno.solicitudes.show', $sol) }}" class="btn-unt-outline">Ver seguimiento</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No has presentado solicitudes de apoyo socioeconómico aún.
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
@include('modules.bienestar.alumno.solicitudes._nueva')
@foreach ($solicitudes->where('estado', 'observada') as $solicitud)
    @include('modules.bienestar.alumno.solicitudes._subsanar')
@endforeach
@endsection
