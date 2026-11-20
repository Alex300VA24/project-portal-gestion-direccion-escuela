{{-- Encabezado común de los modales. Recibe: id, titulo; opcional: detalle. --}}
<div class="sg-modal-cabecera">
  <div>
    <h2 id="{{ $id }}-titulo">{{ $titulo }}</h2>
    @isset($detalle)<p>{{ $detalle }}</p>@endisset
  </div>
  <button class="sg-button sg-button-secondary" type="button" data-sg-cerrar-modal>Cerrar</button>
</div>
