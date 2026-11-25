@php $propio = old('_modal') === 'm-solicitud'; @endphp
<dialog id="m-solicitud" class="modal-unt" style="max-width: 46rem" aria-labelledby="m-solicitud-titulo" x-data="nuevaSolicitud()">
    <form method="POST" action="{{ route('bienestar.alumno.solicitudes.store') }}" enctype="multipart/form-data" @submit="enviar($event)">
        @csrf
        <input type="hidden" name="_modal" value="m-solicitud">
        <input type="hidden" name="_accion" value="">
        <header>
            <div><h2 id="m-solicitud-titulo">Nueva solicitud</h2><p x-text="paso === 1 ? 'Paso 1 de 2: elige el trámite' : 'Paso 2 de 2: adjunta los documentos'"></p></div>
            <button type="button" class="modal-unt-cerrar" data-cerrar-modal aria-label="Cerrar ventana"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="modal-unt-cuerpo">
            @if ($errors->any() && $propio)
                <ul class="aviso aviso-error" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach<li>Vuelve a adjuntar los documentos: el navegador no los conserva.</li></ul>
            @endif
            <p x-show="error" x-text="error" class="aviso aviso-error" role="alert" style="display: none"></p>
            <div x-show="paso === 1" x-ref="datos" class="grid gap-4">
                <label class="campo">Trámite *
                    <select name="id_tipo_solicitud" x-model="idTramite" @change="error = ''" required>
                        <option value="">Selecciona un trámite</option>
                        @foreach ($tramites as $t)<option value="{{ $t['id_tipo_solicitud'] }}">{{ $t['nombre'] }}</option>@endforeach
                    </select>
                </label>
                <p x-show="tramite?.descripcion" x-text="tramite?.descripcion" class="nota" style="display: none"></p>
                <label class="campo">¿Por qué lo solicitas? *
                    <textarea name="motivo" rows="4" required minlength="10" maxlength="1000">{{ $propio ? old('motivo') : '' }}</textarea>
                    <small>Entre 10 y 1000 caracteres.</small>
                </label>
            </div>
            <div x-show="paso === 2" class="grid gap-3" style="display: none">
                <div class="avance" role="status"><span x-text="listos + ' de ' + obligatorios + ' obligatorios listos'"></span><progress :value="listos" :max="obligatorios || 1"></progress></div>
                <template x-for="r in requisitos" :key="r.id_requisito">
                    <label class="archivo" :class="{ 'archivo-listo': elegidos[r.id_requisito] }">
                        <span>
                            <b x-text="r.nombre + (r.es_obligatorio ? ' *' : '')"></b>
                            <small x-text="r.descripcion"></small>
                            <small x-text="r.formatos_permitidos.toUpperCase().replaceAll(',', ', ') + ', máx. ' + r.tamano_maximo_mb + ' MB' + (r.es_obligatorio ? '' : ' (opcional)')"></small>
                            <a x-show="r.plantilla" :href="r.plantilla" class="enlace" download @click.stop>Descargar plantilla</a>
                        </span>
                        <input type="file" :name="'archivos[' + r.id_requisito + ']'" :accept="extensiones(r).map((e) => '.' + e).join(',')" @change="elegir(r, $event.target)">
                    </label>
                </template>
                <p x-show="! requisitos.length" class="vacio">Este trámite no pide documentos.</p>
            </div>
        </div>
        <footer>
            <button type="button" class="btn-unt-outline" x-show="paso === 1" data-cerrar-modal>Cancelar</button>
            <button type="button" class="btn-unt-outline" x-show="paso === 2" @click="paso = 1; error = ''" style="display: none">Atrás</button>
            <button type="button" class="btn-unt-primary" x-show="paso === 1" @click="continuar()">Continuar</button>
            <button type="submit" class="btn-unt-primary" x-show="paso === 2" style="display: none">Enviar solicitud</button>
        </footer>
    </form>
</dialog>
<script>
    function nuevaSolicitud() {
        const tramites = @json($tramites);
        return {
            paso: 1, error: '', elegidos: {}, idTramite: @json($propio ? (string) old('id_tipo_solicitud') : ''),
            init() { this.$watch('idTramite', () => { this.elegidos = {}; }); },
            get tramite() { return tramites.find((t) => t.id_tipo_solicitud == this.idTramite) || null; },
            get requisitos() { return this.tramite?.requisitos || []; },
            get obligatorios() { return this.requisitos.filter((r) => r.es_obligatorio).length; },
            get listos() { return this.requisitos.filter((r) => r.es_obligatorio && this.elegidos[r.id_requisito]).length; },
            extensiones(r) {
                const e = r.formatos_permitidos.toLowerCase().split(',').map((f) => f.trim());
                return e.includes('jpg') ? [...e, 'jpeg'] : e;
            },
            elegir(r, campo) {
                const archivo = campo.files[0];
                this.error = '';
                if (archivo && !this.extensiones(r).includes(archivo.name.split('.').pop().toLowerCase())) this.error = `«${r.nombre}» solo admite ${r.formatos_permitidos.toUpperCase()}.`;
                else if (archivo && archivo.size > r.tamano_maximo_mb * 1048576) this.error = `«${r.nombre}» supera los ${r.tamano_maximo_mb} MB.`;
                if (this.error) campo.value = '';
                this.elegidos = { ...this.elegidos, [r.id_requisito]: campo.files.length > 0 };
            },
            continuar() {
                for (const campo of this.$refs.datos.querySelectorAll('select, textarea')) if (!campo.reportValidity()) return;
                this.paso = 2;
            },
            enviar(e) {
                const falta = this.requisitos.find((r) => r.es_obligatorio && !this.elegidos[r.id_requisito]);
                if (this.paso === 1 || falta) {
                    e.preventDefault();
                    if (this.paso === 1) this.continuar();
                    else this.error = `Falta adjuntar «${falta.nombre}».`;
                }
            },
        };
    }
</script>
