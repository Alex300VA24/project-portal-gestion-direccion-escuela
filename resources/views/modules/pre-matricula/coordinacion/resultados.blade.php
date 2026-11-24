@extends('modules.pre-matricula.layout')

@section('title', 'Resultados oficiales')
@section('page-label', 'Resultados oficiales')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Resultados oficiales</h1><p>Sube las notas finales en Excel. Primero se valida el archivo y ves qué cambiará; recién al confirmar se guarda y se revalidan las prematrículas abiertas.</p></div>
        <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.resultados.plantilla') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>Descargar plantilla</a>
    </header>

    <div class="pm-grid">
        <div class="pm-stack">
            <section class="quality-panel" aria-labelledby="subir-titulo">
                <header class="quality-panel-heading"><div><h2 id="subir-titulo">1. Validar archivo</h2><p>Columnas: codigo_universitario, codigo_curso, codigo_periodo, nota_final, estado (APROBADO, DESAPROBADO, EN_CURSO o RETIRADO).</p></div></header>
                <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.resultados.validar') }}" enctype="multipart/form-data" x-data="{ enviando: false }" @submit="enviando = true">
                    @csrf
                    <label class="pm-field"><span>Archivo .xlsx (máximo 5 MB)</span><input type="file" name="archivo" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
                    <label class="pm-field"><span>Motivo</span><input type="text" name="motivo" maxlength="500" value="{{ old('motivo') }}" placeholder="Ej.: Actas finales 2026-I" required></label>
                    <button class="pm-button" type="submit" :disabled="enviando" x-text="enviando ? 'Validando…' : 'Validar'">Validar</button>
                </form>
            </section>

            @if ($reporte)
                <section class="quality-panel" aria-labelledby="reporte-titulo">
                    <header class="quality-panel-heading"><div><h2 id="reporte-titulo">2. Revisar y confirmar</h2><p>{{ $reporte['archivo'] }}</p></div>@if ($reporte['valido'])<span class="quality-status is-success">Sin errores</span>@else<span class="quality-status is-danger">{{ $reporte['total_errores'] }} errores</span>@endif</header>
                    <div class="pm-body">
                        <dl class="pm-facts">
                            <div><dt>Nuevos</dt><dd>{{ $reporte['resumen']['crear'] }}</dd></div>
                            <div><dt>Actualizados</dt><dd>{{ $reporte['resumen']['actualizar'] }}</dd></div>
                            <div><dt>Sin cambios</dt><dd>{{ $reporte['resumen']['sin_cambios'] }}</dd></div>
                        </dl>
                        @if (! $reporte['valido'])
                            <p>Corrige el archivo y vuelve a validarlo. No se guardará nada mientras tenga errores.</p>
                            <div class="pm-table-wrap">
                                <table class="pm-table">
                                    <thead><tr><th>Fila</th><th>Columna</th><th>Problema</th></tr></thead>
                                    <tbody>@foreach ($reporte['errores'] as $error)<tr><td class="pm-num">{{ $error['fila'] }}</td><td><span class="pm-code">{{ $error['columna'] }}</span></td><td>{{ $error['mensaje'] }}</td></tr>@endforeach</tbody>
                                </table>
                            </div>
                            @if ($reporte['total_errores'] > count($reporte['errores']))<p class="pm-muted">Se muestran los primeros {{ count($reporte['errores']) }}.</p>@endif
                        @else
                            <div class="pm-table-wrap">
                                <table class="pm-table">
                                    <thead><tr><th>Fila</th><th>Estudiante</th><th>Curso</th><th>Período</th><th>Antes</th><th>Después</th><th>Acción</th></tr></thead>
                                    <tbody>
                                        @foreach ($reporte['cambios'] as $c)
                                            <tr>
                                                <td class="pm-num">{{ $c['fila'] }}</td><td>{{ $c['codigo_universitario'] }}</td><td>{{ $c['codigo_curso'] }}</td><td>{{ $c['codigo_periodo'] }}</td>
                                                <td>{{ $c['antes'] ? ($c['antes']['estado'].($c['antes']['nota_final'] !== null ? ' · '.$c['antes']['nota_final'] : '')) : '—' }}</td>
                                                <td>{{ $c['despues']['estado'] }}{{ $c['despues']['nota_final'] !== null ? ' · '.$c['despues']['nota_final'] : '' }}</td>
                                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => $c['accion']])</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <form class="pm-actions pm-mt" method="POST" action="{{ route('pre-matricula.coordinacion.resultados.confirmar') }}" x-data="{ enviando: false }" @submit="enviando = true">
                                @csrf
                                <input type="hidden" name="sha256" value="{{ $reporte['sha256'] }}">
                                <input type="hidden" name="huella" value="{{ $reporte['huella'] }}">
                                <input type="hidden" name="motivo" value="{{ $reporte['motivo'] }}">
                                <button class="pm-button" type="submit" :disabled="enviando">Confirmar importación</button>
                                <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.resultados') }}">Descartar</a>
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        <section class="quality-panel" aria-labelledby="auditoria-titulo">
            <header class="quality-panel-heading"><div><h2 id="auditoria-titulo">Importaciones anteriores</h2><p>Cada cambio de nota queda registrado con su valor anterior.</p></div></header>
            <ol class="pm-timeline">
                @forelse ($importaciones as $i)
                    <li>
                        <a class="pm-code" href="{{ route('pre-matricula.coordinacion.resultados.importacion', $i->id_importacion) }}" aria-label="Ver los cambios de la importación {{ $i->id_importacion }}">#{{ $i->id_importacion }}</a>
                        <div><strong>{{ $i->motivo }}</strong><small>{{ $i->creado_en?->timezone('America/Lima')->translatedFormat('d M Y, H:i') }} · {{ $i->autor?->correo_institucional ?? 'usuario '.$i->creado_por }}</small><small>{{ $i->resumen['crear'] ?? 0 }} nuevos · {{ $i->resumen['actualizar'] ?? 0 }} actualizados · {{ $i->resumen['sin_cambios'] ?? 0 }} sin cambios</small></div>
                        <span></span>
                    </li>
                @empty
                    <li><span></span><span class="pm-muted">Aún no se importan resultados.</span><span></span></li>
                @endforelse
            </ol>
        </section>
    </div>
@endsection
