<div class="rounded-lg border border-gray-200 bg-white p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-gray-900">Estado de configuración</h2>
        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
            Versión {{ $estado->version }}
        </span>
    </div>

    <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-3" role="list">
        <li>
            Competencias:
            <strong class="{{ $estado->tiene_competencias ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $estado->tiene_competencias ? 'Completas' : 'Pendientes' }}
            </strong>
        </li>
        <li>
            Rúbrica:
            <strong class="{{ $estado->tiene_rubrica ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $estado->tiene_rubrica ? 'Completa' : 'Pendiente' }}
            </strong>
        </li>
        <li>
            Cronograma:
            <strong class="{{ $estado->tiene_cronograma ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $estado->tiene_cronograma ? 'Completo' : 'Pendiente' }}
            </strong>
        </li>
    </ul>

    @if ($estado->faltantes === [])
        <p class="mt-3 text-sm text-emerald-800">
            La configuración de la sección está completa.
        </p>
    @else
        <p class="mt-3 text-sm text-amber-900">
            Falta completar: {{ implode(', ', $estado->faltantes) }}.
        </p>
    @endif
</div>
