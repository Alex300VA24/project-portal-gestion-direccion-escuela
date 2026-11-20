{{-- Dónde corrigió el estudiante: requiere $o con entregaCorreccion (uuid, version) cargada --}}
@if ($o->entregaCorreccion)
    <span class="tz-muted">· corregida en v{{ $o->entregaCorreccion->version }}</span>
    <a class="tz-doc-link" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $o->entregaCorreccion->uuid]) }}" target="_blank" rel="noopener">Ver documento corregido (v{{ $o->entregaCorreccion->version }})</a>
@else
    <span class="tz-warning-text">· sin versión corregida indicada</span>
@endif
