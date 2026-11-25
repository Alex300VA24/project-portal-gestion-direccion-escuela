@extends('modules.bienestar.layout')

@section('title', 'Información Académica y Bienestar · UNT')
@section('page-label', 'Normativa Académica')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[color:var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold text-[color:var(--unt-azul-tinta)] tracking-tight" style="font-family: 'Roboto Slab', serif;">
                Información Académica y Bienestar
            </h1>
            <p class="text-xs text-[color:var(--unt-texto-suave)] mt-1">Normativas de permanencia, régimen disciplinario y orientaciones clave de la Escuela de Informática.</p>
        </div>
        @if ($puedeGestionar)
            <a href="{{ route('bienestar.informacion.create') }}" class="btn-unt-primary text-xs">
                + Redactar Artículo
            </a>
        @endif
    </div>

    <div class="space-y-6">
        @forelse ($articulos as $art)
            <article class="card-unt p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[color:var(--unt-azul-tinta)] bg-[color:var(--unt-fondo-suave)] border border-[color:var(--unt-azul-tinta)]/20 px-2.5 py-1 rounded-full">
                            {{ $art->fuente_normativa }}
                        </span>
                        @if ($puedeGestionar)
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold {{ $art->estado === 'publicado' ? 'bg-unt-azul text-white' : 'bg-amber-100 text-[color:var(--u-dorado-texto)]' }}">
                                {{ ucfirst($art->estado) }}
                            </span>
                        @endif
                    </div>
                    <h2 class="text-xl font-bold text-[color:var(--unt-azul-tinta)] mt-2 hover:text-[color:var(--unt-azul-tinta)] transition" style="font-family: 'Roboto Slab', serif;">
                        <a href="{{ route('bienestar.informacion.show', $art) }}">
                            {{ $art->titulo }}
                        </a>
                    </h2>
                    <p class="text-[color:var(--unt-texto-suave)] text-sm mt-2 leading-relaxed">
                        {{ $art->resumen }}
                    </p>
                </div>
                <div class="mt-4 pt-4 border-t border-[color:var(--unt-borde)] flex items-center justify-between text-xs text-[color:var(--unt-texto-suave)]">
                    <span>Última revisión: <strong>{{ $art->fecha_ultima_revision?->format('d/m/Y') }}</strong></span>
                    <a href="{{ route('bienestar.informacion.show', $art) }}" class="font-bold text-[color:var(--unt-azul-tinta)] hover:text-[color:var(--unt-azul-tinta)] inline-flex items-center">
                        Leer detalle normativo &rarr;
                    </a>
                </div>
            </article>
        @empty
            <div class="text-center py-12 bg-white rounded-xl border border-[color:var(--unt-borde)] text-[color:var(--unt-texto-suave)]">
                <p class="text-base font-medium">No se encontraron artículos informativos publicados.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $articulos->links() }}
    </div>
</div>
@endsection
