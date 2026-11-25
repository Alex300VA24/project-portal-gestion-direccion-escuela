@extends('modules.bienestar.layout')
@section('title', 'Catálogo de trámites · Bienestar')
@section('page-label', 'Catálogo de trámites')
@section('content')
<div class="space-y-6">
    <div class="encabezado">
        <div><h1>Catálogo de trámites UNT</h1><p>Tipos de solicitud, requisitos y plantillas. Pendiente de verificar con la Dirección de Bienestar de la UNT.</p></div>
        <button type="button" class="btn-unt-primary" data-abrir-modal="m-tramite">Nuevo trámite</button>
    </div>
    <div class="catalogo">
        @forelse ($tipos as $tipo)
            <article @class(['card-unt', 'inactivo' => ! $tipo->activo])>
                <div class="catalogo-etiquetas">
                    <span class="etiqueta {{ $tipo->estado_verificacion === 'verificado' ? 'etiqueta-azul' : 'etiqueta-dorado' }}">{{ $tipo->estado_verificacion === 'verificado' ? 'Verificado' : 'Pendiente de verificar' }}</span>
                    @unless ($tipo->activo)<span class="etiqueta etiqueta-gris">Inactivo</span>@endunless
                </div>
                <h2>{{ $tipo->nombre }}</h2>
                <p>{{ $tipo->unidad_responsable }}</p>
                <dl>
                    <div><dt>Plazo</dt><dd>{{ $tipo->plazo_dias_habiles }} {{ $tipo->plazo_dias_habiles === 1 ? 'día hábil' : 'días hábiles' }}</dd></div>
                    <div><dt>Requisitos</dt><dd>{{ $tipo->requisitos->where('activo', true)->count() }}</dd></div>
                </dl>
                <small>Fuente: {{ $tipo->base_normativa_fuente }}</small>
                <div class="acciones">
                    <button type="button" class="btn-unt-outline" data-abrir-modal="m-estado" data-accion="{{ route('bienestar.catalogo-solicitudes.toggle-activo', $tipo) }}"
                        data-campos="{{ json_encode(['titulo' => ($tipo->activo ? 'Desactivar' : 'Activar').' trámite', 'texto' => $tipo->activo ? 'Los estudiantes ya no podrán solicitar «'.$tipo->nombre.'». Las solicitudes en curso continúan.' : 'Los estudiantes podrán solicitar «'.$tipo->nombre.'».']) }}">{{ $tipo->activo ? 'Desactivar' : 'Activar' }}</button>
                    <a href="{{ route('bienestar.catalogo-solicitudes.edit', $tipo) }}" class="btn-unt-primary">Editar</a>
                </div>
            </article>
        @empty
            <p class="card-unt vacio">Aún no hay trámites en el catálogo.</p>
        @endforelse
    </div>
    {{ $tipos->links() }}
</div>
<x-bienestar::modal-unt id="m-estado" titulo="Cambiar estado" metodo="PATCH" boton="Confirmar"><p data-campo="texto"></p></x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-tramite" titulo="Nuevo trámite" detalle="Después podrás agregar sus requisitos." :accion="route('bienestar.catalogo-solicitudes.store')" boton="Crear trámite" ancho="42rem">
    <label class="campo">Nombre *<input name="nombre" value="{{ old('nombre') }}" required maxlength="200"></label>
    <label class="campo">Descripción<textarea name="descripcion" rows="2">{{ old('descripcion') }}</textarea></label>
    <div class="campos-2">
        <label class="campo">Unidad responsable *<input name="unidad_responsable" value="{{ old('unidad_responsable') }}" required maxlength="200"></label>
        <label class="campo">Plazo (días hábiles) *<input type="number" name="plazo_dias_habiles" min="1" max="90" value="{{ old('plazo_dias_habiles', 1) }}" required></label>
    </div>
    <label class="campo">Base normativa *<input name="base_normativa_fuente" value="{{ old('base_normativa_fuente') }}" required maxlength="500"></label>
    <label class="campo">Verificación *<select name="estado_verificacion"><option value="pendiente_verificar">Pendiente de verificar</option><option value="verificado" @selected(old('estado_verificacion') === 'verificado')>Verificado</option></select></label>
    <input type="hidden" name="activo" value="0">
    <label class="casilla"><input type="checkbox" name="activo" value="1" @checked(old('activo'))><span><b>Publicar ahora</b><small>Si aún no tiene requisitos, conviene dejarlo inactivo.</small></span></label>
</x-bienestar::modal-unt>
@endsection
