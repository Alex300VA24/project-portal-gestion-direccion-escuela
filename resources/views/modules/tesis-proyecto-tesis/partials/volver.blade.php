{{-- Enlace a la lista de origen. Requiere: $url y $a (nombre de la lista). --}}
<a class="tz-back" href="{{ $url }}">
    <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 12H5"/><path d="m11 18-6-6 6-6"/></svg>
    <span>Volver a {{ $a }}</span>
</a>
