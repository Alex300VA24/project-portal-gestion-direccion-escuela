@extends('modules.bienestar.layout')

@section('title', 'Inicio · ' . $module)
@section('page-label', 'Inicio')

@section('content')
<div class="space-y-8">
    <header class="portada">
        <div>
            <span>{{ now('America/Lima')->locale('es')->isoFormat('dddd D [de] MMMM') }}</span>
            @auth
                <h1>Hola, {{ auth()->user()->name }}</h1>
                <p>{{ $resumen['pendientes'] ? 'Esto es lo que necesita tu atención hoy.' : 'Estás al día: no tienes pendientes.' }}</p>
            @else
                <h1>Gestión de Bienestar Estudiantil</h1>
                <p>Acompañamiento, trámites de apoyo y orientación para permanecer y avanzar en la Escuela de Informática.</p>
            @endauth
        </div>
        <img src="{{ asset('img/logo-informatica.png') }}" alt="" width="112" height="112">
    </header>

    @if ($resumen['kpis'])
        <section class="kpis" aria-label="Indicadores">
            @foreach ($resumen['kpis'] as $k)
                <a href="{{ $k['url'] }}" class="kpi kpi-{{ $k['tono'] }}"><strong>{{ $k['valor'] }}</strong><span>{{ $k['texto'] }}</span></a>
            @endforeach
        </section>
    @endif

    <div @class(['inicio-columnas' => $resumen['pendientes']])>
        @if ($resumen['pendientes'])
            <section class="card-unt pendientes">
                <h2>Pendientes</h2>
                @foreach ($resumen['pendientes'] as $p)
                    <a href="{{ $p['url'] }}" class="pendiente-{{ $p['tono'] }}"><span><b>{{ $p['titulo'] }}</b><small>{{ $p['detalle'] }}</small></span><i aria-hidden="true">&rarr;</i></a>
                @endforeach
            </section>
        @endif
        <section class="accesos" aria-label="Accesos">
            @foreach ($resumen['accesos'] as $a)
                <a href="{{ $a['url'] }}" class="card-unt acceso">
                    @include('modules.bienestar.partials.icono', ['icono' => $a['icono']])
                    <span><b>{{ $a['titulo'] }}</b><small>{{ $a['detalle'] }}</small></span>
                </a>
            @endforeach
            @guest
                <a href="{{ route('bienestar.informacion.index') }}" class="card-unt acceso">@include('modules.bienestar.partials.icono', ['icono' => 'libro'])<span><b>Normativa académica</b><small>Matrícula, inasistencias, retiro y permanencia explicados con sencillez.</small></span></a>
                <a href="{{ route('login') }}" class="card-unt acceso">@include('modules.bienestar.partials.icono', ['icono' => 'documento'])<span><b>Iniciar sesión</b><small>Presenta solicitudes, consulta tus citas o gestiona tu bandeja.</small></span></a>
            @endguest
        </section>
    </div>
</div>
@endsection
