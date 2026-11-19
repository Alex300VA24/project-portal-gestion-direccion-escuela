{{-- Lista de documentos de DESIGN.md §4 (.quality-document-list). Parámetros: $documentos (colección), $puedeRevisar (bool) --}}
@php($tipos = \App\Modules\GestionPracticas\Support\Catalogos::TIPOS_DOCUMENTO)
<ul class="quality-document-list">
    @forelse ($documentos as $documento)
        <li>
            <span class="quality-document-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h4"/></svg></span>
            <div>
                <h2>{{ $tipos[$documento->tipo_documento] ?? $documento->tipo_documento }}</h2>
                <p>
                    <span class="quality-code">{{ $documento->nombre_original }}</span>
                    <span>Subido el {{ \Illuminate\Support\Carbon::parse($documento->subido_en)->format('d/m/Y H:i') }}</span>
                </p>
            </div>
            <div class="quality-file-meta gp-row-actions">
                @include('modules.gestion-practicas.partials.estado', ['estado' => $documento->estado])
                <a class="quality-open-link" href="{{ route('gestion-practicas.documentos.descargar', $documento->id_documento) }}">Descargar</a>
                @if ($puedeRevisar && $documento->estado !== 'reemplazado')
                    <form method="POST" action="{{ route('gestion-practicas.documentos.revisar', $documento->id_documento) }}">
                        @csrf
                        <button class="quality-open-link" name="estado" value="aprobado">Aprobar</button>
                        <button class="quality-open-link is-danger" name="estado" value="observado">Observar</button>
                    </form>
                @endif
            </div>
        </li>
    @empty
        <li class="quality-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/></svg>
            <h2>Aún no hay documentos</h2>
            <p>Los archivos que se suban a esta práctica aparecerán aquí con su estado de revisión.</p>
        </li>
    @endforelse
</ul>
