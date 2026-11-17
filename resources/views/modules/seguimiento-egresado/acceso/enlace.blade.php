@extends('modules.seguimiento-egresado.egresado.layout')
@section('title', $noValido ? 'Enlace no válido' : 'Entra con tu enlace')

@section('content')
<div>
  <span class="sg-badge">Acceso de egresados</span>
  @if ($noValido)
    <h1 class="sg-heading">Este enlace no es válido o ya venció</h1>
    <p class="sgs-intro">Puede que el enlace esté incompleto, haya sido anulado o haya pasado su fecha límite. Pide uno nuevo a la Secretaría de la Escuela.</p>
  @else
    <h1 class="sg-heading">Entra con tu enlace personal</h1>
    <p class="sgs-intro">Para ver tu expediente abre el enlace personal que la Escuela te envió en tu invitación. Si no lo encuentras, pídelo a la Secretaría.</p>
  @endif
  <div class="sg-alert" role="status">Tu enlace es personal: no lo compartas con otras personas.</div>
</div>
@endsection
