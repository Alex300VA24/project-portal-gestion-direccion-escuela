@extends('modules.bienestar.layout')

@section('title', 'Mis Citas · Bienestar UNT')
@section('page-label', 'Mis Citas')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Mis Citas de Acompañamiento</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Estudiante: <strong>{{ $actor->nombreCompleto }}</strong> · Cód. {{ $actor->codigoEstudiante }}
            </p>
        </div>
        <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="btn-unt-outline">
            Ver Mis Solicitudes
        </a>
    </div>

    <div class="card-unt overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--unt-borde)] text-sm">
            <thead class="text-xs uppercase tracking-wider font-bold" style="background-color: var(--unt-fondo-alt); border-bottom: 2px solid var(--unt-azul-tinta); color: var(--unt-azul-tinta);">
                <tr>
                    <th class="px-6 py-3 text-left">Área / Motivo</th>
                    <th class="px-6 py-3 text-left">Especialista / Atención</th>
                    <th class="px-6 py-3 text-center">Fecha y Hora</th>
                    <th class="px-6 py-3 text-center">Modalidad</th>
                    <th class="px-6 py-3 text-right">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--unt-borde)] bg-white">
                @forelse ($citas as $cita)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-[var(--unt-texto)] block">{{ $cita->caso?->tipoCaso?->nombre ?? 'Atención Bienestar' }}</span>
                            <span class="text-xs font-mono" style="color: var(--unt-texto-suave);">Caso #{{ $cita->id_caso }}</span>
                        </td>
                        <td class="px-6 py-4 text-xs text-[var(--unt-texto)]">
                            {{ $cita->especialista?->usuario?->name ?? 'Personal de Bienestar' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-xs font-mono" style="color: var(--unt-texto);">
                            <div>{{ $cita->inicio?->format('d/m/Y') }}</div>
                            <div class="text-[11px]" style="color: var(--unt-texto-suave);">{{ $cita->inicio?->format('H:i') }} - {{ $cita->fin?->format('H:i') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if ($cita->modalidad === 'Virtual')
                                <span class="px-2 py-0.5 rounded text-xs font-semibold" style="background-color: var(--unt-azul-suave); color: var(--unt-azul-tinta); border: 1px solid var(--unt-azul-borde);">
                                    Virtual
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs font-semibold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">
                                    Presencial
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            @if ($cita->estado === 'Programada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">Programada</span>
                            @elseif ($cita->estado === 'Realizada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-unt-azul text-white border border-unt-azul">Realizada</span>
                            @elseif ($cita->estado === 'Cancelada')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-800 border border-red-200">Cancelada</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">{{ $cita->estado }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-xs" style="color: var(--unt-texto-suave);">
                            No tiene citas de consejería o acompañamiento programadas actualmente.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $citas->links() }}
    </div>
</div>
@endsection
