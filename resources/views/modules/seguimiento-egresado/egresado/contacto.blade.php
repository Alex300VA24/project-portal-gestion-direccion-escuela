@extends('modules.seguimiento-egresado.egresado.layout')
@section('title', 'Mi contacto')

@section('content')
@php $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—'; @endphp
<div>
  <span class="sg-kicker">Mi expediente</span>
  <h1 class="sg-heading">Mis datos de contacto</h1>
  <p class="sgs-intro">Revisa que tus datos estén al día. Los campos con * son obligatorios.</p>
  @if (empty($contacto))
    <div class="sg-alert sg-alert-warning" role="note">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
      <p><strong>Este paso es obligatorio:</strong> si no completas tus datos de contacto, no podrás egresar.</p>
    </div>
  @endif

  @include('modules.seguimiento-egresado.egresado._resumen-errores')

  <form method="post" action="{{ route('seguimiento-egresado.egresado.contacto.guardar') }}" class="sg-panel">
    @csrf
    <div class="sg-form-grid">
      @include('modules.seguimiento-egresado.egresado._campo', [
        'id' => 'correo_personal', 'nombre' => 'correo_personal', 'etiqueta' => 'Correo personal', 'tipo' => 'email', 'requerido' => true,
        'valor' => old('correo_personal', $contacto['correo_personal'] ?? ''),
        'attrs' => ['required' => 'required', 'maxlength' => 254, 'autocomplete' => 'email', 'inputmode' => 'email', 'placeholder' => 'ejemplo@correo.com'],
      ])
      @include('modules.seguimiento-egresado.egresado._campo', [
        'id' => 'celular_whatsapp', 'nombre' => 'celular_whatsapp', 'etiqueta' => 'Celular / WhatsApp', 'tipo' => 'tel', 'requerido' => true,
        'valor' => old('celular_whatsapp', $contacto['celular_whatsapp'] ?? ''),
        'attrs' => ['required' => 'required', 'maxlength' => 20, 'pattern' => '\+?[0-9 ]{9,20}', 'title' => 'De 9 a 15 dígitos, con + opcional al inicio. Ejemplo: +51 900 000 000', 'autocomplete' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+51 900 000 000'],
      ])
      @include('modules.seguimiento-egresado.egresado._campo', [
        'id' => 'telefono_fijo', 'nombre' => 'telefono_fijo', 'etiqueta' => 'Teléfono fijo (opcional)', 'tipo' => 'tel',
        'valor' => old('telefono_fijo', $contacto['telefono_fijo'] ?? ''),
        'attrs' => ['maxlength' => 20, 'pattern' => '[0-9 ()+\-]{6,20}', 'title' => 'De 6 a 15 dígitos. Ejemplo: 044 000000', 'autocomplete' => 'off', 'inputmode' => 'tel', 'placeholder' => '044 000000'],
      ])
      @include('modules.seguimiento-egresado.egresado._campo', [
        'id' => 'direccion', 'nombre' => 'direccion', 'etiqueta' => 'Dirección (opcional)',
        'valor' => old('direccion', $contacto['direccion'] ?? ''),
        'attrs' => ['minlength' => 5, 'maxlength' => 250, 'autocomplete' => 'street-address', 'placeholder' => 'Av. / Calle y número'],
      ])
    </div>
    <div class="sgs-navbtns">
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.egresado.inicio') }}">Cancelar</a>
      <button class="sg-button" type="submit">Guardar mis datos</button>
    </div>
  </form>

  @if (count($versiones) > 1)
    <section class="sg-panel" aria-labelledby="t-versiones">
      <h2 class="sg-section-title" id="t-versiones">Datos anteriores</h2>
      <div class="sg-table-wrap">
        <table class="sg-table sgs-wide">
          <thead><tr><th scope="col">Correo</th><th scope="col">Celular</th><th scope="col">Vigente desde</th><th scope="col">Hasta</th></tr></thead>
          <tbody>
            @foreach ($versiones as $v)
              <tr><td>{{ $v['correo_personal'] }}</td><td>{{ $v['celular_whatsapp'] }}</td><td>{{ $fecha($v['vigente_desde']) }}</td><td>{{ $v['vigente_hasta'] ? $fecha($v['vigente_hasta']) : 'Vigente' }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif
</div>
@endsection
