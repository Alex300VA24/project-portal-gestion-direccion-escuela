<x-app-layout>
    @php
        // Se conservan los parámetros y las rutas que ya utiliza el controlador.
        $periodoActual = old('periodo', request('periodo', $periodo ?? ''));
        $modalidadActual = old('modalidad', request('modalidad', 'ciclo'));
        $modalidadActual = in_array($modalidadActual, ['ciclo', 'ambiente', 'docente'], true) ? $modalidadActual : 'ciclo';
        $valorActual = old('valor', request('valor', ''));
        $seccionActual = old('seccion', request('seccion', ''));
    @endphp

    <style>
        .gh-pub { min-height: 100%; padding: 2rem 1rem 4rem; background: var(--portal-canvas, #f4f4ef); color: var(--portal-ink, #141a33); font-family: 'Fira Sans', sans-serif; }
        .gh-pub * { box-sizing: border-box; }
        .gh-pub__wrap { max-width: 1160px; margin: 0 auto; }
        .gh-pub__breadcrumb { display: flex; gap: .6rem; align-items: center; margin: 0 0 1.25rem; color: #586a82; font-size: .9rem; }
        .gh-pub__breadcrumb a { color: #2251a4; text-decoration: none; font-weight: 650; }
        .gh-pub__breadcrumb a:hover { text-decoration: underline; }
        .gh-pub__hero { display: flex; justify-content: space-between; align-items: center; gap: 1.75rem; padding: 2.3rem 2.5rem; border-radius: 24px; background: linear-gradient(115deg, #102b55, #164c80 70%, #166a8c); color: white; box-shadow: 0 20px 44px rgba(16, 43, 85, .14); }
        .gh-pub__eyebrow { display: inline-flex; align-items: center; gap: .45rem; padding: .42rem .72rem; border: 1px solid rgba(255,255,255,.28); border-radius: 999px; font-size: .76rem; font-weight: 750; text-transform: uppercase; letter-spacing: .07em; background: rgba(255,255,255,.1); }
        .gh-pub__eyebrow::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #82e6ce; }
        .gh-pub__hero h1 { font-size: clamp(1.7rem, 3vw, 2.45rem); font-weight: 800; line-height: 1.14; letter-spacing: -.035em; margin: 1rem 0 .72rem; }
        .gh-pub__hero p { max-width: 590px; line-height: 1.65; color: #dae9fa; margin: 0; }
        .gh-pub__hero-icon { width: 120px; height: 120px; flex: none; display: grid; place-items: center; border-radius: 27px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.11); transform: rotate(5deg); }
        .gh-pub__hero-icon svg { width: 72px; height: 72px; fill: none; stroke: #c7e9ff; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
        .gh-pub__layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(275px, .85fr); align-items: start; gap: 1.25rem; margin-top: 1.5rem; }
        .gh-pub__card { background: white; border: 1px solid #e3eaf2; border-radius: 18px; padding: 1.65rem; box-shadow: 0 10px 34px rgba(25, 56, 94, .045); }
        .gh-pub__card-top { display: flex; align-items: flex-start; gap: .82rem; margin-bottom: 1.4rem; }
        .gh-pub__number { width: 38px; height: 38px; flex: none; display: grid; place-items: center; background: #edf4ff; color: #1f55a5; border-radius: 12px; font-weight: 800; }
        .gh-pub__card h2 { font-size: 1.24rem; font-weight: 780; margin: .06rem 0 .3rem; line-height: 1.3; }
        .gh-pub__muted { color: #60718a; font-size: .9rem; line-height: 1.5; margin: 0; }
        .gh-pub__fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .gh-pub__field { display: flex; flex-direction: column; min-width: 0; gap: .45rem; }
        .gh-pub__field--full { grid-column: 1 / -1; }
        .gh-pub__label { display: block; font-size: .88rem; font-weight: 700; color: #253b57; }
        .gh-pub__control { width: 100%; min-height: 45px; border-radius: 10px; border: 1px solid #cdd8e7; background: white; padding: .63rem .8rem; color: #192d47; font: inherit; transition: border-color .15s, box-shadow .15s; }
        .gh-pub__control::placeholder { color: #8493a8; }
        .gh-pub__control:focus { outline: none; border-color: #316ecc; box-shadow: 0 0 0 3px rgba(49,110,204,.15); }
        .gh-pub__hint { color: #64758d; font-size: .79rem; line-height: 1.35; }
        .gh-pub__actions { display: flex; gap: .7rem; flex-wrap: wrap; align-items: center; margin-top: 1.35rem; }
        .gh-pub__button { display: inline-flex; align-items: center; justify-content: center; gap: .55rem; min-height: 44px; border: 1px solid transparent; border-radius: 10px; padding: .7rem 1.1rem; background: #1c56ab; color: white; text-decoration: none; font: inherit; font-weight: 750; cursor: pointer; transition: background .15s, transform .15s; }
        .gh-pub__button:hover { background: #123f85; transform: translateY(-1px); }
        .gh-pub__button--light { background: white; color: #28548d; border-color: #d1dbea; }
        .gh-pub__button--light:hover { background: #f0f5fd; color: #153e79; }
        .gh-pub__button svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
        .gh-pub__error { color: #a32732; font-size: .82rem; margin: .1rem 0 0; }
        .gh-pub__results { margin-top: 1.5rem; }
        .gh-pub__results-heading { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; }
        .gh-pub__results-heading h2 { margin: 0 0 .3rem; }
        .gh-pub__pill { display: inline-flex; align-items: center; padding: .43rem .75rem; border-radius: 999px; background: #e8f7f2; color: #126247; font-weight: 750; font-size: .8rem; white-space: nowrap; }
        .gh-pub__table-scroll { overflow-x: auto; border: 1px solid #e5ebf3; border-radius: 12px; }
        .gh-pub__sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        .gh-pub__table { width: 100%; border-collapse: collapse; text-align: left; min-width: 620px; }
        .gh-pub__table thead { background: #f3f7fb; }
        .gh-pub__table th { padding: .94rem 1rem; font-size: .77rem; text-transform: uppercase; letter-spacing: .055em; color: #52677f; font-weight: 800; }
        .gh-pub__table td { padding: 1.05rem 1rem; border-top: 1px solid #edf1f6; font-size: .93rem; vertical-align: middle; }
        .gh-pub__table tbody tr:hover { background: #fafcff; }
        .gh-pub__version { display: inline-flex; border-radius: 8px; padding: .3rem .55rem; background: #edf3ff; color: #2453a0; font-weight: 760; font-size: .83rem; }
        .gh-pub__empty { text-align: center; padding: 2rem 1rem; background: #fafcff; border: 1px dashed #cad8e8; border-radius: 14px; }
        .gh-pub__empty svg { width: 45px; height: 45px; margin: 0 auto .7rem; fill: none; stroke: #6e8bb4; stroke-width: 1.5; }
        .gh-pub__empty h3 { font-size: 1.04rem; font-weight: 750; margin: 0 0 .45rem; }
        .gh-pub__empty p { margin: 0 0 1rem; color: #66768b; }
        .gh-pub__pagination { margin-top: 1rem; }
        .gh-pub :is(a, button, input, select):focus-visible { outline: 3px solid #83aeec; outline-offset: 2px; }
        .gh-pub__hero { background: var(--portal-navy, #17224d); }
        .gh-pub__button { background: var(--portal-green, #0d7a50); }
        .gh-pub__button:hover { background: var(--portal-green-dark, #09613f); }
        .gh-pub__button--light { background: var(--portal-surface, #fff); color: var(--portal-ink, #141a33); }
        .gh-pub__card { background: var(--portal-surface, #fff); border-color: var(--portal-border, #dfe3ea); }
        .gh-pub__breadcrumb a, .gh-pub__label, .gh-pub__card h2 { color: var(--portal-ink, #141a33); }
        .gh-pub__muted, .gh-pub__hint { color: var(--portal-muted, #4a5170); }
        .gh-pub__control { border-color: var(--portal-border, #dfe3ea); }
        .gh-pub__number, .gh-pub__version { font-family: 'Fira Code', monospace; }
        .gh-pub__error { color: var(--portal-danger, #a11d12); }
        .gh-pub :is(a, button, input, select):focus-visible { outline-color: var(--portal-focus, #f2a900); }
        @@media (max-width: 790px) { .gh-pub__layout { grid-template-columns: 1fr; } .gh-pub__hero { padding: 1.8rem; } .gh-pub__hero-icon { width: 85px; height: 85px; border-radius: 18px; } .gh-pub__hero-icon svg { width: 52px; height: 52px; } }
        @@media (max-width: 540px) { .gh-pub { padding: 1.1rem .75rem 2.5rem; } .gh-pub__card { padding: 1.15rem; } .gh-pub__hero { padding: 1.45rem; } .gh-pub__hero-icon { display: none; } .gh-pub__fields { grid-template-columns: 1fr; } .gh-pub__field--full { grid-column: auto; } .gh-pub__actions .gh-pub__button { flex: 1; } }
    </style>

    <main class="gh-pub">
        <div class="gh-pub__wrap">
            <nav class="gh-pub__breadcrumb" aria-label="Ruta actual">
                <a href="{{ route('gestion-horarios.index') }}">Gestión de Horarios</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Horarios publicados</span>
            </nav>

            <header class="gh-pub__hero">
                <div>
                    <span class="gh-pub__eyebrow">Consulta académica</span>
                    <h1>Horarios publicados</h1>
                    <p>Encuentre las clases programadas por ciclo, aula o docente y consulte las versiones oficialmente publicadas.</p>
                </div>
                <div class="gh-pub__hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 80 80"><rect x="11" y="14" width="58" height="54" rx="7"/><path d="M11 31h58M26 9v12M54 9v12M22 44h9M40 44h9M22 56h9M40 56h9"/></svg>
                </div>
            </header>

            <div class="gh-pub__layout">
                <section class="gh-pub__card" aria-labelledby="consulta-semanal">
                    <div class="gh-pub__card-top">
                        <span class="gh-pub__number" aria-hidden="true">01</span>
                        <div>
                            <h2 id="consulta-semanal">Consultar horario semanal</h2>
                            <p class="gh-pub__muted">Seleccione cómo desea buscar las clases publicadas.</p>
                        </div>
                    </div>

                    @if (! $periodoActual)
                        <p class="gh-pub__muted" role="status">Seleccione un período para cargar sus ciclos, ambientes y docentes.</p>
                    @elseif ($catalogos['ciclos']->isEmpty() && $catalogos['ambientes']->isEmpty() && $catalogos['docentes']->isEmpty())
                        <p class="gh-pub__muted" role="status">Este período no tiene actividades publicadas disponibles para consultar.</p>
                    @endif

                    <form method="get" action="{{ route('gestion-horarios.semana-publicada') }}"
                          x-data="{ modalidad: @js($modalidadActual), ciclo: @js((string) $valorActual), seccion: @js($seccionActual), enviando: false }" @submit="enviando = true">
                        <div class="gh-pub__fields">
                            <div class="gh-pub__field">
                                <label class="gh-pub__label" for="semana-periodo">Período académico</label>
                                <select class="gh-pub__control" id="semana-periodo" name="periodo" required
                                    @change="window.location.href = @js(route('gestion-horarios.consulta-publicados')) + '?periodo=' + encodeURIComponent($event.target.value) + '&modalidad=' + encodeURIComponent(modalidad)">
                                    <option value="">Seleccione un período</option>
                                    @foreach ($periodos as $p)
                                        <option value="{{ $p->id_periodo }}" @selected((string) $periodoActual === (string) $p->id_periodo)>{{ $p->codigo }}</option>
                                    @endforeach
                                </select>
                                <span class="gh-pub__hint">Solo se muestran períodos con publicaciones.</span>
                            </div>
                            <div class="gh-pub__field">
                                <label class="gh-pub__label" for="modalidad">Consultar por</label>
                                <select class="gh-pub__control" id="modalidad" name="modalidad" x-model="modalidad">
                                    <option value="ciclo" @selected($modalidadActual === 'ciclo')>Ciclo académico</option>
                                    <option value="ambiente" @selected($modalidadActual === 'ambiente')>Aula o laboratorio</option>
                                    <option value="docente" @selected($modalidadActual === 'docente')>Docente</option>
                                </select>
                            </div>
                            <div class="gh-pub__field gh-pub__field--full" x-show="modalidad === 'ciclo'">
                                <label class="gh-pub__label" for="ciclo">Ciclo académico</label>
                                <select class="gh-pub__control" id="ciclo" name="valor" x-model="ciclo" @change="seccion = ''" :disabled="modalidad !== 'ciclo'" required>
                                    <option value="">Seleccione un ciclo</option>
                                    @foreach ($catalogos['ciclos'] as $ciclo)
                                        <option value="{{ $ciclo }}">Ciclo {{ $ciclo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="gh-pub__field gh-pub__field--full" x-show="modalidad === 'ambiente'" x-cloak>
                                <label class="gh-pub__label" for="ambiente">Aula o laboratorio</label>
                                <select class="gh-pub__control" id="ambiente" name="valor" :disabled="modalidad !== 'ambiente'" required>
                                    <option value="">Seleccione un ambiente</option>
                                    @foreach ($catalogos['ambientes'] as $ambiente)
                                        <option value="{{ $ambiente['id'] }}" @selected($modalidadActual === 'ambiente' && (string) $valorActual === (string) $ambiente['id'])>{{ $ambiente['codigo'] }} · {{ $ambiente['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="gh-pub__field gh-pub__field--full" x-show="modalidad === 'docente'" x-cloak>
                                <label class="gh-pub__label" for="docente">Docente</label>
                                <select class="gh-pub__control" id="docente" name="valor" :disabled="modalidad !== 'docente'" required>
                                    <option value="">Seleccione un docente</option>
                                    @foreach ($catalogos['docentes'] as $docente)
                                        <option value="{{ $docente['id'] }}" @selected($modalidadActual === 'docente' && (string) $valorActual === (string) $docente['id'])>{{ $docente['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="gh-pub__field gh-pub__field--full" x-show="modalidad === 'ciclo'">
                                <label class="gh-pub__label" for="seccion">Sección <span style="font-weight:400;color:#718098">(opcional)</span></label>
                                <select class="gh-pub__control" id="seccion" name="seccion" x-model="seccion" :disabled="modalidad !== 'ciclo' || ! ciclo">
                                    <option value="">Todas las secciones</option>
                                    @foreach ($catalogos['secciones'] as $seccion)
                                        <option value="{{ $seccion['codigo'] }}" x-show="ciclo === @js((string) $seccion['ciclo'])" :disabled="ciclo !== @js((string) $seccion['ciclo'])">{{ $seccion['codigo'] }}</option>
                                    @endforeach
                                </select>
                                <span class="gh-pub__hint">Déjelo vacío para consultar todas las secciones del ciclo.</span>
                            </div>
                        </div>
                        <div class="gh-pub__actions">
                            <button class="gh-pub__button" type="submit" :disabled="enviando || ! $el.form.periodo.value">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
                                <span x-text="enviando ? 'Consultando…' : 'Ver horario semanal'">Ver horario semanal</span>
                            </button>
                        </div>
                        @error('modalidad') <p class="gh-pub__error" role="alert">Seleccione una modalidad válida.</p> @enderror
                        @error('valor') <p class="gh-pub__error" role="alert">{{ $message }}</p> @enderror
                        @error('seccion') <p class="gh-pub__error" role="alert">{{ $message }}</p> @enderror
                    </form>
                </section>

                <section class="gh-pub__card" aria-labelledby="buscar-versiones">
                    <div class="gh-pub__card-top">
                        <span class="gh-pub__number" aria-hidden="true">02</span>
                        <div>
                            <h2 id="buscar-versiones">Buscar versiones</h2>
                            <p class="gh-pub__muted">Revise qué horarios se han publicado en cada período.</p>
                        </div>
                    </div>
                    <form method="get" action="{{ route('gestion-horarios.consulta-publicados') }}">
                        <div class="gh-pub__field">
                            <label class="gh-pub__label" for="periodo">Período académico</label>
                            <select class="gh-pub__control" id="periodo" name="periodo" aria-describedby="periodo-ayuda">
                                <option value="">Todos los períodos</option>
                                @foreach ($periodos as $p)
                                    <option value="{{ $p->id_periodo }}" @selected((string) $periodoActual === (string) $p->id_periodo)>{{ $p->codigo }}</option>
                                @endforeach
                            </select>
                            <span class="gh-pub__hint" id="periodo-ayuda">Déjelo vacío para mostrar todas las versiones publicadas.</span>
                        </div>
                        @error('periodo') <p class="gh-pub__error" role="alert">Introduzca un identificador de período válido.</p> @enderror
                        <div class="gh-pub__actions">
                            <button class="gh-pub__button" type="submit">Buscar versiones</button>
                            <a class="gh-pub__button gh-pub__button--light" href="{{ route('gestion-horarios.consulta-publicados') }}">Limpiar</a>
                        </div>
                    </form>
                </section>
            </div>

            <section class="gh-pub__card gh-pub__results" aria-labelledby="resultados-publicados">
                <div class="gh-pub__results-heading">
                    <div>
                        <h2 id="resultados-publicados">Versiones publicadas</h2>
                        <p class="gh-pub__muted">Historial de versiones disponibles para consulta.</p>
                    </div>
                    <span class="gh-pub__pill">Solo publicadas</span>
                </div>

                @if ($horarios->isEmpty())
                    <div class="gh-pub__empty" role="status">
                        <svg viewBox="0 0 48 48" aria-hidden="true"><rect x="7" y="9" width="34" height="32" rx="5"/><path d="M7 19h34M16 5v9M32 5v9M18 29h12"/></svg>
                        <h3>No se encontraron horarios publicados</h3>
                        <p>Pruebe con otro período o consulte todas las versiones disponibles.</p>
                        <a class="gh-pub__button gh-pub__button--light" href="{{ route('gestion-horarios.consulta-publicados') }}">Ver todos los períodos</a>
                    </div>
                @else
                    <div class="gh-pub__table-scroll">
                        <table class="gh-pub__table">
                            <caption class="gh-pub__sr-only">Versiones de horarios publicados</caption>
                            <thead><tr>
                                <th scope="col">Período</th>
                                <th scope="col">Ámbito</th>
                                <th scope="col">Versión</th>
                                <th scope="col">Fecha de publicación</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($horarios as $horario)
                                    <tr>
                                        <td><strong>{{ $periodos->firstWhere('id_periodo', $horario->id_periodo)?->codigo ?? 'Período no disponible' }}</strong></td>
                                        <td>{{ $horario->ambito }}</td>
                                        <td><span class="gh-pub__version">V{{ $horario->version }}</span></td>
                                        <td>{{ $horario->publicado_en ? \Illuminate\Support\Carbon::parse($horario->publicado_en)->format('d/m/Y H:i') : 'Sin fecha' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="gh-pub__pagination">{{ $horarios->withQueryString()->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</x-app-layout>
