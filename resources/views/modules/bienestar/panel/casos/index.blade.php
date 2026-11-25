@extends('modules.bienestar.layout')
@section('title', 'Casos · Bienestar')
@section('page-label', 'Casos y citas')
@section('content')
<div class="space-y-6">
    <div class="encabezado"><div><h1>Casos y citas</h1><p>Acompañamiento abierto y concluido, desde alertas o solicitudes aprobadas.</p></div></div>
    <form method="GET" class="card-unt filtros">
        <label class="campo">Estado<select name="estado" onchange="this.form.submit()">
            @foreach (['' => 'Todos', 'abierto' => 'Abiertos', 'cerrado' => 'Cerrados'] as $v => $t)<option value="{{ $v }}" @selected(($filtros['estado'] ?? '') === $v)>{{ $t }}</option>@endforeach
        </select></label>
        <label class="campo">Estudiante<input name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código o apellidos"></label>
        <button class="btn-unt-primary">Buscar</button>
    </form>
    <div class="card-unt overflow-hidden">
        <table class="min-w-full tabla">
            <thead><tr><th>Caso</th><th>Estudiante</th><th>Tipo</th><th>Apertura</th><th>Citas</th><th>Derivaciones</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($casos as $c)
                    <tr>
                        <td class="font-mono font-bold">#{{ $c->id_caso }}</td>
                        <td><b>{{ $c->estudiante?->persona?->nombres }} {{ $c->estudiante?->persona?->apellido_paterno }}</b><small>{{ $c->estudiante?->codigo_universitario }}</small></td>
                        <td>{{ $c->tipoCaso?->nombre }}</td>
                        <td>{{ $c->abierto_en?->format('d/m/Y') }}</td>
                        <td>{{ $c->citas_count }}</td>
                        <td>{{ $c->derivaciones_count }}</td>
                        <td><span class="etiqueta {{ $c->estado === 'Cerrado' ? 'etiqueta-gris' : 'etiqueta-azul' }}">{{ str_replace('_', ' ', $c->estado) }}</span></td>
                        <td class="text-right"><a class="btn-unt-primary" href="{{ route('bienestar.panel.casos.show', $c) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="vacio">No hay casos con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $casos->links() }}
</div>
@endsection
