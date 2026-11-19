@extends('modules.gestion-practicas.layout')

@section('title', 'Empresas')
@section('page-label', 'Empresas')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Empresas</h1><p>Empresas con cuenta de acceso al módulo. Solo ellas pueden publicar convocatorias.</p></div>
        <a class="institutional-primary" href="{{ route('gestion-practicas.empresas.create') }}">Registrar empresa</a>
    </header>

    <section class="quality-panel" aria-labelledby="empresas-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="empresas-titulo">Empresas registradas</h2><p>{{ count($empresas) }} {{ count($empresas) === 1 ? 'empresa' : 'empresas' }}</p></div>
        </header>
        @forelse ($empresas as $empresa)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">RUC {{ $empresa->ruc }}</span>
                    <strong>{{ $empresa->razon_social }}</strong>
                    <small>Cuenta de acceso: {{ $empresa->cuenta ?? 'sin cuenta' }}</small>
                </div>
                <div>@include('modules.gestion-practicas.partials.estado', ['estado' => $empresa->estado])</div>
                <form method="POST" action="{{ route('gestion-practicas.empresas.estado', $empresa->id_empresa) }}">
                    @csrf
                    <button class="quality-open-link">{{ strtolower($empresa->estado) === 'activo' ? 'Desactivar' : 'Activar' }}</button>
                </form>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'Todavía no hay empresas registradas',
                'texto' => 'Registre una empresa para crear su cuenta de acceso; luego podrá publicar convocatorias.',
            ])
        @endforelse
    </section>
@endsection
