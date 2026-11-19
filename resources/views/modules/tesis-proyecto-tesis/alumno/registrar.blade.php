@extends('modules.tesis-proyecto-tesis.layout')

@php
    $fila = fn (array $campos) => array_fill_keys($campos, '');
    $inicial = [
        'titulo' => old('titulo', ''),
        'coautor' => old('coautor_codigo', ''),
        'actividades' => old('actividades') ?: [$fila(['descripcion', 'fecha_inicio', 'fecha_fin', 'horas'])],
        'partidas' => old('partidas') ?: [$fila(['codigo', 'descripcion', 'monto'])],
        'recursos' => old('recursos') ?: [['tipo' => 'Bienes', 'descripcion' => '', 'cantidad' => '']],
        'colaboradores' => old('colaboradores') ?: [],
        'urlCoautor' => route('tesis-proyecto-tesis.alumno.coautor'),
        'maxPalabras' => \App\Modules\TesisProyectoTesis\Support\Parametros::TITULO_MAX_PALABRAS,
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
                get total() { return this.partidas.reduce((s, p) => s + (parseFloat(p.monto) || 0), 0).toFixed(2); },
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
                    <select id="diseno" name="diseno" class="tz-select" required aria-invalid="{{ $err('diseno') }}">
                        <option value="">Selecciona</option>
                        @foreach ($parametros['disenos'] as $v) <option value="{{ $v }}" @selected(old('diseno') === $v)>{{ $v }}</option> @endforeach
                    </select>
                    @error('diseno') <span class="tz-error">{{ $message }}</span> @enderror
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
                    <input id="lugar_ejecucion" name="lugar_ejecucion" class="tz-input" value="{{ old('lugar_ejecucion') }}" maxlength="200" required aria-invalid="{{ $err('lugar_ejecucion') }}">
                    @error('lugar_ejecucion') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="institucion">Institución *</label>
                    <input id="institucion" name="institucion" class="tz-input" value="{{ old('institucion') }}" maxlength="200" required aria-invalid="{{ $err('institucion') }}">
                    @error('institucion') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="duracion_meses">Duración (meses) *</label>
                    <input id="duracion_meses" name="duracion_meses" type="number" min="1" max="36" class="tz-input" value="{{ old('duracion_meses') }}" required aria-invalid="{{ $err('duracion_meses') }}">
                    @error('duracion_meses') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="financiamiento">Financiamiento *</label>
                    <select id="financiamiento" name="financiamiento" class="tz-select" required aria-invalid="{{ $err('financiamiento') }}">
                        <option value="">Selecciona</option>
                        @foreach ($parametros['financiamientos'] as $v) <option value="{{ $v }}" @selected(old('financiamiento') === $v)>{{ $v }}</option> @endforeach
                    </select>
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
            <div class="tz-panel-head"><h2><span class="tz-step">3</span>Cronograma de trabajo *</h2></div>
            <div class="tz-table-wrap">
                <table class="tz-table">
                    <thead><tr><th scope="col">Etapa</th><th scope="col">Inicio</th><th scope="col">Término</th><th scope="col">Horas/sem.</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <template x-for="(a, i) in actividades" :key="i">
                            <tr>
                                <td><input class="tz-input" :name="`actividades[${i}][descripcion]`" x-model="a.descripcion" maxlength="300" aria-label="Etapa"></td>
                                <td><input class="tz-input" type="date" :name="`actividades[${i}][fecha_inicio]`" x-model="a.fecha_inicio" aria-label="Inicio"></td>
                                <td><input class="tz-input" type="date" :name="`actividades[${i}][fecha_fin]`" x-model="a.fecha_fin" aria-label="Término"></td>
                                <td style="width: 110px"><input class="tz-input" type="number" min="1" max="60" :name="`actividades[${i}][horas]`" x-model="a.horas" aria-label="Horas por semana"></td>
                                <td><button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="quitar('actividades', i)" x-show="actividades.length > 1">Quitar</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="tz-panel-body">
                <button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="agregar('actividades', { descripcion: '', fecha_inicio: '', fecha_fin: '', horas: '' })">+ Agregar etapa</button>
                @error('actividades') <span class="tz-error">{{ $message }}</span> @enderror
            </div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2><span class="tz-step">4</span>Presupuesto por partidas</h2><span class="tz-muted">Total: S/ <strong x-text="total"></strong></span></div>
            <div class="tz-table-wrap">
                <table class="tz-table">
                    <thead><tr><th scope="col">Partida</th><th scope="col">Concepto</th><th scope="col">Monto (S/)</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <template x-for="(p, i) in partidas" :key="i">
                            <tr>
                                <td style="width: 140px"><input class="tz-input" :name="`partidas[${i}][codigo]`" x-model="p.codigo" maxlength="20" placeholder="2.3.1 5" aria-label="Código de partida"></td>
                                <td><input class="tz-input" :name="`partidas[${i}][descripcion]`" x-model="p.descripcion" maxlength="200" aria-label="Concepto"></td>
                                <td style="width: 150px"><input class="tz-input" type="number" min="0" step="0.01" :name="`partidas[${i}][monto]`" x-model="p.monto" aria-label="Monto"></td>
                                <td><button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="quitar('partidas', i)">Quitar</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="tz-panel-body"><button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="agregar('partidas', { codigo: '', descripcion: '', monto: '' })">+ Agregar partida</button></div>
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2><span class="tz-step">5</span>Recursos y colaboradores</h2></div>
            <div class="tz-table-wrap">
                <table class="tz-table">
                    <thead><tr><th scope="col">Tipo</th><th scope="col">Detalle del recurso</th><th scope="col">Cantidad</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <template x-for="(r, i) in recursos" :key="i">
                            <tr>
                                <td style="width: 160px">
                                    <select class="tz-select" :name="`recursos[${i}][tipo]`" x-model="r.tipo" aria-label="Tipo de recurso">
                                        @foreach ($parametros['recursos'] as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                                    </select>
                                </td>
                                <td><input class="tz-input" :name="`recursos[${i}][descripcion]`" x-model="r.descripcion" maxlength="300" aria-label="Detalle"></td>
                                <td style="width: 120px"><input class="tz-input" type="number" min="0" step="0.01" :name="`recursos[${i}][cantidad]`" x-model="r.cantidad" aria-label="Cantidad"></td>
                                <td><button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="quitar('recursos', i)">Quitar</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="tz-panel-body"><button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="agregar('recursos', { tipo: 'Bienes', descripcion: '', cantidad: '' })">+ Agregar recurso</button></div>

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
