{{--
  Contenido de la ventana «Ver respuesta» de un egresado (se pide al pulsar el botón de su fila).
  Variables: $egresado (nombre, matricula, promocion), $m (respuesta, la misma que ve el egresado en su historial).
  Solo se muestran los datos que la persona respondió.
--}}
@php
  $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : null;
  $sino = fn ($v) => $v === null ? null : ($v ? 'Sí' : 'No');
  $ingles = ['NINGUNO' => 'Ninguno', 'BASICO' => 'Básico', 'INTERMEDIO' => 'Intermedio', 'AVANZADO' => 'Avanzado'];
  $region = ['LA_LIBERTAD' => 'La Libertad', 'OTRA_REGION' => 'Otra región del Perú'];
  $habilidad = fn (array $h) => $h['codigo'] === 'OTRA' && $h['detalle_otro'] ? $h['nombre'].': '.$h['detalle_otro'] : $h['nombre'];
  $trabaja = $m['situacion_codigo'] === 'TRABAJA';
  $grupos = [
    'Situación laboral' => [
      'Situación' => $m['situacion'],
      'Trabajo relacionado con la carrera' => $trabaja ? $sino($m['trabajo_relacionado']) : null,
      'Empleador o cliente' => $trabaja ? $m['nombre_empleador_cliente'] : null,
      'Tipo de vínculo' => $trabaja ? $m['modalidad_laboral'] : null,
      'Modalidad de trabajo' => $trabaja ? $m['modalidad_trabajo'] : null,
      'Sector' => $trabaja ? $m['sector'] : null,
      'Área profesional' => $m['area_profesional'],
      'Lugar de trabajo' => $trabaja ? trim(($m['pais_empleador'] ?? '').' '.(($region[$m['region_empleo']] ?? null) ? '· '.$region[$m['region_empleo']] : ''), ' ·') : null,
      'Autorizó contactar a su empresa' => $trabaja ? $sino($m['autoriza_contacto_empresa']) : null,
      'Contacto del jefe' => $trabaja ? $m['contacto_jefe'] : null,
    ],
    'Residencia e idiomas' => [
      'Reside en' => ($m['ciudad_residencia'] ?? '') !== ''
        ? $m['ciudad_residencia'].($m['pais_residencia'] ? ' ('.$m['pais_residencia'].')' : '')
        : $m['pais_residencia'],
      'Inglés' => $ingles[$m['nivel_ingles']] ?? null,
      'Otros idiomas' => $m['otros_idiomas'],
    ],
    'Satisfacción con la formación' => [
      'Nivel' => $m['satisfaccion'],
      'Comentario' => $m['comentario'],
    ],
  ];
@endphp
<div class="sg-resp">
  <header class="sg-resp-cab">
    <h2 id="dlg-respuesta-titulo">{{ $egresado['nombre'] }}</h2>
    <p>Matrícula {{ $egresado['matricula'] }}@if ($egresado['promocion']) · Promoción {{ $egresado['promocion'] }}@endif</p>
    <p>{{ $m['campania'] }} · {{ $m['periodo'] }} · respondió el {{ $fecha($m['fecha_respuesta']) }}</p>
  </header>

  @foreach ($grupos as $titulo => $filas)
    @php $filas = array_filter($filas, fn ($v) => $v !== null && $v !== ''); @endphp
    @continue($filas === [])
    <section class="sg-resp-grupo">
      <h3>{{ $titulo }}</h3>
      <dl class="sg-resp-dl">
        @foreach ($filas as $etiqueta => $valor)<div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>@endforeach
      </dl>
    </section>
  @endforeach

  @if (count($m['brechas']) || count($m['fortalezas']))
    <section class="sg-resp-grupo">
      <h3>Habilidades</h3>
      @if (count($m['brechas']))<p class="sg-resp-lista"><span>Con formación insuficiente</span>@foreach ($m['brechas'] as $h)<span class="sg-badge sg-badge-warning">{{ $habilidad($h) }}</span>@endforeach</p>@endif
      @if (count($m['fortalezas']))<p class="sg-resp-lista"><span>Fortalezas</span>@foreach ($m['fortalezas'] as $h)<span class="sg-badge sg-badge-success">{{ $habilidad($h) }}</span>@endforeach</p>@endif
    </section>
  @endif

  @if (count($m['formaciones']))
    <section class="sg-resp-grupo">
      <h3>Formación posterior</h3>
      <ul class="sg-resp-formacion">
        @foreach ($m['formaciones'] as $f)
          <li><strong>{{ $f['tipo'] }}</strong> · {{ $f['estado'] }}@if ($f['area_formacion']) · {{ $f['area_formacion'] }}@endif @if ($f['institucion']) · {{ $f['institucion'] }}@endif</li>
        @endforeach
      </ul>
    </section>
  @endif
</div>
