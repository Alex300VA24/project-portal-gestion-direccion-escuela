{{-- Selector local: escribir filtra las opciones; solo seleccionar cambia el filtro Livewire. --}}
@php
    $itemsSelector = collect($opciones)->map(fn ($nombre, $codigo) => ['valor' => (string) $codigo, 'nombre' => (string) $nombre])->values()->all();
@endphp
<div class="gh-horario-combo"
    wire:key="selector-{{ $campo }}-{{ hash('sha256', json_encode($itemsSelector, JSON_THROW_ON_ERROR)) }}"
    x-id="['gh-combo-entrada', 'gh-combo-lista', 'gh-combo-opcion']"
    x-data="{
        opciones: @js($itemsSelector),
        campo: @js($campo),
        consulta: '', abierto: false, activo: -1,
        normalizar(s) { return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim(); },
        get seleccion() { return String($wire[this.campo] || ''); },
        get etiqueta() { return this.opciones.find(o => o.valor === this.seleccion)?.nombre || ''; },
        get filtradas() { const palabras = this.normalizar(this.consulta).split(/\s+/).filter(Boolean); return this.opciones.filter(o => palabras.every(p => this.normalizar(o.nombre).includes(p))); },
        abrir() { if (this.abierto) return; this.consulta = ''; this.abierto = true; this.activo = Math.max(0, this.filtradas.findIndex(o => o.valor === this.seleccion)); },
        cerrar() { this.abierto = false; this.consulta = ''; this.activo = -1; },
        escribir(valor) { this.abierto = true; this.consulta = valor; this.activo = this.filtradas.length ? 0 : -1; },
        mover(delta) {
            if (!this.abierto) { this.abrir(); if (delta < 0 && !this.seleccion) this.activo = this.filtradas.length - 1; }
            else if (this.filtradas.length) this.activo = Math.max(0, Math.min(this.filtradas.length - 1, this.activo + delta));
            if (this.filtradas.length) this.$nextTick(() => this.$refs.lista.querySelectorAll('[role=option]')[this.activo]?.scrollIntoView({block:'nearest'}));
        },
        elegir(opcion) { if (!opcion) return; this.cerrar(); if (this.seleccion !== opcion.valor) $wire.$set(this.campo, opcion.valor); },
        alternar() { if (this.abierto) this.cerrar(); else { this.abrir(); this.$refs.entrada.focus(); } }
    }"
    x-on:click.outside="cerrar()"
    x-on:focusout="if (!$el.contains($event.relatedTarget)) cerrar()">
    <label class="gh-horario-combo-label" x-bind:for="$id('gh-combo-entrada')">{{ $etiqueta }}</label>
    <div class="gh-horario-combo-control">
        <input type="text" role="combobox" autocomplete="off" maxlength="120" aria-autocomplete="list"
            x-ref="entrada" x-bind:id="$id('gh-combo-entrada')" x-bind:aria-controls="$id('gh-combo-lista')"
            x-bind:aria-expanded="abierto.toString()"
            x-bind:aria-activedescendant="abierto && filtradas[activo] ? $id('gh-combo-opcion', activo) : null"
            placeholder="{{ $placeholder }}" x-bind:value="abierto ? consulta : etiqueta"
            x-on:focus="abrir()" x-on:click="abrir()" x-on:input="escribir($event.target.value)"
            x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
            x-on:keydown.enter="if (abierto) { $event.preventDefault(); elegir(filtradas[activo]); }"
            x-on:keydown.escape="if (abierto) { $event.preventDefault(); $event.stopPropagation(); cerrar(); }">
        <button type="button" tabindex="-1" aria-label="Mostrar u ocultar opciones de {{ $etiqueta }}"
            x-bind:aria-expanded="abierto.toString()" x-bind:aria-controls="$id('gh-combo-lista')"
            x-on:mousedown.prevent x-on:click="alternar()"><span aria-hidden="true" x-text="abierto ? '▴' : '▾'"></span></button>
    </div>
    <div class="gh-horario-combo-menu" x-cloak x-show="abierto">
        <div role="listbox" x-bind:id="$id('gh-combo-lista')" aria-label="{{ $etiqueta }}" x-ref="lista">
            <template x-for="(opcion, indice) in filtradas" x-bind:key="opcion.valor">
                <div role="option" class="gh-horario-combo-option" x-bind:id="$id('gh-combo-opcion', indice)"
                    x-bind:class="{'is-active': indice === activo}" x-bind:aria-selected="(opcion.valor === seleccion).toString()"
                    x-on:mouseenter="activo = indice" x-on:mousedown.prevent x-on:click="elegir(opcion)" x-text="opcion.nombre"></div>
            </template>
        </div>
        <p class="gh-horario-combo-empty" role="status" x-show="!filtradas.length">No se encontraron coincidencias.</p>
    </div>
</div>
