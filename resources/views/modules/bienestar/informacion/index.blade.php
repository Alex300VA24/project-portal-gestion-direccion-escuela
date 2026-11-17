@extends('modules.bienestar.layout')

@section('title', 'Información Académica y Bienestar · UNT')
@section('page-label', 'Normativa Académica')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Información Académica y Bienestar</h1>
            <p class="text-xs text-slate-600 mt-1">Normativas de permanencia, régimen disciplinario y orientaciones clave de la Escuela de Informática.</p>
        </div>
        @if ($puedeGestionar)
            <a href="{{ route('bienestar.informacion.create') }}" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition">
                + Redactar Artículo
            </a>
        @endif
    </div>

        <div class="mb-8 text-center max-w-2xl mx-auto">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Información Académica y Bienestar</h1>
            <p class="text-slate-600 mt-2 text-sm">
                Conoce las normativas universitarias, límites de permanencia, régimen disciplinario y procedimientos de bienestar de la Escuela de Informática.
            </p>
        </div>

        <div class="space-y-6">
            @forelse ($articulos as $art)
                <article class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:border-indigo-300 transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-full">
                                {{ $art->fuente_normativa }}
                            </span>
                            @if ($puedeGestionar)
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold {{ $art->estado === 'publicado' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst($art->estado) }}
                                </span>
                            @endif
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mt-2 hover:text-indigo-600 transition">
                            <a href="{{ route('bienestar.informacion.show', $art) }}">
                                {{ $art->titulo }}
                            </a>
                        </h2>
                        <p class="text-slate-600 text-sm mt-2 leading-relaxed">
                            {{ $art->resumen }}
                        </p>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Última revisión: <strong>{{ $art->fecha_ultima_revision?->format('d/m/Y') }}</strong></span>
                        <a href="{{ route('bienestar.informacion.show', $art) }}" class="font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center">
                            Leer detalle normativo &rarr;
                        </a>
                    </div>
                </article>
            @empty
                <div class="text-center py-12 bg-white rounded-xl border border-slate-200 text-slate-500">
                    <p class="text-base font-medium">No se encontraron artículos informativos publicados.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $articulos->links() }}
        </div>
</div>
@endsection
