@php
  $identidad = \App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso::desde(request())->egresado();
  $esEgresado = $identidad?->perfil === \App\Modules\SeguimientoEgresado\Support\Accesos\Perfil::Egresado;
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title', 'Mi seguimiento') · Seguimiento al Egresado | UNT</title>
  <link rel="stylesheet" href="{{ asset('css/seguimiento-egresado.css') }}">
  @include('modules.seguimiento-egresado.egresado._estilos')
  @stack('styles')
</head>
<body class="sg sgs-scope">
<a class="sg-skip-link" href="#contenido">Saltar al contenido</a>
{{-- Mismo armazón que el resto del módulo, pero el menú solo ofrece las opciones del propio egresado. --}}
<div class="sg-shell">
  <aside class="sg-sidebar" aria-label="Módulo Seguimiento al Egresado">
    <div class="sg-sidebar-head">
      <a class="sg-brand" href="{{ $esEgresado ? route('seguimiento-egresado.egresado.inicio') : '#contenido' }}" aria-label="Seguimiento al Egresado">
        <span class="sg-brand-symbol" aria-hidden="true">SE<span></span></span>
        <span class="sg-brand-copy"><strong>Seguimiento al Egresado</strong><small>Ingeniería Informática de la UNT</small></span>
      </a>
      @if ($esEgresado)
        <button class="sg-menu-toggle" type="button" aria-label="Menú de navegación" aria-controls="sg-navigation" aria-expanded="false" hidden>
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'menu'])
          <span>Menú</span>
        </button>
      @endif
    </div>
    @if ($esEgresado)
      <nav class="sg-navigation" id="sg-navigation" aria-label="Mi expediente">
        <div class="sg-nav">
          @foreach ([
            ['seguimiento-egresado.egresado.inicio', 'seguimiento-egresado.egresado.inicio', 'Inicio', 'home'],
            ['seguimiento-egresado.egresado.contacto', 'seguimiento-egresado.egresado.contacto*', 'Mi contacto', 'mail'],
            ['seguimiento-egresado.egresado.historial', 'seguimiento-egresado.egresado.historial*', 'Mi historial', 'clipboard'],
          ] as [$ruta, $patron, $texto, $icono])
            <a class="sg-nav-link {{ request()->routeIs($patron) ? 'sg-active' : '' }}" href="{{ route($ruta) }}" @if(request()->routeIs($patron)) aria-current="page" @endif>
              @include('modules.seguimiento-egresado.partials.icon', ['name' => $icono])
              <span>{{ $texto }}</span>
            </a>
          @endforeach
        </div>
      </nav>
    @endif
    <div class="sg-sidebar-foot">
      <span class="sg-sidebar-university">Universidad Nacional de Trujillo</span>
      <span>Escuela de Ingeniería Informática</span>
    </div>
  </aside>
  <div class="sg-main-area">
    <header class="sg-topbar">
      <nav class="sg-breadcrumb sg-container" aria-label="Ruta de navegación">
        <ol>
          <li><span>Seguimiento al Egresado</span></li>
          <li><span aria-current="page">@yield('title', 'Mi seguimiento')</span></li>
        </ol>
      </nav>
    </header>
    <main class="sg-main" id="contenido" tabindex="-1">
      @yield('content')
    </main>
    <footer class="sg-footer"><div class="sg-container sg-footer-inner"><span>Universidad Nacional de Trujillo</span></div></footer>
  </div>
</div>
<script>
  (() => {
    const sidebar = document.querySelector('.sg-sidebar');
    const button = sidebar.querySelector('.sg-menu-toggle');
    const navigation = sidebar.querySelector('.sg-navigation');
    if (!button || !navigation) return;
    const mobile = window.matchMedia('(max-width: 900px)');
    const setOpen = (open) => {
      const expanded = open && mobile.matches;
      sidebar.classList.toggle('sg-menu-open', expanded);
      button.setAttribute('aria-expanded', String(expanded));
    };
    button.hidden = false;
    sidebar.classList.add('sg-has-menu');
    button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && mobile.matches && button.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        button.focus();
      }
    });
    document.addEventListener('click', (event) => {
      if (!sidebar.contains(event.target)) setOpen(false);
    });
    sidebar.addEventListener('focusout', (event) => {
      if (!sidebar.contains(event.relatedTarget)) setOpen(false);
    });
    mobile.addEventListener('change', () => {
      if (mobile.matches && navigation.contains(document.activeElement)) button.focus();
      setOpen(false);
    });
  })();
</script>
@include('modules.seguimiento-egresado.partials.dialogo')
@stack('scripts')
</body>
</html>
