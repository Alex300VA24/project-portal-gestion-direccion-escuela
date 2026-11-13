@extends('layouts.cotecu')

@section('title', 'Planes curriculares')
@section('page-label', 'Planes curriculares')

@section('content')
    <div class="quality-page" x-data="{ subirOpen: false }">
        <header class="quality-page-heading">
            <div>
                <h1>Planes curriculares</h1>
                <p>Documentos del plan de estudios y su estado de revisión. No se filtran por semestre: un plan se revisa una vez y vigila durante años.</p>
            </div>
            @if ($puedeSubir)
                <button class="institutional-primary" type="button" @click="subirOpen = true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                    Subir plan curricular
                </button>
            @endif
        </header>

        @if ($planes->isEmpty())
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no hay planes curriculares</h2>
                <p>Los planes subidos por el director de COTECU aparecerán aquí junto con su estado de revisión.</p>
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
                                Versión {{ $plan->version }}
                            </p>
                        </div>
                        <div class="quality-file-meta">
                            @if ($plan->estado === 'Validado')
                                <span class="quality-status is-success">{{ $plan->estado }}</span>
                            @elseif ($plan->estado === 'Observado')
                                <span class="quality-status is-danger">{{ $plan->estado }}</span>
                            @else
                                <span class="quality-status is-warning">{{ $plan->estado }}</span>
                            @endif
                            <small>Plan {{ $plan->plan_nombre }}</small>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="quality-modal" x-cloak x-show="subirOpen" x-transition.opacity @keydown.escape.window="subirOpen = false">
            <div class="quality-modal-backdrop" @click="subirOpen = false"></div>
            <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="subir-plan-title">
                <header>
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="subir-plan-title">Sección en construcción</h2></div>
                    <button class="quality-icon-button" type="button" @click="subirOpen = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <p>El formulario de subida de planes curriculares se habilitará en la siguiente entrega del módulo COTECU.</p>
            </section>
        </div>
    </div>
@endsection
