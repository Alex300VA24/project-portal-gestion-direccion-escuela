{{--
    Paginación del módulo. Usa las clases del portal para que herede el tema
    claro, oscuro y de alto contraste.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación">
        <div class="if-pagination">
            <p>
                Mostrando {{ $paginator->firstItem() ?? 0 }} a {{ $paginator->lastItem() ?? 0 }}
                de {{ $paginator->total() }} resultados
            </p>

            <div class="if-actions">
                @if ($paginator->onFirstPage())
                    <span class="if-button-secondary if-pagination-disabled" aria-disabled="true">
                        Anterior
                    </span>
                @else
                    <a class="if-button-secondary" rel="prev" href="{{ $paginator->previousPageUrl() }}">
                        Anterior
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="if-pagination-gap">…</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="if-button if-pagination-current" aria-current="page">
                                    {{ $page }}
                                </span>
                            @else
                                <a class="if-button-secondary" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="if-button-secondary" rel="next" href="{{ $paginator->nextPageUrl() }}">
                        Siguiente
                    </a>
                @else
                    <span class="if-button-secondary if-pagination-disabled" aria-disabled="true">
                        Siguiente
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
