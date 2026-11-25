@props(['id', 'titulo', 'detalle' => null, 'accion' => '', 'metodo' => 'POST', 'boton' => 'Guardar', 'tono' => 'primary', 'ancho' => '34rem'])
{{-- Ventana modal nativa: foco atrapado, Escape y fondo. Se abre con data-abrir-modal="id" --}}
<dialog id="{{ $id }}" class="modal-unt" style="max-width: {{ $ancho }}" aria-labelledby="{{ $id }}-titulo">
    <form method="POST" action="{{ $accion }}" enctype="multipart/form-data">
        @csrf
        @if ($metodo !== 'POST')
            @method($metodo)
        @endif
        <input type="hidden" name="_modal" value="{{ $id }}">
        <input type="hidden" name="_accion" value="">
        <header>
            <div>
                <h2 id="{{ $id }}-titulo" data-campo="titulo">{{ $titulo }}</h2>
                @if ($detalle)<p data-campo="detalle">{{ $detalle }}</p>@endif
            </div>
            <button type="button" class="modal-unt-cerrar" data-cerrar-modal aria-label="Cerrar ventana">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>
        <div class="modal-unt-cuerpo">
            @if ($errors->any() && old('_modal') === $id)
                <ul class="aviso aviso-error" role="alert">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            {{ $slot }}
        </div>
        <footer>
            <button type="button" class="btn-unt-outline" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="btn-unt-{{ $tono }}">{{ $boton }}</button>
        </footer>
    </form>
</dialog>
