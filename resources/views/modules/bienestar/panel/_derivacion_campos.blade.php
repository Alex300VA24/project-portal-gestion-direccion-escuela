<input type="hidden" name="id_caso" value="{{ $caso->id_caso }}">
<label class="campo">Especialista del catálogo
    <select name="id_especialista_destino">
        <option value="">Ninguno: se deriva a una entidad externa</option>
        @foreach ($especialistas as $esp)
            <option value="{{ $esp->id_especialista }}" @selected(old('id_especialista_destino') == $esp->id_especialista)>{{ $esp->especialidad?->nombre }} · {{ $esp->nombre_completo }} ({{ $esp->institucion }})</option>
        @endforeach
    </select>
</label>
<label class="campo">O entidad externa
    <input name="entidad_externa" value="{{ old('entidad_externa') }}" placeholder="Ej. Centro de Salud Mental Comunitario">
</label>
<label class="campo">Motivo de la derivación *
    <textarea name="motivo" rows="4" required>{{ old('motivo') }}</textarea>
</label>
<label class="casilla"><input type="checkbox" name="consentimiento_estudiante" value="1" required @checked(old('consentimiento_estudiante'))>
    <span><b>Consentimiento informado del estudiante *</b><small>Certifico que fue informado y autorizó esta derivación.</small></span>
</label>
