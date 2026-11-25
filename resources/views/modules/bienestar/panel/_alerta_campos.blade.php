<div class="campos-2">
    <label class="campo">Código universitario *
        <input name="codigo_estudiante" value="{{ old('codigo_estudiante') }}" required placeholder="Ej. 1021400121">
    </label>
    <label class="campo">Curso o contexto
        <input name="curso" value="{{ old('curso', 'GLOBAL') }}" maxlength="150">
    </label>
    <label class="campo">Prioridad *
        <select name="prioridad" required>
            @foreach (['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'] as $v => $t)
                <option value="{{ $v }}" @selected(old('prioridad', 'media') === $v)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
    <label class="campo">Categoría *
        <select name="categoria_motivo" required>
            @foreach (\App\Modules\Bienestar\Support\CategoriaMotivo::TODAS as $v => $t)
                <option value="{{ $v }}" @selected(old('categoria_motivo') === $v)>{{ $t }}</option>
            @endforeach
        </select>
    </label>
</div>
<label class="campo">Motivo resumido *
    <input name="motivo_reporte" value="{{ old('motivo_reporte') }}" required maxlength="255">
</label>
<label class="campo">Descripción *
    <textarea name="descripcion_reporte" rows="4" required minlength="10">{{ old('descripcion_reporte') }}</textarea>
    <small>Se guarda cifrada. Solo la lee Bienestar.</small>
</label>
