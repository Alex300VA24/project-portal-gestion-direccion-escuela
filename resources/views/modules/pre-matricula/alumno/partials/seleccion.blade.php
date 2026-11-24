@forelse ($seleccion as $curso)
    <article class="pm-course">
        <div>
            <span class="pm-code">{{ $curso->codigo }}</span><strong>{{ $curso->nombre }}</strong>
            <div class="pm-meta">
                <span class="pm-chip">Ciclo {{ $curso->ciclo }}</span><span class="pm-chip">{{ $num($curso->creditos) }} créditos</span>
                @if ($validados->has($curso->id_curso_plan) && $validados[$curso->id_curso_plan]['estado'] !== 'VALIDA')@include('modules.pre-matricula.partials.estado', ['valor' => $validados[$curso->id_curso_plan]['estado']])@endif
            </div>
            @if ($validados->has($curso->id_curso_plan) && $validados[$curso->id_curso_plan]['motivo'])<small>{{ $validados[$curso->id_curso_plan]['motivo'] }}</small>@endif
        </div>
        @if ($editable)
            <form method="POST" action="{{ route('pre-matricula.alumno.retirar', $curso->id_detalle) }}">@csrf @method('DELETE')<button class="pm-button is-danger is-small" type="submit" aria-label="Quitar {{ $curso->nombre }}">Quitar</button></form>
        @endif
    </article>
@empty
    <p class="quality-list-empty pm-body">Todavía no agregas cursos.</p>
@endforelse
