<x-bienestar::modal-unt id="m-reporte" titulo="Reportar estudiante en riesgo" detalle="Secretaría lo revisa y Bienestar da el acompañamiento." :accion="route('bienestar.profesor.store')" boton="Enviar reporte" ancho="44rem">
    @if (! empty($estudiantesAsignados))
        <label class="campo">Estudiante de mis cursos
            <select id="r-asignado"><option value="">Selecciona, o escribe su código abajo</option>
                @foreach ($estudiantesAsignados as $est)<option value="{{ $est['codigo'] }}" data-curso="{{ $est['curso'] }}" data-nombre="{{ $est['nombres'] }} {{ $est['apellidos'] }}">{{ $est['nombres'] }} {{ $est['apellidos'] }} · {{ $est['curso'] }}</option>@endforeach
            </select>
        </label>
    @endif
    <div class="campos-2">
        <label class="campo">Código universitario *<input name="codigo_estudiante" id="r-codigo" value="{{ old('codigo_estudiante') }}" required maxlength="30" placeholder="Ej. 1021400222"><small id="r-nombre" role="status">Al salir del campo se verifica el código.</small></label>
        <label class="campo">Curso *<input name="curso" id="r-curso" value="{{ old('curso') }}" required maxlength="150"></label>
        <label class="campo">Urgencia *<select name="prioridad" required>@foreach (['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'] as $v => $t)<option value="{{ $v }}" @selected(old('prioridad', 'media') === $v)>{{ $t }}</option>@endforeach</select></label>
        <label class="campo">Categoría *<select name="categoria_motivo" required>@foreach (\App\Modules\Bienestar\Support\CategoriaMotivo::TODAS as $v => $t)<option value="{{ $v }}" @selected(old('categoria_motivo') === $v)>{{ $t }}</option>@endforeach</select><small>Es lo único del motivo que verá Secretaría.</small></label>
    </div>
    <label class="campo">Motivo resumido *<input name="motivo_reporte" value="{{ old('motivo_reporte') }}" required maxlength="255"></label>
    <label class="campo">Descripción *<textarea name="descripcion_reporte" rows="3" required minlength="10">{{ old('descripcion_reporte') }}</textarea><small>Se guarda cifrada.</small></label>
</x-bienestar::modal-unt>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const codigo = document.getElementById('r-codigo'), nombre = document.getElementById('r-nombre'), lista = document.getElementById('r-asignado');
        lista?.addEventListener('change', () => {
            const o = lista.selectedOptions[0];
            if (!o.value) return;
            codigo.value = o.value;
            document.getElementById('r-curso').value = o.dataset.curso || '';
            nombre.textContent = o.dataset.nombre;
        });
        codigo.addEventListener('change', async () => {
            if (!codigo.value.trim()) return;
            nombre.textContent = 'Verificando…';
            try {
                const r = await fetch(`{{ route('bienestar.profesor.buscar-estudiante') }}?codigo=${encodeURIComponent(codigo.value.trim())}`, { headers: { Accept: 'application/json' } });
                const d = await r.json();
                nombre.textContent = d.error || `${d.nombres} ${d.apellidos}`;
            } catch { nombre.textContent = 'No se pudo verificar el código.'; }
        });
    });
</script>
