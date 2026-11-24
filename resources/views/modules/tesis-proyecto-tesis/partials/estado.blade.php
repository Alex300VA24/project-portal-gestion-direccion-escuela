@php($tzEstado = $estado ?? null)
<span class="tz-status is-{{ \App\Modules\TesisProyectoTesis\Support\Estados::variante($tzEstado) }}">{{ \App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($tzEstado) }}</span>
