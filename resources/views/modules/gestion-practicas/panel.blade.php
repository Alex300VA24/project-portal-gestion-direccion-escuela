@extends('modules.gestion-practicas.layout')

@section('title', 'Inicio')

@section('content')
    @php
        $opciones = array_values(array_filter(
            \App\Modules\GestionPracticas\Support\Menu::para($actor->user),
            fn (array $opcion): bool => $opcion['ruta'] !== 'gestion-practicas.panel',
        ));
        $roles = $actor->user->getRoleNames()->map(fn ($rol) => str_replace(['practicas_', '_'], ['', ' '], $rol))->implode(', ');
    @endphp

    @unless ($vinculada)
        <div class="quality-alert gp-aviso" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
            <div>
                <strong>Su cuenta de acceso aún no está vinculada a un usuario institucional.</strong>
                <span>Pida al administrador que registre el correo {{ $actor->user->email }} para poder operar en el módulo.</span>
            </div>
        </div>
    @endunless

    <header class="quality-page-heading">
        <div>
            <h1>Hola, {{ $actor->user->name }}</h1>
            <p>Gestión de Prácticas · {{ $roles !== '' ? 'Rol: '.$roles : 'Sin rol asignado' }}</p>
        </div>
        @if ($resumen !== [])
            <dl class="quality-inline-stats" aria-label="Resumen de su actividad">
                @foreach ($resumen as $titulo => $valor)
                    <div><dt>{{ $titulo }}</dt><dd>{{ $valor }}</dd></div>
                @endforeach
            </dl>
        @endif
    </header>

    <section class="quality-panel" aria-labelledby="acceso-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="acceso-titulo">Qué puede hacer</h2><p>Opciones disponibles para su rol.</p></div>
        </header>
        @if ($opciones === [])
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'Sin rol de prácticas asignado',
                'texto' => 'Contacte al administrador del portal para que le asigne un rol en este módulo.',
            ])
        @else
            <div class="quality-plan-list">
                @foreach ($opciones as $opcion)
                    <article>
                        <div><strong>{{ $opcion['texto'] }}</strong><small>{{ $opcion['ayuda'] }}</small></div>
                        <a class="quality-open-link" href="{{ route($opcion['ruta']) }}">Abrir</a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
