<div class="if-panel if-stack" style="margin-top: 22px; padding: 18px 20px;">
    <div class="if-toolbar" style="justify-content: space-between;">
        <h2 class="if-section-title">Estado de configuración</h2>
        <span class="if-tag">Versión {{ $estado->version }}</span>
    </div>

    <ul class="if-grid if-grid-compact" style="font-size: 14px;" role="list">
        <li>
            Competencias:
            <strong class="{{ $estado->tiene_competencias ? 'if-text-success' : 'if-text-warning' }}">
                {{ $estado->tiene_competencias ? 'Completas' : 'Pendientes' }}
            </strong>
        </li>
        <li>
            Rúbrica:
            <strong class="{{ $estado->tiene_rubrica ? 'if-text-success' : 'if-text-warning' }}">
                {{ $estado->tiene_rubrica ? 'Completa' : 'Pendiente' }}
            </strong>
        </li>
        <li>
            Cronograma:
            <strong class="{{ $estado->tiene_cronograma ? 'if-text-success' : 'if-text-warning' }}">
                {{ $estado->tiene_cronograma ? 'Completo' : 'Pendiente' }}
            </strong>
        </li>
    </ul>

    @if ($estado->faltantes === [])
        <p class="if-text-success">La configuración de la sección está completa.</p>
    @else
        <p class="if-text-warning">Falta completar: {{ implode(', ', $estado->faltantes) }}.</p>
    @endif
</div>
