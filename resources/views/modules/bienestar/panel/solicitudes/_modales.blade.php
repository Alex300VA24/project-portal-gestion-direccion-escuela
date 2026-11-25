<x-bienestar::modal-unt id="m-aprobar" titulo="Aprobar solicitud" detalle="Se abrirá un caso de apoyo y el estudiante recibirá un aviso." boton="Aprobar">
    <input type="hidden" name="estado" value="aprobada">
    <label class="campo">Indicaciones para el estudiante<textarea name="observacion" rows="3" maxlength="500">{{ old('estado') === 'aprobada' ? old('observacion') : '' }}</textarea></label>
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-rechazar" titulo="Rechazar solicitud" detalle="El estudiante verá el motivo. No se puede deshacer." boton="Rechazar" tono="peligro">
    <input type="hidden" name="estado" value="rechazada">
    <label class="campo">Motivo del rechazo *<textarea name="observacion" rows="3" required minlength="10" maxlength="500">{{ old('estado') === 'rechazada' ? old('observacion') : '' }}</textarea></label>
</x-bienestar::modal-unt>
