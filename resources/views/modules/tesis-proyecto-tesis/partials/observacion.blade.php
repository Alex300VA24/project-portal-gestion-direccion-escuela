{{-- Tarjeta de observación: requiere $o (de AvanceService::observaciones) --}}
@php($tzVar = ['Pendiente' => 'warning', 'Respondida' => 'info', 'Levantada' => 'success'][$o->estado] ?? 'neutral')
<div class="tz-tags">
    <span class="tz-status is-{{ $tzVar }}">{{ $o->estado }}</span>
    <span class="tz-status is-neutral">{{ $o->rol_revisor }}{{ $o->referencial ? ' · referencial' : '' }}</span>
    @if ($o->criterio)
        <span class="tz-status is-danger">✗ Rúbrica · criterio {{ $o->criterio->orden }}</span>
    @endif
</div>
<span class="tz-muted tz-small">{{ $o->revisor }} · {{ $o->etapa_entrega }} v{{ $o->version_entrega }}{{ $o->semana_entrega ? ' (semana '.$o->semana_entrega.')' : '' }} · {{ \Illuminate\Support\Carbon::parse($o->revisado_en)->format('d/m/Y') }}</span>
@if ($o->seccion_documento)
    <strong>{{ $o->seccion_documento }}</strong>
@endif
@if ($o->criterio)
    <span class="tz-small tz-muted">Criterio: «{{ $o->criterio->descripcion }}»</span>
@endif
<p>{{ $o->descripcion }}</p>
@if ($o->respuesta_estudiante && $o->estado !== 'Pendiente')
    <div class="tz-small" style="background: var(--tz-alt); border-radius: 10px; padding: 10px 12px">
        <strong>Respuesta del estudiante:</strong> {{ $o->respuesta_estudiante }}
        @include('modules.tesis-proyecto-tesis.partials.correccion')
    </div>
@endif
