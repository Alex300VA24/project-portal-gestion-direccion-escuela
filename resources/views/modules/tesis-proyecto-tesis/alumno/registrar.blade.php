@extends('modules.tesis-proyecto-tesis.layout')

@php
    $fila = fn (array $campos) => array_fill_keys($campos, '');
    $filaRecurso = ['tipo' => 'Bienes', 'descripcion' => '', 'codigo' => '', 'unidad' => '', 'cantidad' => '', 'costo' => ''];
    $inicial = [
        'titulo' => old('titulo', ''),
        'coautor' => old('coautor_codigo', ''),
        'diseno' => old('diseno', ''),
        'financiamiento' => old('financiamiento', ''),
        'actividades' => old('actividades') ?: [$fila(['descripcion', 'fecha_inicio', 'fecha_fin', 'horas'])],
        'recursos' => old('recursos') ?: [$filaRecurso],
        'colaboradores' => old('colaboradores') ?: [],
        'urlCoautor' => route('tesis-proyecto-tesis.alumno.coautor'),
        'maxPalabras' => \App\Modules\TesisProyectoTesis\Support\Parametros::TITULO_MAX_PALABRAS,
        'maxMeses' => $parametros['maxMeses'],
        'diasPorMes' => \App\Modules\TesisProyectoTesis\Support\DuracionCronograma::DIAS_POR_MES,
        'ayudasFinanciamiento' => $parametros['financiamientos'],
        'filaRecurso' => $filaRecurso,
    ];
    $err = fn (string $campo) => $errors->has($campo) ? 'true' : 'false';
@endphp

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.alumno.panel'), 'a' => 'Inicio'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.alumno.panel') }}">Mi proyecto</a> › Registro</p>
            <h1>Registrar proyecto de tesis</h1>
            <p>Completa los datos generales del formato F1. El plan de investigación va en el documento adjunto. Los campos con * son obligatorios.</p>
        </div>
    </div>

    <script>
        window.tzF1 = function (d) {
            return {
                ...d,
                coautorInfo: null, buscando: false,
                init() { if (this.coautor.trim() !== '') this.buscarCoautor(); }, // al volver con errores, muestra de nuevo el estado del coautor
                get palabras() { return this.titulo.trim() === '' ? 0 : this.titulo.trim().split(/\s+/).length; },
                // Duración: del primer inicio al último término (misma fórmula que el servidor).
                get duracion() {
                    const fechas = this.actividades.filter(a => a.fecha_inicio && a.fecha_fin);
                    if (fechas.length === 0) return '';
                    const ini = Math.min(...fechas.map(a => Date.parse(a.fecha_inicio)));
                    const fin = Math.max(...fechas.map(a => Date.parse(a.fecha_fin)));
                    if (!(fin >= ini)) return '';
                    return Math.max(1, Math.ceil((Math.round((fin - ini) / 86400000) + 1) / this.diasPorMes));
                },
                subtotal(r) { return (parseFloat(r.cantidad) || 0) * (parseFloat(r.costo) || 0); },
                totalDe(tipo) { return this.recursos.filter(r => r.tipo === tipo).reduce((s, r) => s + this.subtotal(r), 0); },
                get totalGeneral() { return this.recursos.reduce((s, r) => s + this.subtotal(r), 0); },
                soles(n) { return 'S/ ' + (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                // «Otro código»: se vacía el campo para escribir el código a mano.
                elegirCodigo(r, e) { if (r.codigo === 'Otro código') { r.codigo = ''; e.target.placeholder = 'Escribe el código (p. ej. 2.3.1.99.1)'; } },
                agregar(lista, fila) { if (this[lista].length < 30) this[lista].push({ ...fila }); },
                quitar(lista, i) { this[lista].splice(i, 1); },
                async buscarCoautor() {
                    const codigo = this.coautor.trim();
                    this.coautorInfo = null;
                    if (!/^[A-Za-z0-9-]+$/.test(codigo)) { this.coautorInfo = { encontrado: false, mensaje: 'Ingresa un código válido.' }; return; }
                    this.buscando = true;
                    try {
                        const r = await fetch(this.urlCoautor + '?codigo=' + encodeURIComponent(codigo), { headers: { 'Accept': 'application/json' } });
                        this.coautorInfo = await r.json();
                    } catch (e) { this.coautorInfo = { encontrado: false, mensaje: 'No se pudo consultar. Intenta de nuevo.' }; }
                    this.buscando = false;
                },
                get coautorListo() { return !! (this.coautorInfo && this.coautorInfo.encontrado && this.coautorInfo.disponible); },
                quitarCoautor() {
                    this.coautor = '';
                    this.coautorInfo = null;
                    this.$nextTick(() => this.$refs.coautorCodigo.focus());
                },
            };
        };
    </script>

    <form class="tz-form" method="POST" action="{{ route('tesis-proyecto-tesis.alumno.proyecto.guardar') }}" enctype="multipart/form-data"
          x-data="tzF1(@js($inicial))" novalidate>
        @csrf

        <section class="tz-panel">
            <div class="tz-panel-head"><h2><span class="tz-step">1</span>Datos generales</h2></div>
            <div class="tz-panel-body tz-fields">
                <div class="tz-field is-wide">
                    <label for="titulo">Título de la investigación *</label>
                    <input id="titulo" name="titulo" class="tz-input" x-model="titulo" maxlength="300" required aria-invalid="{{ $err('titulo') }}" aria-describedby="titulo-ayuda">
                    <span id="titulo-ayuda" class="tz-hint">Indica la variable o solución, la unidad de análisis y el contexto. Máximo <span x-text="maxPalabras"></span> palabras (llevas <span x-text="palabras"></span>).</span>
                    <span class="tz-error" x-show="palabras > maxPalabras" x-cloak>El título supera el máximo de palabras.</span>
                    @error('titulo') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field is-wide">
                    <label for="resumen">Resumen *</label>
                    <textarea id="resumen" name="resumen" class="tz-textarea" maxlength="3000" required aria-invalid="{{ $err('resumen') }}">{{ old('resumen') }}</textarea>
                    @error('resumen') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="enfoque">Enfoque *</label>
                    <select id="enfoque" name="enfoque" class="tz-select" required aria-invalid="{{ $err('enfoque') }}">
                        <option value="">Selecciona</option>
                        @foreach ($parametros['enfoques'] as $v) <option value="{{ $v }}" @selected(old('enfoque') === $v)>{{ $v }}</option> @endforeach
                    </select>
                    @error('enfoque') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="tipo_investigacion">Tipo por el fin que persigue *</label>
                    <select id="tipo_investigacion" name="tipo_investigacion" class="tz-select" required aria-invalid="{{ $err('tipo_investigacion') }}">
                        <option value="">Selecciona</option>
                        @foreach ($parametros['tipos'] as $v) <option value="{{ $v }}" @selected(old('tipo_investigacion') === $v)>{{ $v }}</option> @endforeach
                    </select>
                    @error('tipo_investigacion') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="diseno">Diseño de contrastación *</label>
                    <select id="diseno" name="diseno" class="tz-select" x-model="diseno" required aria-invalid="{{ $err('diseno') }}">
                        <option value="">Selecciona</option>
                        @foreach ($parametros['disenos'] as $v) <option value="{{ $v }}" @selected(old('diseno') === $v)>{{ $v }}</option> @endforeach
                    </select>
                    @error('diseno') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field" x-show="diseno === @js($parametros['disenoOtro'])" x-cloak>
                    <label for="diseno_otro">Describe el diseño *</label>
                    <input id="diseno_otro" name="diseno_otro" class="tz-input" value="{{ old('diseno_otro') }}" maxlength="100" :required="diseno === @js($parametros['disenoOtro'])" :disabled="diseno !== @js($parametros['disenoOtro'])" aria-invalid="{{ $err('diseno_otro') }}">
                    @error('diseno_otro') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="linea">Línea de investigación *</label>
                    <select id="linea" name="linea" class="tz-select" required aria-invalid="{{ $err('linea') }}">
                        <option value="">Selecciona</option>
                        @foreach ($lineas as $l) <option value="{{ $l['codigo'] }}" @selected(old('linea') === $l['codigo'])>{{ $l['nombre'] }}</option> @endforeach
                    </select>
                    @error('linea') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="lugar_ejecucion">Lugar de ejecución *</label>
                    <input id="lugar_ejecucion" name="lugar_ejecucion" class="tz-input" value="{{ old('lugar_ejecucion') }}" maxlength="200" required aria-invalid="{{ $err('lugar_ejecucion') }}" aria-describedby="lugar-ayuda" placeholder="Trujillo, La Libertad">
                    <span id="lugar-ayuda" class="tz-hint">Ciudad o entidad donde se aplica la investigación (ej. Trujillo, La Libertad).</span>
                    @error('lugar_ejecucion') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="institucion">Institución *</label>
                    <input id="institucion" name="institucion" class="tz-input" value="{{ old('institucion', $parametros['institucion']) }}" maxlength="200" required aria-invalid="{{ $err('institucion') }}">
                    @error('institucion') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="duracion_meses">Duración (meses)</label>
                    <input id="duracion_meses" class="tz-input" :value="duracion" readonly aria-readonly="true" aria-describedby="duracion-ayuda" placeholder="Se calcula del cronograma">
                    <span id="duracion-ayuda" class="tz-hint">Se calcula sola: del primer inicio al último término del cronograma (máximo <span x-text="maxMeses"></span> meses).</span>
                </div>
                <div class="tz-field">
                    <label for="financiamiento">Financiamiento *</label>
                    <select id="financiamiento" name="financiamiento" class="tz-select" x-model="financiamiento" required aria-invalid="{{ $err('financiamiento') }}" aria-describedby="financiamiento-ayuda">
                        <option value="">Selecciona</option>
                        @foreach ($parametros['financiamientos'] as $v => $ayuda) <option value="{{ $v }}" @selected(old('financiamiento') === $v)>{{ $v }}</option> @endforeach
                    </select>
                    <ul id="financiamiento-ayuda" class="tz-hint tz-ayuda-lista">
                        @foreach ($parametros['financiamientos'] as $v => $ayuda)
                            <li :class="financiamiento === @js($v) && 'is-on'"><strong>{{ $v }}:</strong> {{ $ayuda }}</li>
                        @endforeach
                    </ul>
                    @error('financiamiento') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2><span class="tz-step">2</span>Integrantes</h2><span class="tz-muted tz-small">El proyecto puede tener 1 o 2 estudiantes. Ambos reciben el mismo resultado.</span></div>
            <div class="tz-panel-body" style="display: grid; gap: 14px">
                <div><span class="tz-status is-info">1</span> <strong>{{ $yo['nombre'] ?? '' }}</strong> (tú) <span class="tz-muted tz-small">· {{ $yo['codigo'] ?? '' }}</span></div>
                <div class="tz-field" style="max-width: 520px">
                    <label for="coautor_codigo">Código universitario del coautor (opcional)</label>
                    <div class="tz-actions">
                        <input id="coautor_codigo" name="coautor_codigo" class="tz-input" style="flex: 1" x-model="coautor" x-ref="coautorCodigo" maxlength="30" inputmode="numeric" aria-invalid="{{ $err('coautor_codigo') }}" aria-describedby="coautor-estado" @input="coautorInfo = null" @keydown.enter.prevent="buscarCoautor()">
                        <button type="button" class="tz-btn tz-btn-secondary" @click="buscarCoautor()" :disabled="buscando || coautor.trim() === ''">Buscar</button>
                    </div>
                    <div id="coautor-estado" role="status" aria-live="polite">
                        <template x-if="coautorListo">
                            <div class="tz-coautor" data-coautor="listo">
                                <span class="tz-status is-info">2</span>
                                <div>
                                    <strong x-text="coautorInfo.nombre"></strong> <span class="tz-muted tz-small">· <span x-text="coautorInfo.codigo"></span></span>
                                    <p class="tz-small">Se agregará como integrante 2 al registrar el proyecto. No necesitas pulsar otro botón: basta con <strong>Registrar proyecto</strong> al final del formulario.</p>
                                </div>
                                <button type="button" class="tz-btn tz-btn-secondary" @click="quitarCoautor()" :aria-label="'Quitar a ' + coautorInfo.nombre + ' como integrante 2'">Quitar</button>
                            </div>
                        </template>
                        <template x-if="coautorInfo && coautorInfo.encontrado && ! coautorInfo.disponible"><p class="tz-small"><strong x-text="coautorInfo.nombre"></strong> · <span class="tz-error" x-text="coautorInfo.mensaje"></span></p></template>
                        <template x-if="coautorInfo && ! coautorInfo.encontrado"><p class="tz-small tz-error" x-text="coautorInfo.mensaje"></p></template>
                    </div>
                    @error('coautor_codigo') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head">
                <h2><span class="tz-step">3</span>Cronograma de actividades *</h2>
                <span class="tz-muted tz-small">Duración: <strong x-text="duracion ? duracion + (duracion === 1 ? ' mes' : ' meses') : '—'"></strong></span>
            </div>
            <div class="tz-panel-body">
                <p class="tz-alert is-info tz-small" id="cronograma-ayuda">
                    Lista las actividades de la investigación, no las etapas del trámite (F1/F2). Por ejemplo: revisión bibliográfica, diseño, desarrollo,
                    análisis de datos, evaluación y redacción del informe. Para cada una indica inicio, término y cuántas horas por semana le dedicarás.
                </p>
            </div>
            <div class="tz-table-wrap">
                <table class="tz-table" aria-describedby="cronograma-ayuda">
                    <thead><tr><th scope="col">Actividad</th><th scope="col">Inicio</th><th scope="col">Término</th><th scope="col">Dedicación (h/sem.)</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <template x-for="(a, i) in actividades" :key="i">
                            <tr>
                                <td><input class="tz-input" :name="`actividades[${i}][descripcion]`" x-model="a.descripcion" maxlength="300" aria-label="Actividad" placeholder="Revisión bibliográfica"></td>
                                <td><input class="tz-input" type="date" :name="`actividades[${i}][fecha_inicio]`" x-model="a.fecha_inicio" aria-label="Inicio"></td>
                                <td><input class="tz-input" type="date" :name="`actividades[${i}][fecha_fin]`" x-model="a.fecha_fin" aria-label="Término"></td>
                                <td style="width: 150px"><input class="tz-input" type="number" min="1" max="60" :name="`actividades[${i}][horas]`" x-model="a.horas" aria-label="Dedicación en horas por semana"></td>
                                <td><button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="quitar('actividades', i)" x-show="actividades.length > 1">Quitar</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="tz-panel-body">
                <button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="agregar('actividades', { descripcion: '', fecha_inicio: '', fecha_fin: '', horas: '' })">+ Agregar actividad</button>
                @error('actividades') <span class="tz-error">{{ $message }}</span> @enderror
            </div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head">
                <h2><span class="tz-step">4</span>Recursos y presupuesto</h2>
                <span class="tz-muted tz-small">Total general: <strong x-text="soles(totalGeneral)"></strong></span>
            </div>
            <div class="tz-panel-body" style="display: grid; gap: 10px">
                <p class="tz-small tz-muted" id="presupuesto-ayuda">
                    Una fila por recurso. El código del clasificador de gastos se busca por código o concepto; si no está en la lista, elige «Otro código» y escríbelo.
                    Los bienes propios (por ejemplo, tu laptop) van con «–» y valor 0.
                </p>
                <div class="tz-personal">
                    <strong>Personal</strong> <span class="tz-muted tz-small">(se agrega solo, sin costo)</span>
                    <ul>
                        <li>{{ $yo['nombre'] ?? '' }} · estudiante</li>
                        <li x-show="coautorListo" x-cloak><span x-text="coautorInfo?.nombre"></span> · estudiante</li>
                        <li>Asesor · lo designa el Decanato por resolución</li>
                    </ul>
                </div>
            </div>
            <datalist id="tz-clasificador">
                @foreach ($parametros['clasificador'] as $codigo => $concepto) <option value="{{ $codigo }} · {{ $concepto }}"></option> @endforeach
                <option value="Otro código"></option>
            </datalist>
            <datalist id="tz-unidades">
                @foreach ($parametros['unidades'] as $u) <option value="{{ $u }}"></option> @endforeach
            </datalist>
            <div class="tz-table-wrap">
                <table class="tz-table tz-presupuesto" aria-describedby="presupuesto-ayuda">
                    <thead><tr><th scope="col">Tipo</th><th scope="col">Descripción</th><th scope="col">Código del clasificador</th><th scope="col">Unidad</th><th scope="col">Cantidad</th><th scope="col">Valor unit. (S/)</th><th scope="col">Subtotal</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <template x-for="(r, i) in recursos" :key="i">
                            <tr>
                                <td style="min-width: 150px">
                                    <select class="tz-select" :name="`recursos[${i}][tipo]`" x-model="r.tipo" aria-label="Tipo de recurso">
                                        @foreach ($parametros['recursos'] as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 200px"><input class="tz-input" :name="`recursos[${i}][descripcion]`" x-model="r.descripcion" maxlength="200" aria-label="Descripción del recurso" placeholder="Papel bond A4"></td>
                                <td style="min-width: 260px"><input class="tz-input" list="tz-clasificador" :name="`recursos[${i}][codigo]`" x-model="r.codigo" @change="elegirCodigo(r, $event)" maxlength="80" aria-label="Código del clasificador de gastos" placeholder="Busca por código o concepto"></td>
                                <td style="min-width: 140px"><input class="tz-input" list="tz-unidades" :name="`recursos[${i}][unidad]`" x-model="r.unidad" maxlength="40" aria-label="Unidad de medida" placeholder="Unidad"></td>
                                <td style="width: 110px"><input class="tz-input" type="number" min="0.01" step="0.01" :name="`recursos[${i}][cantidad]`" x-model="r.cantidad" aria-label="Cantidad"></td>
                                <td style="width: 130px"><input class="tz-input" type="number" min="0" step="0.01" :name="`recursos[${i}][costo]`" x-model="r.costo" aria-label="Valor unitario en soles"></td>
                                <td class="tz-num" x-text="soles(subtotal(r))"></td>
                                <td><button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="quitar('recursos', i)">Quitar</button></td>
                            </tr>
                        </template>
                        <tr x-show="recursos.length === 0"><td colspan="8" class="tz-muted">Sin recursos. Agrega al menos los materiales y servicios que usarás.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr><th scope="row" colspan="6">Total de bienes</th><td class="tz-num" x-text="soles(totalDe('Bienes'))"></td><td></td></tr>
                        <tr><th scope="row" colspan="6">Total de servicios</th><td class="tz-num" x-text="soles(totalDe('Servicios'))"></td><td></td></tr>
                        <tr><th scope="row" colspan="6">Total general</th><td class="tz-num"><strong x-text="soles(totalGeneral)"></strong></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="tz-panel-body">
                <button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="agregar('recursos', { ...filaRecurso })">+ Agregar recurso</button>
                @error('recursos') <span class="tz-error">{{ $message }}</span> @enderror
            </div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2><span class="tz-step">5</span>Colaboradores (opcional)</h2></div>
            <div class="tz-table-wrap">
                <table class="tz-table">
                    <thead><tr><th scope="col">Colaborador</th><th scope="col">Institución</th><th scope="col">Qué aporta</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <template x-for="(c, i) in colaboradores" :key="i">
                            <tr>
                                <td><input class="tz-input" :name="`colaboradores[${i}][nombres]`" x-model="c.nombres" maxlength="200" aria-label="Nombre del colaborador"></td>
                                <td><input class="tz-input" :name="`colaboradores[${i}][institucion]`" x-model="c.institucion" maxlength="180" aria-label="Institución"></td>
                                <td><input class="tz-input" :name="`colaboradores[${i}][rol]`" x-model="c.rol" maxlength="100" aria-label="Qué aporta"></td>
                                <td><button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="quitar('colaboradores', i)">Quitar</button></td>
                            </tr>
                        </template>
                        <tr x-show="colaboradores.length === 0"><td colspan="4" class="tz-muted">Sin colaboradores.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="tz-panel-body"><button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="agregar('colaboradores', { nombres: '', institucion: '', rol: '' })">+ Agregar colaborador</button></div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2><span class="tz-step">6</span>Documento F1 *</h2></div>
            <div class="tz-panel-body tz-field">
                <label for="documento">Proyecto en formato F1 (PDF, máximo 10 MB)</label>
                <input id="documento" name="documento" type="file" accept="application/pdf,.pdf" class="tz-input" required aria-invalid="{{ $err('documento') }}">
                @error('documento') <span class="tz-error">{{ $message }}</span> @enderror
            </div>
        </section>

        <div class="tz-actions">
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.alumno.panel') }}">Cancelar</a>
            <button type="submit" class="tz-btn tz-btn-primary" :disabled="palabras > maxPalabras">Registrar proyecto</button>
        </div>
    </form>
@endsection
