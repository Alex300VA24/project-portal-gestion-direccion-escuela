{{-- Pantalla final de la encuesta pública. Recibe: tipo (exito, info o aviso), titulo, mensaje. --}}
@extends('modules.seguimiento-egresado.empresas.publico')
@section('title', $titulo)
@push('styles')
<style>
  .sg-estado { display: grid; justify-items: center; gap: var(--sg-space-3); width: 100%; max-width: 520px; margin-inline: auto; padding: var(--sg-space-8) var(--sg-space-6); border: var(--sg-stroke); border-radius: var(--sg-radius); background: var(--sg-surface); text-align: center; }
  .sg-estado-icono { display: grid; place-items: center; width: 56px; height: 56px; border-radius: 50%; }
  .sg-estado-icono svg { width: 28px; height: 28px; }
  .sg-estado-exito .sg-estado-icono { background: var(--sg-success-soft); color: var(--sg-success); }
  .sg-estado-info .sg-estado-icono { background: var(--sg-soft); color: var(--sg-accent); }
  .sg-estado-aviso .sg-estado-icono { background: var(--sg-warning-soft); color: var(--sg-warning); }
  .sg-pub-contenido .sg-estado h1 { margin: 0; font-size: 24px; }
  .sg-estado p { margin: 0; color: var(--sg-muted); }
</style>
@endpush
@section('content')
<div class="sg-estado sg-estado-{{ $tipo }}" role="{{ $tipo === 'aviso' ? 'alert' : 'status' }}">
  <span class="sg-estado-icono" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      @if ($tipo === 'aviso')
        <path d="M12 8v5"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/>
      @else
        <path d="m5 12.5 4.5 4.5L19 7.5"/>
      @endif
    </svg>
  </span>
  <h1>{{ $titulo }}</h1>
  <p>{{ $mensaje }}</p>
</div>
@endsection
