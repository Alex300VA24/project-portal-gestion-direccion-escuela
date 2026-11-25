@extends('modules.pre-matricula.layout')

@section('title', 'Estudiantes')
@section('page-label', 'Estudiantes')

@section('content')
    <header class="quality-page-heading"><div><h1>Situación de estudiantes</h1><p>Busca un estudiante para ver su historial, su prematrícula y los cursos que puede llevar.</p></div></header>
    <form class="pm-filters" method="GET" action="{{ route('pre-matricula.coordinacion.estudiantes') }}" role="search">
        <label class="pm-field"><span>Código universitario o nombre</span><input type="search" name="buscar" value="{{ $buscar }}" minlength="2" autofocus></label>
        <button class="pm-button" type="submit">Buscar</button>
    </form>
    @if ($buscar !== '')
        <section class="quality-panel">
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Código</th><th>Nombre</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($estudiantes as $e)
                            <tr>
                                <td><span class="pm-code">{{ $e->codigo_universitario }}</span></td>
                                <td>{{ trim(($e->persona?->apellido_paterno ?? '').' '.($e->persona?->apellido_materno ?? '')) }}, {{ $e->persona?->nombres }}</td>
                                <td>{{ Str::of($e->estado)->lower()->ucfirst() }}</td>
                                <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.estudiantes.show', $e->codigo_universitario) }}">Ver situación</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pm-muted">No se encontraron estudiantes. Se muestran hasta 25 resultados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
