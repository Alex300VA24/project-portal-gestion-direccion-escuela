<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="color-scheme" content="light">
  <title>@yield('title', 'Inicio') · Seguimiento al Egresado | UNT</title>
  <link rel="stylesheet" href="{{ asset('css/seguimiento-egresado.css') }}">
  @stack('styles')
</head>
<body class="sg">
<a class="sg-skip-link" href="#contenido">Saltar al contenido</a>
<div class="sg-shell">
  <aside class="sg-sidebar" aria-label="Módulo Seguimiento al Egresado">
    <div class="sg-sidebar-head">
      <a class="sg-brand" href="{{ route('seguimiento-egresado.index') }}" aria-label="Seguimiento al Egresado · Inicio">
        <span class="sg-brand-symbol" aria-hidden="true">SE<span></span></span>
        <span class="sg-brand-copy"><strong>Seguimiento al Egresado</strong><small>Ingeniería Informática de la UNT</small></span>
      </a>
      <button class="sg-menu-toggle" type="button" aria-label="Menú de navegación" aria-controls="sg-navigation" aria-expanded="false" hidden>
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'menu'])
        <span>Menú</span>
      </button>
    </div>
    <nav class="sg-navigation" id="sg-navigation" aria-label="Navegación principal">
      <div class="sg-nav">
        @foreach ([
          ['index', 'Inicio', 'home'],
          ['padron', 'Padrón y cohortes', 'users'],
          ['campanias', 'Campañas e invitaciones', 'mail'],
          ['empresas', 'Encuesta empresarial', 'building'],
          ['indicadores', 'Indicadores I1 a I6', 'chart'],
          ['institucional', 'Evaluación institucional', 'clipboard'],
        ] as [$route, $label, $icon])
          <a class="sg-nav-link {{ request()->routeIs('seguimiento-egresado.'.$route) ? 'sg-active' : '' }}"
             href="{{ route('seguimiento-egresado.'.$route) }}" @if(request()->routeIs('seguimiento-egresado.'.$route)) aria-current="page" @endif>
            @include('modules.seguimiento-egresado.partials.icon', ['name' => $icon])
            <span>{{ $label }}</span>
          </a>
        @endforeach
      </div>
    </nav>
    <div class="sg-sidebar-foot">
      <span class="sg-sidebar-university">Universidad Nacional de Trujillo</span>
      <span>Escuela de Ingeniería Informática</span>
    </div>
  </aside>
  <div class="sg-main-area">
    <header class="sg-topbar">
      <nav class="sg-breadcrumb sg-container" aria-label="Ruta de navegación">
        <ol>
          <li><a href="{{ route('dashboard') }}">Portal</a></li>
          @if(request()->routeIs('seguimiento-egresado.index'))
            <li><span aria-current="page">Seguimiento al Egresado</span></li>
          @else
            <li><a href="{{ route('seguimiento-egresado.index') }}">Seguimiento al Egresado</a></li>
            <li><span aria-current="page">@yield('section_name', 'Vista general')</span></li>
          @endif
        </ol>
      </nav>
      <div class="sg-user">
        @auth
          <span class="sg-user-name"><strong>{{ auth()->user()->name }}</strong><small>{{ \Illuminate\Support\Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario institucional')->replace('_', ' ') }}</small></span>
          <a class="sg-button sg-button-secondary sg-user-action" href="{{ route('dashboard') }}">Cambiar de módulo</a>
          <form method="POST" action="{{ route('logout') }}">@csrf<button class="sg-button sg-button-secondary sg-user-action" type="submit">Cerrar sesión</button></form>
        @else
          <a class="sg-button sg-user-action" href="{{ route('login') }}">Iniciar sesión</a>
        @endauth
      </div>
    </header>
    <main class="sg-main" id="contenido" tabindex="-1">@yield('content')</main>
    <footer class="sg-footer"><div class="sg-container sg-footer-inner"><span>Universidad Nacional de Trujillo</span></div></footer>
  </div>
</div>
<script>
  (() => {
    const sidebar = document.querySelector('.sg-sidebar');
    const button = sidebar.querySelector('.sg-menu-toggle');
    const navigation = sidebar.querySelector('.sg-navigation');
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
@stack('scripts')
</body>
</html>
