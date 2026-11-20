{{--
  Contenido de la ventana «Ver respuesta» de una empresa (opinión sobre un egresado o encuesta general).
  Variable: $r (ver RespuestasParaPersonal::deEmpresa). Solo se muestran los datos que la empresa respondió.
--}}
@php
  $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : null;
  $habilidad = fn (array $h) => $h['codigo'] === 'OTRA' && $h['detalle_otro'] ? $h['nombre'].': '.$h['detalle_otro'] : $h['nombre'];
  $deEgresado = $r['contexto'] === 'DESEMPENO';
  $filas = array_filter([
    'Egresado evaluado' => $deEgresado ? $r['egresado'] : null,
    'Quién responde' => $r['cargo'],
    'Sector de la empresa' => $r['sector'],
    'Satisfacción con el desempeño' => $deEgresado ? $r['satisfaccion'] : null,
    'Comentario' => $r['comentario'],
  ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="sg-resp">
  <header class="sg-resp-cab">
    <h2 id="dlg-respuesta-titulo">{{ $r['organizacion'] ?: 'Empresa' }}</h2>
    <p>{{ $deEgresado ? 'Opinión sobre un egresado' : 'Encuesta general de demanda de habilidades' }}</p>
    <p>{{ $r['campania'] }} · {{ $r['periodo'] }} · respondió el {{ $fecha($r['fecha_respuesta']) }}</p>
  </header>

  @if ($filas !== [])
    <section class="sg-resp-grupo">
      <dl class="sg-resp-dl">
        @foreach ($filas as $etiqueta => $valor)<div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>@endforeach
      </dl>
    </section>
  @endif

  @if (count($r['demandas']) || count($r['brechas']) || count($r['otras']))
    <section class="sg-resp-grupo">
      <h3>Habilidades</h3>
      @if (count($r['demandas']))<p class="sg-resp-lista"><span>Que más demanda</span>@foreach ($r['demandas'] as $h)<span class="sg-badge sg-badge-success">{{ $habilidad($h) }}</span>@endforeach</p>@endif
      @if (count($r['brechas']))<p class="sg-resp-lista"><span>Por reforzar en los egresados</span>@foreach ($r['brechas'] as $h)<span class="sg-badge sg-badge-warning">{{ $habilidad($h) }}</span>@endforeach</p>@endif
      @if (count($r['otras']))<p class="sg-resp-lista"><span>Otras</span>@foreach ($r['otras'] as $h)<span class="sg-badge">{{ $habilidad($h) }}</span>@endforeach</p>@endif
    </section>
  @endif
</div>
