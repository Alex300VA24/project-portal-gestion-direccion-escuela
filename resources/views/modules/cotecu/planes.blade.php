@extends('layouts.cotecu')

@section('title', 'Planes curriculares')
@section('page-label', 'Planes curriculares')

@section('content')
    <div class="quality-page" x-data="{ avisoArchivo: false }">
        <header class="quality-page-heading">
            <div>
                <h1>Planes curriculares</h1>
                <p>Planes curriculares de la Escuela de Ingeniería Informática, indicando el año en que entraron en vigencia.</p>
            </div>
        </header>

        @if ($porRevisar)
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>Tienes {{ $porRevisar }} {{ $porRevisar === 1 ? 'plan' : 'planes' }} por revisar · Entra a Revisar planes en el menú lateral</strong>
                </div>
            </div>
        @endif

        @if ($planes->isEmpty())
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no hay planes curriculares</h2>
                <p>Los planes curriculares aprobados aparecerán aquí junto con el año en que entraron en vigencia.</p>
            </section>
        @else
            <ul class="quality-document-list">
                @foreach ($planes as $plan)
                    <li>
                        <span class="quality-document-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/></svg>
                        </span>
                        <div>
                            <h2>{{ $plan->nombre }}</h2>
                            <p>
                                <span class="quality-period">{{ $plan->plan_codigo }}</span>
                            </p>
                        </div>
                        <div class="quality-file-meta">
                            <span class="quality-a11y-actions">
                                <button class="institutional-primary" type="button" @click="avisoArchivo = true">Ver</button>
                                <button class="institutional-secondary" type="button" @click="avisoArchivo = true">Descargar</button>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="quality-modal" x-cloak x-show="avisoArchivo" x-transition.opacity @keydown.escape.window="avisoArchivo = false">
            <div class="quality-modal-backdrop" @click="avisoArchivo = false"></div>
            <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="aviso-archivo-title">
                <header>
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="aviso-archivo-title">Archivo no disponible</h2></div>
                    <button class="quality-icon-button" type="button" @click="avisoArchivo = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <div class="quality-a11y-body">
                    <div class="quality-a11y-row">
                        <div>
                            <strong>Por ahora no se puede ver ni descargar el archivo</strong>
                            <small>La subida de archivos está simulada en esta versión de COTECU: los PDF aún no están cargados en el servidor. La función se habilitará en una siguiente entrega.</small>
                        </div>
                    </div>
                    <div class="quality-a11y-actions">
                        <button class="institutional-primary" type="button" @click="avisoArchivo = false">Entendido</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
