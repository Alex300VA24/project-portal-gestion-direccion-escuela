@php
    $dias = collect(['LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves',
        'VIERNES' => 'Viernes', 'SABADO' => 'Sábado', 'DOMINGO' => 'Domingo'])->only($sesiones->pluck('dia_semana')->all());
    $tipos = ['TEORIA' => 'Teoría', 'PRACTICA' => 'Práctica', 'LABORATORIO' => 'Laboratorio', 'TALLER' => 'Taller'];
@endphp
<style>
    .gh-semana { width:100%; border-collapse:collapse; table-layout:fixed; text-align:left; }
    .gh-semana th,.gh-semana td { padding:.45rem; vertical-align:top; overflow-wrap:anywhere; border-bottom:1px solid var(--gh-ui-border,#dfe3ea); }
    .gh-semana thead th { background:var(--portal-navy,#17224d); color:#fff; }
    .gh-semana th:first-child { width:4.6rem; }
    .gh-semana article { padding:.4rem .55rem; border-left:3px solid #267357; margin-bottom:.4rem; background:#eaf4ed; border-radius:7px; }
    .gh-semana article[data-tipo="PRACTICA"] { border-color:#a36526; background:#f9efdf; }
    .gh-semana article[data-tipo="LABORATORIO"] { border-color:#416c9d; background:#e8eff9; }
    .gh-semana small,.gh-semana strong { display:block; }
    .gh-semana .gh-dia { display:none; }
    @@media (max-width:800px) {
        .gh-semana,.gh-semana tbody,.gh-semana tr,.gh-semana td,.gh-semana th { display:block; width:auto; }
        .gh-semana thead,.gh-semana .gh-libre { display:none; }
        .gh-semana th:first-child { width:auto; background:var(--gh-ui-soft,#eef0f3); }
        .gh-semana .gh-dia { display:block; }
    }
</style>
<table class="gh-semana" aria-label="Cuadrícula semanal de actividades">
    <thead><tr><th scope="col">Inicio</th>@foreach ($dias as $dia)<th scope="col">{{ $dia }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($sesiones->groupBy('hora_inicio')->sortKeys() as $inicio => $franja)
        <tr><th scope="row">{{ substr($inicio, 0, 5) }}</th>
            @foreach ($dias as $clave => $dia)
                @php($actividades = $franja->where('dia_semana', $clave))
                <td @class(['gh-libre' => $actividades->isEmpty()])>
                    @foreach ($actividades as $a)
                        <article data-tipo="{{ $a->tipo_actividad }}">
                            <small><span class="gh-dia">{{ $dia }}</span>{{ substr($inicio, 0, 5) }}–{{ substr($a->hora_fin, 0, 5) }}</small>
                            <strong>{{ $a->curso_nombre }}</strong>
                            <small>{{ $a->curso_codigo }} · {{ $tipos[$a->tipo_actividad] ?? $a->tipo_actividad }}</small>
                            <small>Ciclo {{ $a->ciclo }} · Sección {{ $a->seccion }}</small>
                            @unless ($planificacion ?? false)
                            <small>{{ $a->ambiente ?: 'Sin ambiente registrado' }}</small>
                            <details><summary>Detalle</summary>
                                <small>{{ $a->plan_nombre ?? 'Plan no disponible' }}</small>
                                @if (isset($docentes))
                                    @forelse ($docentes->get($a->id_actividad, collect()) as $d)
                                        <small>{{ trim($d->nombres.' '.$d->apellido_paterno.' '.$d->apellido_materno) }}</small>
                                    @empty <small>Sin asignación confirmada</small> @endforelse
                                @else
                                    <small>{{ trim(($a->nombres ?? '').' '.($a->apellido_paterno ?? '').' '.($a->apellido_materno ?? '')) ?: 'Sin asignación confirmada' }}</small>
                                    <small>{{ $a->ambito }} · versión {{ $a->version }}</small>
                                @endif
                            </details>
                            @endunless
                        </article>
                    @endforeach
                </td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
