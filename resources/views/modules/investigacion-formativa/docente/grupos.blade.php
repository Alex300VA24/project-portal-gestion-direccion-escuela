@extends('layouts.app')
@section('title', 'Grupos · Investigación Formativa')
@section('page-label', 'Grupos')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Preferencias</span>
            <h2>Grupos de investigación</h2>
            <p>
                Revisa el título propuesto por cada grupo de la sección. El estado del título solo
                cambia por una revisión registrada.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="if-flash-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 17h.01"/></svg>
            <span>
                <strong>Revisa los siguientes errores</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </span>
        </div>
    @endif

    <form class="mb-4" method="GET" action="{{ route('investigacion-formativa.docente.grupos.index', $seccion) }}">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700" for="buscar-grupo">Buscar grupo o título</label>
                <input
                    class="mt-1 block w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="buscar-grupo"
                    name="buscar"
                    type="search"
                    value="{{ $busqueda }}"
                >
            </div>
            <button
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                type="submit"
            >
                Buscar
            </button>
        </div>
    </form>

    @if ($grupos->isEmpty())
        <div class="if-empty" style="margin:16px 0;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3>No hay grupos que coincidan con la búsqueda</h3>
            <p>Ajuste el filtro de búsqueda para ver los grupos.</p>
        </div>
    @else
        <div class="if-panel" style="margin:16px 0;overflow-x:auto">
            <table class="if-table">
                <thead>
                    <tr>
                        <th scope="col">Grupo</th>
                        <th scope="col">Coordinador</th>
                        <th scope="col">Título propuesto</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Avances</th>
                        <th scope="col">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupos as $grupo)
                        <tr>
                            <td data-label="Grupo" style="font-weight:600">{{ $grupo->nombre }}</td>
                            <td data-label="Coordinador">{{ $grupo->coordinador ?? 'Sin coordinador' }}</td>
                            <td data-label="Título">{{ $grupo->titulo ?? 'Sin título' }}</td>
                            <td data-label="Estado">{{ $grupo->estado ?? '—' }}</td>
                            <td data-label="Avances">{{ $grupo->entregados }} de 3</td>
                            <td data-label="Acción">
                                <a
                                    class="if-button-secondary"
                                    style="padding:0 12px;min-height:38px;font-size:12px"
                                    href="{{ route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo->id_grupo]) }}"
                                >
                                    Ver grupo
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $grupos->links() }}
        </div>
    @endif
</div>