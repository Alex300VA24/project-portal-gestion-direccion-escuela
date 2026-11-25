<x-bienestar::modal-unt :id="'m-subsanar-'.$solicitud->id_solicitud" titulo="Corregir lo observado" :detalle="'Solicitud #'.$solicitud->id_solicitud.'. Vuelve a subir solo estos documentos.'"
    :accion="route('bienestar.alumno.solicitudes.guardar-subsanacion', $solicitud)" boton="Reenviar" ancho="42rem">
    @if ($solicitud->observacion_general_secretaria)<p class="nota">{{ $solicitud->observacion_general_secretaria }}</p>@endif
    @foreach ($solicitud->documentosRequisitos->where('estado_revision', 'observado') as $doc)
        <label class="archivo">
            <span><b>{{ $doc->requisito->nombre }}</b><small class="texto-rojo">{{ $doc->comentario_revision ?: 'Documento observado.' }}</small><small>{{ strtoupper($doc->requisito->formatos_permitidos) }} · máx. {{ $doc->requisito->tamano_maximo_mb }} MB</small></span>
            <input type="file" name="archivos[{{ $doc->id_requisito }}]" required accept="{{ '.'.str_replace([' ', ','], ['', ',.'], $doc->requisito->formatos_permitidos) }}">
        </label>
    @endforeach
</x-bienestar::modal-unt>
