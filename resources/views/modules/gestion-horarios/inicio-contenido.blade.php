@php
    // Enlaces propios del módulo; no se modifican rutas ni permisos institucionales.
    $apartados = [
        ['periodos', 'Periodos académicos', 'Consulte el periodo institucional para la planificación.', 'M3 5h18v16H3z M7 3v4 M17 3v4 M3 10h18'],
        ['oferta', 'Oferta académica', 'Revise los cursos ofertados y la demanda recibida.', 'M12 6c-3-2-6-2-9-1v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1z M12 6v14'],
        ['secciones', 'Distribución de secciones', 'Consulte las secciones, capacidades y demanda.', 'M9 5a3 3 0 1 0 0 6a3 3 0 0 0 0-6z M3 20v-2a6 6 0 0 1 12 0v2 M16 5a3 3 0 0 1 0 6 M17 14a5 5 0 0 1 4 5v1'],
        ['asignacion-docente', 'Asignación docente', 'Revise actividades y docentes asignados.', 'M9 3a4 4 0 1 0 0 8a4 4 0 0 0 0-8z M2 21v-2a7 7 0 0 1 12-5 M16 18l2 2 4-5'],
        ['disponibilidad-docente', 'Disponibilidad docente', 'Consulte las declaraciones y bloques registrados.', 'M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18z M12 7v5l4 2'],
        ['ambientes', 'Ambientes y laboratorios', 'Revise capacidades, bloqueos y ocupación registrada.', 'M4 3h16v18H4z M9 21v-5h6v5 M8 7h2 M14 7h2 M8 11h2 M14 11h2'],
        ['restricciones', 'Restricciones académicas', 'Consulte las reglas registradas para planificar.', 'M12 2L4 5v6c0 5 3.5 8.5 8 11c4.5-2.5 8-6 8-11V5l-8-3z M9 12l2 2 4-4'],
        ['revisiones', 'Revisiones de oferta', 'Consulte las revisiones de oferta pendientes.', 'M5 4h14v18H5z M9 4V2h6v2 M9 11h6 M9 15h6'],
    ];

    // Agrupación visual del flujo. No altera la lógica ni los enlaces del módulo.
    $gruposPreparacion = [
        ['titulo' => 'Definir la base del periodo', 'descripcion' => 'Empiece por los datos que determinan qué cursos y grupos se planificarán.', 'items' => [0, 1, 2]],
        ['titulo' => 'Organizar los recursos', 'descripcion' => 'Revise quién dictará las actividades, cuándo podrá hacerlo y dónde.', 'items' => [3, 4, 5]],
        ['titulo' => 'Comprobar antes de planificar', 'descripcion' => 'Revise las condiciones académicas y las observaciones de oferta.', 'items' => [6, 7]],
    ];

    $puedeAdministrar = auth()->check()
        && auth()->user()->hasAnyRole(['director_escuela', 'secretaria'])
        && (new \App\Modules\GestionHorarios\Policies\RegistroPolicy)->viewAny(auth()->user())
        && (new \App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa)->escuelas(auth()->user()) !== [];

@endphp

{{-- El CSS se limita a Gestión de Horarios y no afecta a otros módulos. --}}
@include('modules.gestion-horarios.inicio-estilos')

<div class="gh-home">
    <nav class="gh-home__breadcrumb" aria-label="Ruta actual">
        <a href="{{ url('/') }}">Portal</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Gestión de Horarios</span>
    </nav>

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

    @if ($puedeAdministrar)
        <nav class="gh-home__quicknav" aria-label="Áreas de Gestión de Horarios">
            <a href="#gh-resumen"><span aria-hidden="true">⌂</span> Inicio</a>
            <a href="#gh-preparacion-titulo"><span aria-hidden="true">▤</span> Preparación académica</a>
            <a href="#gh-generacion-titulo"><span aria-hidden="true">▦</span> Elaboración de horarios</a>
            <a href="#gh-publicados-titulo"><span aria-hidden="true">✓</span> Consulta publicada</a>
        </nav>

        <section class="gh-home__overview" id="gh-resumen" aria-labelledby="gh-resumen-titulo">
            <div class="gh-home__overview-copy">
                <span class="gh-home__stage">Panel de inicio</span>
                <h2 id="gh-resumen-titulo">Planificación del periodo académico</h2>
                <p>Desde aquí puede revisar el periodo institucional, preparar las actividades y consultar los horarios existentes. La elaboración automática continúa pendiente de integración.</p>
                <div class="gh-home__overview-actions">
                    <a class="gh-home__button" href="{{ route('gestion-horarios.oferta') }}">Consultar oferta <span aria-hidden="true">→</span></a>
                </div>
            </div>
            <div class="gh-home__overview-aside">
                <span class="gh-home__overview-label">Para comenzar</span>
                <span class="gh-home__overview-period">Periodo académico</span>
                <p>Seleccione el periodo al consultar la oferta o las secciones autorizadas.</p>
                <span class="gh-home__overview-status"><span aria-hidden="true">●</span> Algunas consultas requieren autorización adicional</span>
            </div>
        </section>
        <section class="gh-home__section gh-home__section--main" aria-labelledby="gh-preparacion-titulo">
            <div class="gh-home__section-heading">
                <div>
                    <span class="gh-home__stage">Su ruta de trabajo</span>
                    <h2 id="gh-preparacion-titulo">¿Qué necesita hacer?</h2>
                    <p>Siga estos pasos para revisar la información que se usará al elaborar los horarios.</p>
                </div>
            </div>

            <div class="gh-home__workflow">
                @foreach ($gruposPreparacion as $grupo)
                    @continue(! array_intersect($grupo['items'], [1, 2, 7]))
                    <section class="gh-home__workflow-step" aria-labelledby="gh-grupo-{{ $loop->iteration }}">
                        <div class="gh-home__workflow-heading">
                            <span class="gh-home__step-number" aria-hidden="true">{{ $loop->iteration }}</span>
                            <div>
                                <h3 id="gh-grupo-{{ $loop->iteration }}">{{ $grupo['titulo'] }}</h3>
                                <p>{{ $grupo['descripcion'] }}</p>
                            </div>
                        </div>
                        <div class="gh-home__step-links">
                            @foreach ($grupo['items'] as $indice)
                                @continue(! in_array($indice, [1, 2, 7], true))
                                @php([$ruta, $titulo, $descripcion, $trazo] = $apartados[$indice])
                                <a class="gh-home__step-link" href="{{ route('gestion-horarios.'.$ruta) }}">
                                    <span class="gh-home__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="{{ $trazo }}" /></svg></span>
                                    <span class="gh-home__step-copy"><strong>{{ $titulo }}</strong><small>{{ $descripcion }}</small></span>
                                    <span class="gh-home__step-arrow" aria-hidden="true">→</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <section class="gh-home__section" aria-labelledby="gh-generacion-titulo">
            <div class="gh-home__section-heading">
                <div>
                    <span class="gh-home__stage">Después de la preparación</span>
                    <h2 id="gh-generacion-titulo">Continuar con los horarios</h2>
                    <p>La consulta de versiones y la generación requieren completar las autorizaciones institucionales.</p>
                </div>
            </div>

            <div class="gh-home__notice" role="note">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
                <p>Las consultas de periodos, asignación y disponibilidad docente, ambientes, restricciones y preparación de generación requieren autorizaciones adicionales. La generación, revisión, aprobación, publicación y exportación de propuestas todavía no están disponibles desde este módulo integrado.</p>
            </div>
        </section>
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
            <div class="gh-home__public-content"><div><h2 id="gh-disponibilidad-propia">Mi espacio docente</h2><p>Consulte su disponibilidad docente y sus cursos asignados cuando la vinculación de su cuenta esté acreditada. El horario docente personal todavía no está disponible.</p></div></div>
            <a class="gh-home__button" href="{{ route('gestion-horarios.mi-disponibilidad') }}">Consultar disponibilidad <span aria-hidden="true">→</span></a>
        </section>
    @endif
    @if (auth()->check() && auth()->user()->hasRole('alumno', 'web'))
        <section class="gh-home__public" aria-labelledby="gh-horario-alumno">
            <div class="gh-home__public-content"><div><h2 id="gh-horario-alumno">Mi espacio académico</h2><p>Su horario personal estará disponible cuando se pueda verificar su matrícula vigente.</p></div></div>
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
        <a class="gh-home__button" href="{{ auth()->check() ? route('gestion-horarios.consulta-publicados') : route('gestion-horarios.publicados') }}">Ver horarios publicados <span aria-hidden="true">→</span></a>
    </section>
</div>
