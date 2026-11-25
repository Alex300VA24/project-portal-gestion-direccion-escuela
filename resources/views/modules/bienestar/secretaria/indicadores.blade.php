@extends('modules.bienestar.layout')

@section('title', 'Indicadores Operativos · Secretaría Bienestar')
@section('page-label', 'Indicadores Operativos')

@section('content')
<div class="space-y-6">
    <div class="pb-4 border-b border-[var(--unt-borde)]">
        <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Indicadores Operativos de Secretaría</h1>
        <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
            Métricas de carga y gestión operativa: monitoreo de alertas pendientes de revisión y solicitudes según etapa de trámite.
        </p>
    </div>

    <!-- Sección de Alertas -->
    <div class="space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider" style="color: var(--unt-azul-tinta);">Gestión de Alertas de Riesgo</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Alertas en Revisión (Pendientes)</div>
                <div class="text-3xl font-extrabold text-unt-dorado-accessible mt-2">{{ $indicadores['alertas_en_revision'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">Requieren decisión de Secretaría</p>
            </div>
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Alertas Remitidas a Bienestar</div>
                <div class="text-3xl font-extrabold mt-2" style="color: var(--unt-azul-tinta);">{{ $indicadores['alertas_pasadas_bienestar'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">En atención especializada</p>
            </div>
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Alertas Descartadas</div>
                <div class="text-3xl font-extrabold mt-2" style="color: var(--unt-texto-suave);">{{ $indicadores['alertas_descartadas'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">Justificadas en bitácora</p>
            </div>
        </div>
    </div>

    <!-- Sección de Solicitudes -->
    <div class="space-y-3 pt-4">
        <h2 class="text-xs font-bold uppercase tracking-wider" style="color: var(--unt-azul-tinta);">Gestión de Solicitudes de Estudiantes</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Enviadas (Sin Revisar)</div>
                <div class="text-3xl font-extrabold text-unt-dorado-accessible mt-2">{{ $indicadores['solicitudes_enviadas'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">Por abrir en Secretaría</p>
            </div>
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Recibidas / En Verificación</div>
                <div class="text-3xl font-extrabold mt-2" style="color: var(--unt-azul-tinta);">{{ $indicadores['solicitudes_recibidas'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">Verificando requisitos</p>
            </div>
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Observadas</div>
                <div class="text-3xl font-extrabold text-red-700 mt-2">{{ $indicadores['solicitudes_observadas'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">En subsanación por alumno</p>
            </div>
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">Remitidas a Bienestar</div>
                <div class="text-3xl font-extrabold mt-2" style="color: var(--unt-azul-tinta);">{{ $indicadores['solicitudes_remitidas'] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">Con folio externo confirmado</p>
            </div>
            @foreach (['aprobadas' => 'Aprobadas', 'rechazadas' => 'Rechazadas'] as $k => $t)
            <div class="card-unt p-5">
                <div class="text-xs font-medium" style="color: var(--unt-texto-suave);">{{ $t }}</div>
                <div class="text-3xl font-extrabold mt-2" style="color: var(--unt-azul-tinta);">{{ $indicadores['solicitudes_'.$k] }}</div>
                <p class="text-[11px] text-unt-texto-suave mt-1">Dictaminadas por Bienestar</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
