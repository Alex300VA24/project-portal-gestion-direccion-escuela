@php
    $d = \App\Modules\GestionHorarios\Services\DistribucionHoraria::class;
    $receso = !collect($plano['sesiones'])->contains(fn($s)=>$s['inicio']<840 && $s['fin']>780);
    // Mantener los límites horarios y ampliar únicamente las franjas densas.
    $alturas=[]; $anchos=[];
    for($h=$plano['desde'];$h<$plano['hasta'];$h+=60) $alturas[$h]=$h===780 && $receso ? 38 : 64;
    foreach($plano['dias'] as $dia) {
        $slots=max(1,...array_merge([1],array_column($plano['tarjetas'][$dia],'slots')));
        $anchos[$dia]=max(174, 164*$slots);
        foreach($plano['tarjetas'][$dia] as $t) {
            $necesario=6;
            foreach($t['items'] as $s) {
                $campos=[$s['codigo'].' · '.$s['curso'],$s['tipo'].' '.$s['grupo'],$s['ambiente'],$s['docente']];
                if($plano['alcance']) $campos[]=$s['alcance_sesion'];
                $lineas=1; // hora de la sesión
                foreach($campos as $campo) $lineas+=max(1,(int)ceil(strlen($campo)/18));
                $necesario+=16*$lineas+24;
            }
            $partes=[]; $actual=0;
            foreach($alturas as $h=>$px) {
                $fraccion=max(0,min($h+60,$t['fin'])-max($h,$t['inicio']))/60;
                if($fraccion>0) { $partes[$h]=$fraccion; $actual+=$px*$fraccion; }
            }
            if($actual<$necesario && $partes) {
                $extra=ceil(($necesario-$actual)/array_sum($partes));
                foreach($partes as $h=>$fraccion) $alturas[$h]+=$extra;
            }
        }
    }
    $posicion=static function(int $minuto) use($alturas): float {
        $px=0; foreach($alturas as $h=>$alto) $px+=$alto*max(0,min(60,$minuto-$h))/60;
        return $px;
    };
    $alto=array_sum($alturas);
    $columnas='112px '.implode(' ',array_map(fn($a)=>'minmax('.$a.'px,1fr)',$anchos));
    $diasLegibles=['MIERCOLES'=>'MIÉRCOLES','SABADO'=>'SÁBADO'];
    $romanos=[1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X'];
@endphp
@once
<style>
.gh-consulta-plano {min-width:0;max-width:100%;padding:1rem;margin:1rem 0}
.gh-consulta-scroll {max-width:100%;overflow-x:auto;outline-offset:3px;border:1px solid var(--gh-ui-border,#dfe3ea);border-radius:8px}
.gh-consulta-grid {display:grid;grid-template-rows:42px auto;position:relative;background:var(--gh-ui-surface,#fff);isolation:isolate}
.gh-consulta-grid * {box-sizing:border-box}
.gh-consulta-cab {display:flex;align-items:center;justify-content:center;background:var(--portal-navy,#17224d);color:#fff;font-size:.78rem;font-weight:700;letter-spacing:.02em;border-right:1px solid #ffffff40;min-width:0}
.gh-consulta-hora {display:flex;align-items:center;justify-content:center;font-size:.78rem;font-variant-numeric:tabular-nums;border-bottom:1px solid var(--gh-ui-border,#dfe3ea);background:var(--gh-ui-soft,#eef0f3);color:var(--gh-ui-ink,#141a33)}
.gh-consulta-eje {position:sticky;left:0;z-index:4;background:var(--gh-ui-soft,#eef0f3);box-shadow:1px 0 var(--gh-ui-border,#dfe3ea)}
.gh-consulta-dia {position:relative;min-width:0;border-left:1px solid var(--gh-ui-border,#dfe3ea)}
.gh-consulta-franja {border-bottom:1px solid var(--gh-ui-border,#dfe3ea);background:var(--gh-ui-surface,#fff)}
.gh-consulta-franja:nth-child(odd) {background:var(--gh-ui-soft,#eef0f3)}
.gh-consulta-grupo {position:absolute;display:flex;flex-direction:column;gap:3px;padding:2px;min-width:0;z-index:1}
.gh-consulta-tarjeta {flex:1 1 auto;min-height:0;overflow:auto;padding:6px 7px;border:1px solid var(--tono);border-left:4px solid var(--tono);border-radius:5px;background:var(--fondo);color:#142d24;font-size:.78rem;line-height:1.3;overflow-wrap:break-word}
.gh-consulta-tarjeta strong,.gh-consulta-tarjeta span {display:block}
.gh-consulta-tarjeta small {display:block;font-size:.73rem}
.gh-consulta-receso {align-self:start;z-index:2;pointer-events:none;display:grid;place-items:center;background:var(--gh-ui-soft,#eef0f3);color:var(--gh-ui-muted,#4a5170);font-weight:700;border-bottom:1px solid var(--gh-ui-border,#dfe3ea)}
.gh-consulta-leyenda {display:flex;flex-wrap:wrap;gap:.8rem;margin:.7rem 0;font-size:.8rem;color:var(--gh-ui-muted,#4a5170)}
.gh-consulta-leyenda i {display:inline-block;width:.7rem;height:.7rem;border-radius:2px;margin-right:.25rem}
</style>
@endonce
<section class="quality-panel gh-consulta-plano">
    <h2>{{ $plano['alcance'] ?: 'Ciclo '.($romanos[(int)$plano['ciclo']]??$plano['ciclo']).' · Sección '.$plano['seccion'] }}</h2>
    <p>{{ $plano['plan_nombre'] }}</p>
    <div class="gh-consulta-leyenda" aria-label="Tipos de actividad"><span><i style="background:#267357"></i>Teoría</span><span><i style="background:#9c721e"></i>Práctica</span><span><i style="background:#416c9d"></i>Laboratorio</span></div>
    <div class="gh-consulta-scroll" role="region" aria-label="Horario semanal desplazable" tabindex="0">
        <div class="gh-consulta-grid" style="grid-template-columns:{{ $columnas }};min-width:{{ 112+array_sum($anchos) }}px">
            <div class="gh-consulta-cab gh-consulta-eje" style="grid-column:1;grid-row:1;background:var(--portal-navy,#17224d)">HORA</div>
            @foreach($plano['dias'] as $dia)
                <div class="gh-consulta-cab" style="grid-column:{{ $loop->index+2 }};grid-row:1">{{ $diasLegibles[$dia]??$dia }}</div>
            @endforeach
            <div class="gh-consulta-eje" style="grid-column:1;grid-row:2;height:{{ $alto }}px">
                @foreach($alturas as $h=>$px)<div class="gh-consulta-hora" style="height:{{ $px }}px">{{ $d::rango12($h,$h+60) }}</div>@endforeach
            </div>
            @foreach($plano['dias'] as $dia)
            <div class="gh-consulta-dia" style="grid-column:{{ $loop->index+2 }};grid-row:2;height:{{ $alto }}px">
                @foreach($alturas as $px)<div class="gh-consulta-franja" style="height:{{ $px }}px" aria-hidden="true"></div>@endforeach
                @foreach($plano['tarjetas'][$dia] as $t)
                <div class="gh-consulta-grupo" style="top:{{ $posicion($t['inicio']) }}px;height:{{ $posicion($t['fin'])-$posicion($t['inicio']) }}px;left:{{ $t['lane']*100/$t['slots'] }}%;width:{{ 100/$t['slots'] }}%">
                    @foreach($t['items'] as $s)
                        @php($color=\App\Modules\GestionHorarios\Services\PlanoAcademicoA4::colorActividad($s['tipo']))
                        <article class="gh-consulta-tarjeta" style="--tono:#{{ $color[1] }};--fondo:#{{ $color[0] }}">
                            <small>{{ $d::rango12($s['inicio'],$s['fin']) }}</small>
                            <strong>{{ $s['codigo'] }} · {{ $s['curso'] }}</strong>
                            <span>{{ $s['tipo'] }} @if($s['grupo']) · {{ $s['grupo'] }} @endif</span>
                            @if($plano['alcance'])<span>{{ $s['alcance_sesion'] }}</span>@endif
                            <span>{{ $s['ambiente'] }}</span><span>{{ $s['docente'] }}</span>
                        </article>
                    @endforeach
                </div>
                @endforeach
            </div>
            @endforeach
            @if($receso && isset($alturas[780]))
            <div class="gh-consulta-receso" style="grid-column:2/-1;grid-row:2;margin-top:{{ $posicion(780) }}px;height:{{ $alturas[780] }}px">RECESO</div>
            @endif
        </div>
    </div>
    <small>Desplace la cuadrícula horizontalmente si necesita ver los demás días.</small>
</section>
