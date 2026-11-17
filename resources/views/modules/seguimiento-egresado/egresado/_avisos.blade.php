@foreach (['exito' => 'sg-alert', 'info' => 'sg-alert', 'error' => 'sg-alert sg-alert-danger'] as $clave => $clase)
  @if (session($clave))
    <div class="{{ $clase }}" role="status">{{ session($clave) }}</div>
  @endif
@endforeach
