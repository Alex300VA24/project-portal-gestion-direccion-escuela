<nav aria-label="Espacios de Investigación Formativa" class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
    <a
        class="text-sm font-semibold text-indigo-700 hover:text-indigo-900"
        href="{{ route('investigacion-formativa.index') }}"
    >
        Investigación Formativa
    </a>

    <ul class="mt-3 flex flex-wrap gap-3 text-sm" role="list">
        @if (auth()->user()->hasRole('director_escuela'))
            <li>
                <a class="text-gray-700 hover:text-indigo-700" href="{{ route('investigacion-formativa.director.index') }}">
                    Dirección de escuela
                </a>
            </li>
        @endif

        @if (auth()->user()->hasRole('profesor'))
            <li>
                <a class="text-gray-700 hover:text-indigo-700" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                    Docencia
                </a>
            </li>
        @endif

        @if (auth()->user()->hasRole('alumno'))
            <li>
                <a class="text-gray-700 hover:text-indigo-700" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                    Estudiante
                </a>
            </li>
        @endif
    </ul>
</nav>
