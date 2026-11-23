@extends('layouts.cotecu')

@section('title', 'Documentos oficiales')
@section('page-label', 'Documentos oficiales')

@section('content')
    <div class="quality-page" x-data="{ abierto: null }">
        <header class="quality-page-heading">
            <div>
                <h1>Documentos oficiales</h1>
                <p>Documentos oficiales que respaldan los flujos de COTECU, con acceso a la fuente oficial en Google Docs o Google Sheets.</p>
            </div>
        </header>

        @if ($documentos->isEmpty())
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no hay documentos oficiales</h2>
                <p>Los documentos oficiales de COTECU aparecerán aquí.</p>
            </section>
        @else
            <ul class="quality-document-list">
                @foreach ($documentos as $documento)
                    <li>
                        <span class="quality-document-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        </span>
                        <div>
                            <h2>{{ $documento->titulo }}</h2>
                        </div>
                        <div class="quality-file-meta">
                            <span class="quality-a11y-actions">
                                <button class="institutional-primary" type="button" @click="abierto = {{ $documento->id }}">Ver</button>
                                <a class="institutional-secondary" href="{{ $documento->url }}" target="_blank" rel="noopener noreferrer">Abrir fuente</a>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Ver: modal con el lector de solo lectura de la fuente
                 oficial. El iframe se monta con template x-if, así que
                 Google solo se carga cuando se pide ver el documento. --}}
            @foreach ($documentos as $documento)
                <div class="quality-modal" x-cloak x-show="abierto === {{ $documento->id }}" x-transition.opacity @keydown.escape.window="abierto = null">
                    <div class="quality-modal-backdrop" @click="abierto = null"></div>
                    <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="lector-title-{{ $documento->id }}">
                        <header>
                            <div><span class="quality-eyebrow">COTECU</span><h2 id="lector-title-{{ $documento->id }}">{{ $documento->titulo }}</h2></div>
                            <button class="quality-icon-button" type="button" @click="abierto = null" aria-label="Cerrar">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </header>
                        <div class="quality-a11y-body">
                            <div class="quality-alert" role="status">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                                <div>
                                    <strong>El documento se mostrará dependiendo de la velocidad del internet</strong>
                                </div>
                            </div>
                            <template x-if="abierto === {{ $documento->id }}">
                                <iframe src="{{ $documento->previewUrl() }}" width="100%" height="700" frameborder="0" loading="lazy" title="Lector de solo lectura: {{ $documento->titulo }}"></iframe>
                            </template>
                        </div>
                    </section>
                </div>
            @endforeach
        @endif
    </div>
@endsection
