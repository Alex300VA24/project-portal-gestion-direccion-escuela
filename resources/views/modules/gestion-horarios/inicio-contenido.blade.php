@if (! $puedeAdministrar && auth()->check() && auth()->user()->hasAnyRole(['alumno','profesor']) && !auth()->user()->hasAnyRole(['director_escuela','secretaria']))
    @include('modules.gestion-horarios.inicio-lectura')
@else
{{-- El CSS se limita a Gestión de Horarios y no afecta a otros módulos. --}}
@include('modules.gestion-horarios.inicio-estilos')

<div class="gh-home">
    @unless ($puedeAdministrar)
    <nav class="gh-home__breadcrumb" aria-label="Ruta actual">
        <a href="{{ url('/') }}">Portal</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Gestión de Horarios</span>
    </nav>
    @endunless

    @unless ($puedeAdministrar)
    <header class="gh-home__hero">
        <div class="gh-home__hero-content">
            <span class="gh-home__eyebrow">Universidad Nacional de Trujillo</span>
            <h1>Gestión de Horarios Académicos</h1>
            <p>Un espacio para preparar, verificar y consultar la planificación académica de su Escuela.</p>
        </div>
        <svg class="gh-home__hero-illustration" viewBox="0 0 120 120" aria-hidden="true">
            <rect x="20" y="23" width="80" height="78" rx="10" />
            <path d="M20 47h80M40 16v14M80 16v14M35 63h15M65 63h15M35 81h15M65 81h15" />
        </svg>
    </header>
    @endunless

    @if ($puedeAdministrar)
        @livewire(\App\Modules\GestionHorarios\Livewire\WorkspaceAcademico::class)
        @if ($revisionesDisponibles ?? false)
            <p><a href="{{ route('gestion-horarios.revisiones') }}">Revisiones de oferta</a></p>
        @endif
    @elseif (auth()->guest())
        <section class="gh-home__access" aria-labelledby="gh-acceso-titulo">
            <h2 id="gh-acceso-titulo">Administración académica</h2>
            <p>Para acceder a la administración de horarios, inicie sesión con una cuenta que tenga el rol institucional correspondiente.</p>
            <a class="gh-home__button" href="{{ route('login') }}">Iniciar sesión <span aria-hidden="true">→</span></a>
        </section>
    @elseif (auth()->user()->hasAnyRole(['director_escuela', 'secretaria']))
        <section class="gh-home__access" aria-labelledby="gh-acceso-titulo">
            <h2 id="gh-acceso-titulo">Mi espacio de administración académica</h2>
            <p role="status"><strong>Ingreso administrativo confirmado.</strong> Está autenticado como {{ auth()->user()->hasRole('director_escuela', 'web') ? 'Director de Escuela' : 'Secretaría Académica' }}.</p>
            @if (! auth()->user()->can('gestion-horarios.view'))
                <p>Su rol está reconocido, pero no tiene acreditado el permiso de consulta de Gestión de Horarios. Comuníquese con la administración del portal.</p>
            @else
                <p>Su permiso de consulta está confirmado. Sin embargo, todavía no se ha podido verificar su adscripción institucional a una escuela.</p>
                <p>Puede consultar los horarios publicados más abajo. La oferta, las secciones y los demás datos administrativos se mostrarán cuando se acredite la escuela autorizada; no es necesario volver a iniciar sesión.</p>
            @endif
        </section>
    @endif

    @if (auth()->check() && auth()->user()->hasRole('profesor', 'web'))
        <section class="gh-home__public" aria-labelledby="gh-disponibilidad-propia">
            <div class="gh-home__public-content"><div><h2 id="gh-disponibilidad-propia">Mi espacio docente</h2><p>Consulte su horario publicado y disponibilidad docente. El acceso personal requiere que su cuenta esté vinculada a su registro docente.</p></div></div>
            <a class="gh-home__button" href="{{ route('gestion-horarios.mi-horario') }}">Mi horario <span aria-hidden="true">→</span></a>
            <a class="gh-home__button" href="{{ route('gestion-horarios.mi-disponibilidad') }}">Consultar disponibilidad <span aria-hidden="true">→</span></a>
        </section>
    @endif
    @if (auth()->check() && auth()->user()->hasRole('alumno', 'web'))
        <section class="gh-home__public" aria-labelledby="gh-horario-alumno">
            <div class="gh-home__public-content"><div><h2 id="gh-horario-alumno">Mi espacio académico</h2><p>Consulte el horario publicado por ciclo y sección. Su horario personal estará disponible cuando se pueda verificar su matrícula vigente.</p></div></div>
            <a class="gh-home__button" href="{{ route('gestion-horarios.consulta-publicados') }}">Consultar horario</a>
            <a class="gh-home__button" href="{{ route('gestion-horarios.panel') }}">Panel de ambientes</a>
        </section>
    @endif
    <section class="gh-home__public" aria-labelledby="gh-publicados-titulo">
        <div class="gh-home__public-content">
            <span class="gh-home__public-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 3v4M17 3v4M3 10h18m-11 5 2 2 4-4" /></svg>
            </span>
            <div>
                <span class="gh-home__stage">Consulta académica</span>
                <h2 id="gh-publicados-titulo">Horarios publicados</h2>
                <p>Consulte los horarios que se encuentren publicados por la Escuela.</p>
            </div>
        </div>
        <a class="gh-home__button" href="{{ auth()->check() ? route('gestion-horarios.consulta-publicados') : route('gestion-horarios.catalogo-publico') }}">Ver horarios publicados <span aria-hidden="true">→</span></a>
        @auth<a class="gh-home__button" href="{{ route('gestion-horarios.panel') }}">Panel de ambientes <span aria-hidden="true">→</span></a>@endauth
    </section>
</div>

@endif
