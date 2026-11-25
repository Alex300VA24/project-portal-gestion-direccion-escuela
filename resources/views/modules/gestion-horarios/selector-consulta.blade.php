@php($items = collect($opciones)->map(fn($o)=>['valor'=>(string)$o['id'],'nombre'=>$o['nombre'],'codigo'=>(string)($o['codigo']??'')])->values()->all())
<div class="gh-horario-combo" x-id="['consulta-input','consulta-lista','consulta-opcion']" x-data="{
    items:@js($items), valor:@js((string)$valorActual), consulta:'', abierto:false, activo:0,
    normalizar(v){return String(v).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()},
    get filtradas(){return this.items.filter(i=>this.normalizar(this.consulta).split(/\s+/).every(p=>this.normalizar(i.nombre+' '+i.codigo).includes(p)))},
    get etiqueta(){return this.items.find(i=>i.valor===this.valor)?.nombre||''},
    abrir(){if(!this.abierto){this.consulta='';this.activo=0;this.abierto=true}},
    elegir(i){if(i){this.valor=i.valor;this.abierto=false;this.consulta=''}},
    mover(n){this.abrir();this.activo=Math.max(0,Math.min(this.filtradas.length-1,this.activo+n));this.$nextTick(()=>this.$refs.lista.children[this.activo]?.scrollIntoView({block:'nearest'}))}
}" x-on:click.outside="abierto=false" x-on:focusout="if(!$el.contains($event.relatedTarget)) abierto=false">
    <label x-bind:for="$id('consulta-input')">{{ $etiqueta }}</label>
    <input type="hidden" name="valor" x-bind:value="valor">
    <div class="gh-horario-combo-control">
        <input type="text" role="combobox" autocomplete="off" x-ref="entrada" x-bind:id="$id('consulta-input')" x-bind:aria-controls="$id('consulta-lista')" x-bind:aria-expanded="abierto.toString()" aria-autocomplete="list" x-bind:aria-activedescendant="abierto&&filtradas[activo]?$id('consulta-opcion',activo):null" placeholder="{{ $placeholder }}" x-bind:value="abierto?consulta:etiqueta" x-on:focus="abrir()" x-on:click="abrir()" x-on:input="consulta=$event.target.value;abierto=true;activo=0" x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)" x-on:keydown.escape.prevent="abierto=false" x-on:keydown.enter="if(abierto){$event.preventDefault();elegir(filtradas[activo])}">
        <button type="button" aria-label="Mostrar opciones" x-on:mousedown.prevent x-on:click="if(abierto){abierto=false}else{abrir();$refs.entrada.focus()}">▾</button>
    </div>
    <div class="gh-horario-combo-menu" x-cloak x-show="abierto">
        <div role="listbox" x-ref="lista" x-bind:id="$id('consulta-lista')">
            <template x-for="(i,k) in filtradas" x-bind:key="i.valor"><div role="option" class="gh-horario-combo-option" x-bind:id="$id('consulta-opcion',k)" x-bind:class="{'is-active':k===activo}" x-bind:aria-selected="(i.valor===valor).toString()" x-on:mouseenter="activo=k" x-on:mousedown.prevent x-on:click="elegir(i)" ><span x-text="i.nombre"></span><small x-show="i.codigo" x-text="' · '+i.codigo"></small></div></template>
        </div><p x-show="!filtradas.length">No se encontraron coincidencias.</p>
    </div>
</div>
