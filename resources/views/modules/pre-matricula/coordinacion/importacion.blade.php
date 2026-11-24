@extends('modules.pre-matricula.layout')

@section('title', 'Importación #'.$importacion->id_importacion)
@section('page-label', 'Resultados oficiales')

@php $valor = fn (?array $r) => $r === null ? '—' : ($r['estado'] ?? '—').(isset($r['nota_final']) && $r['nota_final'] !== null ? ' · '.$r['nota_final'] : ''); @endphp

@section('content')
    <header class="quality-page-heading">
        <div><h1>Importación #{{ $importacion->id_importacion }}</h1><p>{{ $importacion->motivo }} · {{ $importacion->creado_en?->timezone('America/Lima')->translatedFormat('d M Y, H:i') }} · {{ $importacion->autor?->correo_institucional ?? 'usuario '.$importacion->creado_por }}</p></div>
        <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.resultados') }}">Volver</a>
    </header>

    <section class="quality-panel" aria-labelledby="cambios-titulo">
        <header class="quality-panel-heading"><div><h2 id="cambios-titulo">Cambios registrados</h2><p>{{ $importacion->resumen['crear'] ?? 0 }} nuevos · {{ $importacion->resumen['actualizar'] ?? 0 }} actualizados · {{ $importacion->resumen['sin_cambios'] ?? 0 }} sin cambios</p></div></header>
        <div class="pm-table-wrap">
            <table class="pm-table">
                <thead><tr><th>Estudiante</th><th>Curso</th><th>Período</th><th>Antes</th><th>Después</th></tr></thead>
                <tbody>
                    @forelse ($cambios as $c)
                        <tr><td><span class="pm-code">{{ $c->codigo_universitario }}</span></td><td><span class="pm-code">{{ $c->curso }}</span> {{ $c->nombre }}</td><td>{{ $c->periodo }}</td><td>{{ $valor($c->antes) }}</td><td>{{ $valor($c->despues) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="pm-muted">Esta importación no cambió resultados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
