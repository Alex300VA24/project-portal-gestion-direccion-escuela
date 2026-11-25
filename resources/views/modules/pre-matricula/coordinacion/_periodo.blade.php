<form class="quality-period-form" method="GET" action="{{ $accion }}" x-data>
    <label for="periodo-filtro">Período</label>
    <select id="periodo-filtro" name="periodo" @change="$el.form.submit()">
        @foreach ($periodos as $p)<option value="{{ $p->id_periodo }}" @selected($periodo?->id_periodo === $p->id_periodo)>{{ $p->codigo }}{{ $p->estado ? ' · '.Str::lower($p->estado) : '' }}</option>@endforeach
    </select>
    <noscript><button class="pm-button is-small" type="submit">Ver</button></noscript>
</form>
