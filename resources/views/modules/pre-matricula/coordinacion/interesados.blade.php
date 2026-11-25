@extends('modules.pre-matricula.layout')

@section('title', $curso->codigo)
@section('page-label', $curso->codigo)

@section('content')
    <header class="quality-page-heading">
        <div><span class="quality-eyebrow">{{ $curso->codigo }} · Ciclo {{ $curso->ciclo }} · {{ $periodo->codigo }}</span><h1>{{ $curso->nombre }}</h1><p>{{ $estudiantes->count() }} estudiantes lo incluyen en su constancia vigente.</p></div>
        <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.demanda', ['periodo' => $periodo->id_periodo]) }}">Volver a demanda</a>
    </header>
    <section class="quality-panel">
        <div class="pm-table-wrap">
            <table class="pm-table">
                <thead><tr><th>Estudiante</th><th>Registrada</th><th class="pm-num">Revisión</th><th>Validación</th><th></th></tr></thead>
                <tbody>
                    @forelse ($estudiantes as $e)
                        <tr>
                            <td><span class="pm-code">{{ $e->codigo_universitario }}</span><br>{{ trim($e->apellido_paterno.' '.$e->apellido_materno) }}, {{ $e->nombres }}</td>
                            <td>{{ $e->enviada_en ? \Illuminate\Support\Carbon::parse($e->enviada_en)->timezone('America/Lima')->translatedFormat('d M Y, H:i') : '—' }}</td>
                            <td class="pm-num">{{ $e->numero_revision }}</td>
                            <td>@include('modules.pre-matricula.partials.estado', ['valor' => $e->validacion === 'SIN_VALIDACION' ? null : $e->validacion])</td>
                            <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.estudiantes.show', ['codigo' => $e->codigo_universitario, 'periodo' => $periodo->id_periodo]) }}">Situación</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pm-muted">Sin estudiantes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
