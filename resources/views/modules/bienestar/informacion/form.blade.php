@extends('modules.bienestar.layout')
@section('title', $isEditing ? 'Editar artículo' : 'Nuevo artículo')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.informacion.index'), 'texto' => 'Normativa'])@endsection
@section('page-label', $isEditing ? 'Editar artículo' : 'Nuevo artículo')
@section('content')
<div class="space-y-6 max-w-4xl">
    <div class="encabezado"><div><h1>{{ $isEditing ? 'Editar artículo' : 'Redactar artículo' }}</h1><p>Información pública para la comunidad estudiantil.</p></div></div>


        <form action="{{ $isEditing ? route('bienestar.informacion.update', $articulo) : route('bienestar.informacion.store') }}" method="POST" class="card-unt p-8 space-y-6">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <div>
                <label for="titulo" class="block text-sm font-bold text-[color:var(--unt-azul-tinta)]">Título del Artículo *</label>
                <input type="text" name="titulo" id="titulo" value="{{ old('titulo', $articulo->titulo) }}" required maxlength="200" class="mt-1 block w-full rounded-lg border-[color:var(--unt-borde)] shadow-xs focus:border-[color:var(--unt-azul-tinta)] focus:ring-1 focus:ring-[#12377B] text-sm">
            </div>

            <div>
                <label for="fuente_normativa" class="block text-sm font-bold text-[color:var(--unt-azul-tinta)]">Fuente Normativa (Art. y Ley / Reglamento) *</label>
                <input type="text" name="fuente_normativa" id="fuente_normativa" value="{{ old('fuente_normativa', $articulo->fuente_normativa) }}" required maxlength="255" placeholder="Ej. Ley Universitaria 30220, Art. 102 [Pendiente de verificación UNT]" class="mt-1 block w-full rounded-lg border-[color:var(--unt-borde)] shadow-xs focus:border-[color:var(--unt-azul-tinta)] focus:ring-1 focus:ring-[#12377B] text-sm">
            </div>

            <div>
                <label for="resumen" class="block text-sm font-bold text-[color:var(--unt-azul-tinta)]">Resumen en Lenguaje Sencillo *</label>
                <textarea name="resumen" id="resumen" rows="2" required maxlength="500" class="mt-1 block w-full rounded-lg border-[color:var(--unt-borde)] shadow-xs focus:border-[color:var(--unt-azul-tinta)] focus:ring-1 focus:ring-[#12377B] text-sm">{{ old('resumen', $articulo->resumen) }}</textarea>
                <p class="text-xs text-[color:var(--unt-texto-suave)] mt-1">Breve síntesis para que el estudiante comprenda el impacto de inmediato.</p>
            </div>

            <div>
                <label for="detalle" class="block text-sm font-bold text-[color:var(--unt-azul-tinta)]">Contenido Detallado (HTML permitido: párrafos, listas, negritas) *</label>
                <textarea name="detalle" id="detalle" rows="8" required class="mt-1 block w-full rounded-lg border-[color:var(--unt-borde)] shadow-xs focus:border-[color:var(--unt-azul-tinta)] focus:ring-1 focus:ring-[#12377B] text-sm font-mono">{{ old('detalle', $articulo->detalle) }}</textarea>
                <p class="text-xs text-[color:var(--unt-texto-suave)] mt-1">El contenido es sanitizado automáticamente eliminando scripts o elementos no autorizados.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="fecha_ultima_revision" class="block text-sm font-bold text-[color:var(--unt-azul-tinta)]">Fecha de Última Revisión *</label>
                    <input type="date" name="fecha_ultima_revision" id="fecha_ultima_revision" value="{{ old('fecha_ultima_revision', $articulo->fecha_ultima_revision ? $articulo->fecha_ultima_revision->format('Y-m-d') : date('Y-m-d')) }}" required class="mt-1 block w-full rounded-lg border-[color:var(--unt-borde)] shadow-xs focus:border-[color:var(--unt-azul-tinta)] focus:ring-1 focus:ring-[#12377B] text-sm">
                </div>

                <div>
                    <label for="estado" class="block text-sm font-bold text-[color:var(--unt-azul-tinta)]">Estado de Publicación *</label>
                    <select name="estado" id="estado" required class="mt-1 block w-full rounded-lg border-[color:var(--unt-borde)] shadow-xs focus:border-[color:var(--unt-azul-tinta)] focus:ring-1 focus:ring-[#12377B] text-sm">
                        <option value="publicado" @selected(old('estado', $articulo->estado) === 'publicado')>Publicado (Visible para todos)</option>
                        <option value="borrador" @selected(old('estado', $articulo->estado) === 'borrador')>Borrador (Solo personal de Bienestar)</option>
                    </select>
                </div>
            </div>

            <div class="pt-6 border-t border-[color:var(--unt-borde)] flex items-center justify-end space-x-3">
                <a href="{{ route('bienestar.informacion.index') }}" class="btn-unt-outline text-sm">
                    Cancelar
                </a>
                <button type="submit" class="btn-unt-primary text-sm">
                    {{ $isEditing ? 'Guardar Cambios' : 'Crear Artículo' }}
                </button>
            </div>
        </form>

</div>




@endsection
