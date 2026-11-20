<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <meta name="color-scheme" content="light">
  <title>@yield('title', 'Encuesta empresarial') · Universidad Nacional de Trujillo</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/seguimiento-egresado.css') }}">
  <style>
    .sg .sg-input:focus-visible, .sg .sg-select:focus-visible, .sg .sg-textarea:focus-visible { outline-offset: 0; border-color: var(--sg-focus); }
    body.sg { display: flex; flex-direction: column; min-height: 100dvh; }
    .sg-pub-contenido { flex: 1; width: 100%; display: flex; flex-direction: column; justify-content: center; }
    .sg-pub-cabecera, .sg-footer { flex: none; }
    .sg-pub-cabecera { background: var(--sg-navy); color: var(--sg-on-dark); }
    .sg-pub-cabecera div { display: flex; align-items: center; gap: var(--sg-space-3); max-width: 860px; margin: 0 auto; padding: var(--sg-space-3) var(--sg-space-4); }
    .sg-pub-marca { display: grid; place-items: center; flex: none; width: 36px; height: 36px; object-fit: contain; }
    .sg-pub-cabecera strong { display: block; line-height: 1.3; }
    .sg-pub-cabecera small { display: block; color: var(--sg-sidebar-muted); font-size: 12px; }
    .sg-pub-contenido { max-width: 860px; margin: 0 auto; padding: var(--sg-space-6) var(--sg-space-4) var(--sg-space-10); }
    .sg-pub-pie { max-width: 860px; margin: 0 auto; padding-inline: var(--sg-space-4); justify-content: center; text-align: center; }
    .sg-pub-contenido h1 { margin: 0 0 var(--sg-space-2); color: var(--sg-navy); font-size: clamp(24px, 4vw, 30px); line-height: 1.25; }
  </style>
  @stack('styles')
</head>
<body class="sg">
<a class="sg-skip-link" href="#contenido">Saltar al contenido</a>
<header class="sg-pub-cabecera">
  <div>
    <img class="sg-pub-marca" src="{{ asset('img/logo-informatica.png') }}" alt="">
    <span><strong>Seguimiento al Egresado</strong><small>Ingeniería Informática · Universidad Nacional de Trujillo</small></span>
  </div>
</header>
<main class="sg-pub-contenido" id="contenido" tabindex="-1">@yield('content')</main>
<footer class="sg-footer"><div class="sg-footer-inner sg-pub-pie"><span>Universidad Nacional de Trujillo</span></div></footer>
@stack('scripts')
</body>
</html>
