@extends('modules.bienestar.layout')
@section('title', Str::limit($articulo->titulo, 45))
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.informacion.index'), 'texto' => 'Normativa'])@endsection
@section('page-label', Str::limit($articulo->titulo, 45))
@section('content')
<div class="space-y-6 max-w-4xl">
    @if ($puedeGestionar)<div class="acciones"><a href="{{ route('bienestar.informacion.edit', $articulo) }}" class="btn-unt-secondary">Editar artículo</a></div>@endif


        <article class="card-unt p-8">
            <div class="flex items-center space-x-3 mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-[color:var(--unt-azul-tinta)] bg-[color:var(--unt-fondo-suave)] border border-[color:var(--unt-azul-tinta)]/20 px-3 py-1 rounded-full">
                    {{ $articulo->fuente_normativa }}
                </span>
                @if ($puedeGestionar)
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold {{ $articulo->estado === 'publicado' ? 'bg-unt-azul text-white' : 'bg-amber-100 text-[color:var(--u-dorado-texto)]' }}">
                        {{ ucfirst($articulo->estado) }}
                    </span>
                @endif
                <span class="text-xs text-[color:var(--unt-texto-suave)]">
                    Última revisión: {{ $articulo->fecha_ultima_revision?->format('d/m/Y') }}
                </span>
            </div>

            <h1 class="text-3xl font-bold text-[color:var(--unt-azul-tinta)] tracking-tight leading-tight" style="font-family: 'Roboto Slab', serif;">
                {{ $articulo->titulo }}
            </h1>

            <div class="mt-4 p-4 rounded-xl bg-[color:var(--unt-fondo-suave)] border-l-4 border-[color:var(--unt-azul-tinta)] text-[color:var(--unt-texto)] text-sm font-medium">
                {{ $articulo->resumen }}
            </div>

            <div class="mt-8 prose prose-slate max-w-none text-[color:var(--unt-texto)] text-base leading-relaxed border-t border-[color:var(--unt-borde)] pt-6">
                {!! $articulo->detalle !!}
            </div>

            <div class="mt-8 pt-6 border-t border-[color:var(--unt-borde)] flex items-center justify-between text-xs text-[color:var(--unt-texto-suave)]">
                <p>Dirección de Bienestar Universitario · Escuela de Informática UNT</p>
            </div>
        </article>

</div>




@endsection
