@unless ($ocultarRuta ?? false)
<nav class="gh-admin-breadcrumb" aria-label="Ruta actual"><a href="{{ route('dashboard') }}">Portal</a> / <a href="{{ route('gestion-horarios.administracion') }}">Gestión de Horarios Académicos</a> / <span aria-current="page">{{ $subtitulo ?? $titulo }}</span></nav>
@endunless
<header class="quality-page-heading gh-admin-heading">
    <span class="quality-eyebrow">Gestión de Horarios Académicos</span>
    <h1>{{ $titulo }}</h1>
    @if (!empty($subtitulo))<h2>{{ $subtitulo }}</h2>@endif
    <p>{{ $descripcion }}</p>
</header>
