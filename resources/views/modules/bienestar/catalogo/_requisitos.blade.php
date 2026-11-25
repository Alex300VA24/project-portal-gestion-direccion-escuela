<section class="card-unt p-6 space-y-4">
    <div class="encabezado encabezado-chico">
        <div><h2>Requisitos</h2><p>El estudiante tendrá un espacio de carga por cada requisito activo.</p></div>
        <button type="button" class="btn-unt-primary" data-abrir-modal="m-requisito" data-accion="{{ route('bienestar.catalogo-solicitudes.requisitos.store', $tipo) }}"
            data-campos="{{ json_encode(['titulo' => 'Nuevo requisito', '_method' => 'POST', 'formatos' => ['pdf', 'jpg', 'png'], 'tamano' => 5, 'obligatorio' => true]) }}">Agregar requisito</button>
    </div>
    @forelse ($tipo->requisitos as $r)
        <div class="fila">
            <div>
                <b>{{ $r->nombre }}</b>
                <small>{{ $r->es_obligatorio ? 'Obligatorio' : 'Opcional' }} · {{ strtoupper($r->formatos_permitidos) }} · máx. {{ $r->tamano_maximo_mb }} MB{{ $r->activo ? '' : ' · inactivo' }}</small>
                <p>{{ $r->descripcion }}</p>
                @if ($r->id_archivo_plantilla)<a class="enlace" href="{{ route('bienestar.requisitos.plantilla', $r) }}">Descargar plantilla</a>@endif
            </div>
            <div class="acciones">
                <form method="POST" action="{{ route('bienestar.catalogo-solicitudes.requisitos.toggle', $r) }}">@csrf @method('PATCH')<button class="btn-unt-outline">{{ $r->activo ? 'Desactivar' : 'Activar' }}</button></form>
                <button type="button" class="btn-unt-outline" data-abrir-modal="m-requisito" data-accion="{{ route('bienestar.catalogo-solicitudes.requisitos.update', $r) }}"
                    data-campos="{{ json_encode(['titulo' => 'Editar requisito', '_method' => 'PUT', 'nombre' => $r->nombre, 'descripcion' => $r->descripcion, 'formatos' => explode(',', str_replace([' ', 'jpeg'], ['', 'jpg'], $r->formatos_permitidos)), 'tamano' => $r->tamano_maximo_mb, 'obligatorio' => $r->es_obligatorio]) }}">Editar</button>
            </div>
        </div>
    @empty
        <p class="vacio">Este trámite aún no tiene requisitos.</p>
    @endforelse
</section>
<x-bienestar::modal-unt id="m-requisito" titulo="Requisito" boton="Guardar requisito">
    <input type="hidden" name="_method" value="{{ old('_method') === 'PUT' ? 'PUT' : 'POST' }}" data-campo="_method">
    <label class="campo">Nombre *<input name="nombre" data-campo="nombre" value="{{ old('_modal') === 'm-requisito' ? old('nombre') : '' }}" required maxlength="255"></label>
    <label class="campo">Qué debe subir el estudiante *<textarea name="descripcion" data-campo="descripcion" rows="3" required maxlength="1000">{{ old('_modal') === 'm-requisito' ? old('descripcion') : '' }}</textarea></label>
    <fieldset class="campo"><legend>Formatos permitidos *</legend><div class="opciones">
        @foreach (['pdf', 'jpg', 'png'] as $f)<label><input type="checkbox" name="formatos[]" value="{{ $f }}" data-campo="formatos" @checked(in_array($f, (array) old('formatos', [])))> {{ strtoupper($f) }}</label>@endforeach
    </div></fieldset>
    <div class="campos-2">
        <label class="campo">Tamaño máximo (MB) *<input type="number" name="tamano_maximo_mb" data-campo="tamano" min="1" max="50" value="{{ old('tamano_maximo_mb', 5) }}" required></label>
        <label class="casilla"><input type="checkbox" name="es_obligatorio" value="1" data-campo="obligatorio" @checked(old('es_obligatorio'))><span><b>Obligatorio</b></span></label>
    </div>
    <label class="campo">Plantilla descargable<input type="file" name="plantilla" accept=".pdf,.doc,.docx,.xls,.xlsx"><small>PDF, Word o Excel, hasta 10 MB. Si no eliges archivo se conserva la actual.</small></label>
    <label class="casilla"><input type="checkbox" name="quitar_plantilla" value="1"><span>Quitar la plantilla actual</span></label>
</x-bienestar::modal-unt>
