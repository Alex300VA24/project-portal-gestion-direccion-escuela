@extends('modules.bienestar.layout')

@section('title', 'Mis Solicitudes · Bienestar UNT')
@section('page-label', 'Mis Solicitudes')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mis Solicitudes de Beneficios</h1>
            <p class="text-xs text-slate-600 mt-1">
                Estudiante: <strong>{{ $actor->nombreCompleto }}</strong> · Cód. {{ $actor->codigoEstudiante }}
            </p>
        </div>
        <a href="{{ route('bienestar.alumno.solicitudes.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-900 hover:bg-indigo-800 text-white text-xs font-bold rounded-lg shadow-xs transition">
            + Nueva Solicitud de Apoyo
        </a>
    </div>
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Beneficio Solicitado</th>
                        <th class="px-6 py-3 text-left">Motivo / Sustento Explicado</th>
                        <th class="px-6 py-3 text-center">Fecha de Envío</th>
                        <th class="px-6 py-3 text-center">Estado</th>
                        <th class="px-6 py-3 text-right">Fecha de Resolución</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($solicitudes as $sol)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-900">
                                {{ $sol->beneficio?->nombre }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 max-w-md">
                                {{ $sol->motivo }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-xs text-slate-500 font-mono">
                                {{ $sol->solicitada_en?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($sol->estado === 'Pendiente')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">En Revisión</span>
                                @elseif ($sol->estado === 'Aprobada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aprobada</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">No Aprobada</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-slate-500">
                                {{ $sol->resuelta_en ? $sol->resuelta_en->format('d/m/Y') : 'Pendiente' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                No has presentado solicitudes de apoyo socioeconómico aún.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $solicitudes->links() }}
        </div>
</div>
@endsection
