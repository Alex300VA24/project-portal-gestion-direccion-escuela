<div data-gh-panel-publicado data-periodos="{{ json_encode($periodos->map(fn($p)=>['id'=>(string)$p->id_periodo,'codigo'=>$p->codigo])->values(),JSON_THROW_ON_ERROR) }}">
    @if($publicacionQa??false)<p class="gh-ui-note">Vista previa de publicación local.</p>@endif
    @php($d = \App\Modules\GestionHorarios\Services\DistribucionHoraria::class)
    <div class="quality-panel" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1rem;margin:1rem 0">
        <div><h2>{{ $periodo?->codigo ?? 'Sin periodo publicado' }}</h2><p>{{ $ahora->locale('es')->translatedFormat('l, d \d\e F \d\e Y') }}</p></div>
        <div style="text-align:right"><strong style="font-size:2rem;font-variant-numeric:tabular-nums">{{ $ahora->format('h:i a') }}</strong><p>Hora de Perú · Última consulta {{ $ahora->format('H:i:s') }}</p></div>
    </div>
    @if($periodo && !$enVigencia)<p class="quality-alert" role="status">El periodo seleccionado está fuera de vigencia. No se muestra ocupación actual.</p>@endif
    @if(!$tarjetas)<p class="quality-panel" style="padding:1rem" role="status">No hay ambientes con horarios publicados para este periodo.</p>@else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:1rem">
            @foreach($tarjetas as $a)
            <article class="quality-panel" style="padding:1rem;border-top:4px solid {{ $a['actuales']?'var(--portal-warning,#8a3f06)':'var(--portal-green,#0d7a50)' }}">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap"><h2>{{ $a['nombre'] }}</h2><span class="quality-status {{ $a['actuales'] ? 'is-warning' : 'is-neutral' }}">{{ $a['estado'] }}</span></div>
                @foreach($a['actuales'] as $s)
                    <div style="background:#fcefdf;color:#7b3906;padding:.7rem;margin:.5rem 0;border-radius:6px"><strong>{{ $s->curso_nombre }}</strong><p>{{ $d::rango12($s->inicio,$s->fin) }} · Ciclo {{ $s->ciclo }} / {{ $s->seccion }}</p><p>{{ $s->docente }}</p></div>
                @endforeach
                @if($a['proximas'])<h3 style="margin-top:1rem">Siguiente clase</h3>@foreach($a['proximas'] as $s)<p><strong>{{ $s->curso_nombre }}</strong><br>{{ $d::rango12($s->inicio,$s->fin) }} · Ciclo {{ $s->ciclo }} / {{ $s->seccion }}</p>@endforeach
                @elseif($enVigencia)<p>No hay otra clase programada hoy.</p>@endif
            </article>
            @endforeach
        </div>
    @endif
</div>
