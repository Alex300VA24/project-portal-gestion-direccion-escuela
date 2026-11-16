{{--
    Formulario de la rúbrica única. Requiere: $criterios (por orden), $accionUrl, $modo ('verificacion' | 'intermedia').
    Los criterios viajan por su número de orden (no por id).
--}}
@php
    $tzInicial = [
        'marcas' => (object) (old('marcas') ?: []),
        'obs' => (object) (old('obs') ?: []),
        'ordenes' => $criterios->keys()->values()->all(),
        'enviando' => false,
    ];
@endphp
<script>
    window.tzRubrica = function (d) {
        return {
            ...d,
            get cumple() { return this.ordenes.filter(o => this.marcas[o] === 'cumple' || this.marcas[o] === 'no_aplica').length; },
            get noCumple() { return this.ordenes.filter(o => this.marcas[o] === 'no_cumple').length; },
            get completos() { return this.ordenes.every(o => this.marcas[o]); },
            get faltaObs() { return this.ordenes.some(o => this.marcas[o] === 'no_cumple' && !(this.obs[o] || '').trim()); },
            todoCumple() { this.ordenes.forEach(o => { if (!this.marcas[o]) this.marcas[o] = 'cumple'; }); },
        };
    };
</script>

<form method="POST" action="{{ $accionUrl }}" class="tz-form" x-data="tzRubrica(@js($tzInicial))" @submit="enviando = true">
    @csrf
    <section class="tz-panel tz-panel-body">
        <dl class="tz-facts" style="margin: 0; align-items: center">
            <div><dt>Rúbrica</dt><dd><strong>Rúbrica única · versión {{ $rubricaVersion ?? 1 }}</strong></dd></div>
            <div><dt>Aplica a esta etapa</dt><dd><strong>{{ $criterios->count() }} criterios ({{ $proyecto->etapa }})</strong></dd></div>
            <div><dt>Cumple</dt><dd><strong style="color: var(--tz-green)" x-text="`${cumple} de ${ordenes.length}`"></strong></dd></div>
            <div><dt>No cumple</dt><dd><strong style="color: var(--tz-danger)" x-text="noCumple"></strong></dd></div>
        </dl>
        <div class="tz-progress" style="margin-top: 14px" role="progressbar" aria-label="Criterios que cumplen" :aria-valuenow="cumple" aria-valuemin="0" :aria-valuemax="ordenes.length">
            <span :style="`width: ${ordenes.length ? cumple / ordenes.length * 100 : 0}%`"></span>
        </div>
    </section>

    <section class="tz-panel">
        <div class="tz-panel-head">
            <h2>Criterios</h2>
            <div class="tz-actions">
                <span class="tz-muted tz-small">Cada ✗ debe llevar su observación</span>
                <button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="todoCumple()">Marcar los pendientes como «cumple»</button>
            </div>
        </div>
        @foreach ($criterios->groupBy('seccion') as $seccion => $grupo)
            <div class="tz-rubric-sec">{{ $seccion }}</div>
            @foreach ($grupo as $c)
                <div class="tz-rubric-row">
                    <div class="tz-rubric-line">
                        <b>{{ $c->orden }}</b>
                        <span id="crit-{{ $c->orden }}">{{ $c->descripcion }}</span>
                        <div class="tz-seg" role="radiogroup" aria-labelledby="crit-{{ $c->orden }}">
                            <label :class="marcas[{{ $c->orden }}] === 'cumple' && 'is-on-ok'"><input type="radio" name="marcas[{{ $c->orden }}]" value="cumple" x-model="marcas[{{ $c->orden }}]"> ✓ Cumple</label>
                            <label :class="marcas[{{ $c->orden }}] === 'no_cumple' && 'is-on-ko'"><input type="radio" name="marcas[{{ $c->orden }}]" value="no_cumple" x-model="marcas[{{ $c->orden }}]"> ✗ No cumple</label>
                            @if ($c->permite_no_aplica)
                                <label :class="marcas[{{ $c->orden }}] === 'no_aplica' && 'is-on-na'"><input type="radio" name="marcas[{{ $c->orden }}]" value="no_aplica" x-model="marcas[{{ $c->orden }}]"> No aplica</label>
                            @endif
                        </div>
                    </div>
                    <div class="tz-obsbox" x-show="marcas[{{ $c->orden }}] === 'no_cumple' || marcas[{{ $c->orden }}] === 'no_aplica'" x-cloak>
                        <label for="obs-{{ $c->orden }}" x-text="marcas[{{ $c->orden }}] === 'no_aplica' ? 'Motivo de «no aplica» (opcional)' : 'Observación del criterio {{ $c->orden }}'"></label>
                        <textarea id="obs-{{ $c->orden }}" class="tz-textarea" style="min-height: 64px" name="obs[{{ $c->orden }}]" x-model="obs[{{ $c->orden }}]" maxlength="2000"></textarea>
                    </div>
                    @error('marcas.'.$c->orden) <span class="tz-error">{{ $message }}</span> @enderror
                    @error('obs.'.$c->orden) <span class="tz-error">{{ $message }}</span> @enderror
                </div>
            @endforeach
        @endforeach
        <div class="tz-panel-body tz-field">
            <label for="comentario">Comentario general (opcional)</label>
            <textarea id="comentario" name="comentario" class="tz-textarea" maxlength="3000">{{ old('comentario') }}</textarea>
        </div>
    </section>

    <div class="tz-sticky">
        <div class="tz-alert" :class="noCumple > 0 || !completos ? 'is-warning' : 'is-success'" style="justify-content: space-between; flex-wrap: wrap; box-shadow: var(--portal-shadow)">
            <div>
                @if ($modo === 'intermedia')
                    <strong>Revisión intermedia (referencial).</strong> El estudiante verá qué criterios le faltan; no bloquea su avance.
                @else
                    <template x-if="!completos"><span><strong>Faltan criterios por marcar.</strong> Marca todos para continuar.</span></template>
                    <template x-if="completos && noCumple > 0"><span><strong>Aún no puedes dar tu conformidad.</strong> Hay <span x-text="noCumple"></span> criterios en ✗. Al guardar, sus observaciones se envían a los estudiantes.</span></template>
                    <template x-if="completos && noCumple === 0"><span><strong>Todos los criterios cumplen.</strong> Puedes dar tu conformidad.</span></template>
                @endif
            </div>
            <div class="tz-actions">
                @if ($modo === 'intermedia')
                    <button type="submit" name="accion" value="referencial" class="tz-btn tz-btn-navy" :disabled="enviando || !completos || faltaObs">Registrar revisión intermedia</button>
                @else
                    <button type="submit" name="accion" value="observaciones" class="tz-btn tz-btn-navy" :disabled="enviando || !completos || faltaObs || noCumple === 0">Guardar y enviar observaciones</button>
                    <button type="submit" name="accion" value="conformidad" class="tz-btn tz-btn-primary" :disabled="enviando || !completos || noCumple > 0">Dar conformidad</button>
                @endif
            </div>
        </div>
        @error('conformidad') <span class="tz-error">{{ $message }}</span> @enderror
    </div>
</form>
