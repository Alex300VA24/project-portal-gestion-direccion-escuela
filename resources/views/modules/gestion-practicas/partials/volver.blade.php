{{-- Botón para regresar al listado desde una pantalla de detalle. Parámetros: $ruta (URL) y $texto (nombre del listado). --}}
<a class="institutional-secondary" href="{{ $ruta }}">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    Volver a {{ $texto }}
</a>
