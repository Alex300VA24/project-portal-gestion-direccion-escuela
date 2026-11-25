@extends('layouts.cotecu')

@section('title', $titulo)
@section('page-label', $titulo)

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>{{ $titulo }}</h1>
                <p>{{ $descripcion }}</p>
            </div>
        </header>

        <section class="quality-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v4"/><path d="M12 7a5 5 0 0 1 5 5c0 2.5-1.5 3.5-2.5 4.5S14 18 14 20h-4c0-2 .5-3-1.5-4.5S7 14.5 7 12a5 5 0 0 1 5-5Z"/><path d="M10 22h4"/></svg>
            <h2>Sección en construcción</h2>
            <p>Esta pantalla se habilitará en la siguiente entrega del módulo COTECU.</p>
        </section>
    </div>
@endsection
