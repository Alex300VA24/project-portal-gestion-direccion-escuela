<div class="card-unt overflow-hidden">
    <table class="min-w-full tabla">
        <thead><tr><th>Requisito</th><th>Archivo</th><th>Revisión</th><th></th></tr></thead>
        <tbody>
            @forelse ($solicitud->documentosRequisitos as $d)
                <tr>
                    <td><b>{{ $d->requisito?->nombre }}</b>@if ($d->estado_revision === 'observado' && $d->comentario_revision)<small class="texto-rojo">{{ $d->comentario_revision }}</small>@endif</td>
                    <td>{{ $d->archivo?->nombre_original ?? 'Sin archivo' }}@if ($d->archivo)<small>{{ number_format($d->archivo->tamano_bytes / 1024, 0) }} KB</small>@endif</td>
                    <td><span class="etiqueta {{ ['conforme' => 'etiqueta-azul', 'observado' => 'etiqueta-rojo'][$d->estado_revision] ?? 'etiqueta-dorado' }}">{{ ucfirst($d->estado_revision) }}</span></td>
                    <td class="text-right">@if ($descarga && $d->archivo)<a class="btn-unt-outline" href="{{ route($descarga, $d) }}">Descargar</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">La solicitud no tiene documentos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
