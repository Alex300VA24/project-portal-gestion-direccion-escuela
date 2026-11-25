@extends('layouts.cotecu')

@section('title', 'Revisar sílabo')
@section('page-label', 'Revisar sílabos')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>Revisar sílabo</h1>
                <p>
                    <span class="quality-period">{{ $revision->periodo_codigo ?? '—' }}</span>
                    {{ $revision->curso_codigo ?? '' }} · {{ $revision->curso_nombre ?? '' }} · Ciclo {{ $revision->ciclo ?? '—' }} ·
                    Versión {{ $revision->version ?? '—' }} · Profesor: {{ $revision->profesor_nombre ?? '—' }}
                </p>
            </div>
            <div class="quality-period-form">
                @if ($cerrada)
                    @if ($revision->estado_final === 'Validado')
                        <span class="quality-status is-success">Revisión: {{ $revision->estado_final }}</span>
                    @else
                        <span class="quality-status is-danger">Revisión: {{ $revision->estado_final }}</span>
                    @endif
                @endif
                <a class="quality-open-link" href="{{ route('cotecu.silabos.revisiones') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5"/><path d="m11 6-6 6 6 6"/></svg>
                    Volver al listado
                </a>
            </div>
        </header>

        @if ($errors->any())
            <div class="quality-alert" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>No se pudo cerrar la revisión</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        <div class="quality-panel">
            <div class="quality-improvement-grid">
                <article>
                    <span class="quality-eyebrow">Documento PDF</span>
                    <section class="quality-empty">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/></svg>
                        <h2>acá irá el lector de pdf</h2>
                        <p>El visor del sílabo se integrará en una siguiente entrega de COTECU.</p>
                    </section>
                </article>

                <article>
                    <h2 class="quality-eyebrow">Lista de cotejo</h2>
                    <p>Marca lo que sí se cumple. Lo que no, déjalo sin marcar: un solo ítem sin marcar deja el sílabo Observado.</p>

                    @if ($items->isNotEmpty())
                        <form method="POST" action="{{ route('cotecu.silabos.revisiones.store', $revision->id) }}">
                            @csrf
                            @foreach ($items as $item)
                                @php($resultado = $resultados->get($item->id))
                                @php($cumpleInicial = old("items.{$item->id}.cumple", $resultado?->cumple ?? false))
                                <div class="quality-a11y-row">
                                    <label>
                                        <input type="hidden" name="items[{{ $item->id }}][cumple]" value="0">
                                        <input type="checkbox"
                                               name="items[{{ $item->id }}][cumple]"
                                               value="1"
                                               @checked($cumpleInicial)
                                               @disabled($cerrada)>
                                        <span>{{ $item->descripcion }}</span>
                                    </label>
                                    {{-- El director de escuela quitó las observaciones por ítem:
                                         los ítems son suficientemente claros y nunca hizo falta
                                         una observación textual. Para restaurar el cuadro hay
                                         que descomentar el template de abajo y volver a poner
                                         Rule::requiredIf($incumple) con su mensaje .required en
                                         CotecuController::storeRevision. --}}
                                    {{-- TEMPLATE — cuadro de observación por ítem:
                                        <textarea name="items[{{ $item->id }}][observacion]"
                                                  rows="2"
                                                  placeholder="Observación si no se cumple…"
                                                  @disabled($cerrada)>{{ old("items.{$item->id}.observacion", $resultado?->observacion_item ?? '') }}</textarea>
                                    --}}
                                </div>
                            @endforeach

                            <div class="quality-a11y-actions">
                                @unless ($cerrada)
                                    <button class="institutional-primary" type="submit">Guardar y cerrar revisión</button>
                                @endunless
                                <a class="quality-open-link" href="{{ route('cotecu.silabos.revisiones') }}">Cancelar</a>
                            </div>
                        </form>
                    @else
                        <p class="quality-list-empty">Esta lista de cotejo no tiene ítems registrados.</p>
                    @endif
                </article>
            </div>
        </div>
    </div>
@endsection
