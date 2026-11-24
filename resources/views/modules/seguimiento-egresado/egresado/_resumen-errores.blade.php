@if ($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" tabindex="-1" id="resumen-errores">
    <div>
      <strong>{{ $titulo ?? 'Revisa los datos marcados:' }}</strong>
      <ul>@foreach (array_unique($errors->all()) as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul>
    </div>
  </div>
@endif
