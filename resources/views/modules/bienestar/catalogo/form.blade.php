@extends('modules.bienestar.layout')

@section('title', 'Editar trámite · Bienestar')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.catalogo-solicitudes.index'), 'texto' => 'Catálogo de trámites'])@endsection
@section('page-label', 'Editar trámite')

@section('content')
<div class="space-y-6 max-w-4xl">
    <div class="pb-4 border-b border-[var(--unt-borde)]">
        <h1 class="text-2xl font-bold mt-1" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">{{ $tipo->nombre }}</h1>
    </div>


    <form method="POST" action="{{ route('bienestar.catalogo-solicitudes.update', $tipo) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="card-unt p-6 space-y-4">
            <h2 class="text-sm font-bold" style="color: var(--unt-azul-tinta);">Datos Principales del Procedimiento</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Nombre del Trámite / Procedimiento *</label>
                    <input type="text" name="nombre" value="{{ old('nombre', $tipo->nombre) }}" required class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Descripción General</label>
                    <textarea name="descripcion" rows="2" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)]">{{ old('descripcion', $tipo->descripcion) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Unidad Responsable *</label>
                    <input type="text" name="unidad_responsable" value="{{ old('unidad_responsable', $tipo->unidad_responsable) }}" required placeholder="Ej. Área de Servicio Social" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Plazo de Atención (días hábiles) *</label>
                    <input type="number" name="plazo_dias_habiles" min="1" max="90" value="{{ old('plazo_dias_habiles', $tipo->plazo_dias_habiles ?? 1) }}" required class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Estado de Verificación *</label>
                    <select name="estado_verificacion" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                        <option value="pendiente_verificar" @selected(old('estado_verificacion', $tipo->estado_verificacion ?? 'pendiente_verificar') === 'pendiente_verificar')>Pendiente de verificar con Bienestar UNT</option>
                        <option value="verificado" @selected(old('estado_verificacion', $tipo->estado_verificacion) === 'verificado')>Verificado formalmente</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Base Normativa / Fuente *</label>
                    <input type="text" name="base_normativa_fuente" value="{{ old('base_normativa_fuente', $tipo->base_normativa_fuente) }}" required placeholder="Ej. TUPA UNT Proc. 302 / Convocatoria Comedor 2022" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                </div>

                <div class="sm:col-span-2 flex items-center gap-2 pt-2">
                    <input type="hidden" name="activo" value="0"><input type="checkbox" id="activo" name="activo" value="1" @checked(old('activo', $tipo->activo ?? true)) class="rounded border-slate-300 text-[color:var(--unt-azul-tinta)] focus:ring-[var(--unt-dorado)]">
                    <label for="activo" class="text-xs font-semibold" style="color: var(--unt-texto);">Trámite activo y disponible para los estudiantes</label>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('bienestar.catalogo-solicitudes.index') }}" class="btn-unt-outline">Cancelar</a>
            <button type="submit" class="btn-unt-primary">
                Guardar cambios
            </button>
        </div>
    </form>

    @include('modules.bienestar.catalogo._requisitos')
</div>
@endsection
