{{--
  Panel de gráfico; sus datos exactos se abren en un modal.
  Recibe: id, titulo, nota (N y cobertura), config (Chart), columnas, filas; opcional: detalle (filtros), vacio (título y mensaje), alto (px).
--}}
@php
  $sinDatos = $vacio ?? null;
  $contexto = $detalle ?? $nota;
@endphp
<section class="sg-panel sg-graf" aria-labelledby="{{ $id }}-encabezado">
  <h3 id="{{ $id }}-encabezado">{{ $titulo }}</h3>
  @unless ($sinDatos)<p class="sg-graf-nota">{{ $nota }}</p>@endunless

  @if ($sinDatos)
    <div class="sg-graf-vacio">
      <strong>{{ $sinDatos[0] }}</strong>
      <span>{{ $sinDatos[1] }}</span>
    </div>
  @else
    <div class="sg-graf-lienzo" style="min-height: {{ $alto ?? 260 }}px" hidden>
      <canvas id="{{ $id }}" role="img" aria-label="{{ $titulo }}. {{ $nota }}. Los valores exactos están en Ver datos."
              data-sg-grafico="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}"></canvas>
    </div>
    <button class="sg-graf-ver" type="button" data-sg-abrir-modal="{{ $id }}-datos" hidden>Ver datos</button>
    <dialog class="sg-modal" id="{{ $id }}-datos" aria-labelledby="{{ $id }}-datos-titulo">
      @include('modules.seguimiento-egresado.partials.modal-apertura', ['id' => $id.'-datos', 'titulo' => $titulo, 'detalle' => $nota === $contexto ? $nota : $nota.' · '.$contexto])
      <div class="sg-modal-cuerpo">
        <div class="sg-table-wrap" tabindex="0" role="region" aria-label="Datos: {{ $titulo }}">
          <table class="sg-table">
            <caption class="sg-graf-oculto">{{ $titulo }}. {{ $contexto }}</caption>
            <thead>
              <tr>
                @foreach ($columnas as $i => $columna)
                  <th scope="col" @class(['sg-numeric' => $i > 0])>{{ $columna }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach ($filas as $fila)
                <tr>
                  @foreach ($fila as $i => $celda)
                    @if ($i === 0)
                      <th scope="row">{{ $celda }}</th>
                    @else
                      <td class="sg-numeric">{{ $celda }}</td>
                    @endif
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </dialog>
  @endif
</section>
