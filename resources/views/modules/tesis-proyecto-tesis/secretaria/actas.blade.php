@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Actas y dictámenes</p>
            <h1>Actas y dictámenes</h1>
            <p>El sistema genera el documento. Imprímelo para la firma, sube el escaneo e informa al Director.</p>
        </div>
    </div>

    <section class="tz-panel">
        @if ($actas->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin documentos todavía', 'texto' => 'El dictamen se genera cuando los tres jurados aprueban el proyecto; el acta, al consolidar el resultado de la sustentación.'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Documento</th><th scope="col">Proyecto</th><th scope="col">Resultado</th><th scope="col">Emitido</th><th scope="col">Firmado</th><th scope="col">Director</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead>
                <tbody>
                    @foreach ($actas as $a)
                        <tr>
                            <td>{{ $a->tipo === 'Dictamen' ? 'Dictamen de aprobación' : 'Acta de sustentación' }}<div class="tz-code">{{ $a->numero }}</div></td>
                            <td class="tz-code">{{ $a->proyecto?->codigo }}</td>
                            <td>{{ $a->resultado }}</td>
                            <td>{{ $a->emitida_en?->format('d/m/Y') }}</td>
                            <td>@if ($a->id_archivo_firmado)<span class="tz-status is-success">Sí</span>@else<span class="tz-status is-warning">Pendiente</span>@endif</td>
                            <td>@if ($a->informado_director_en)<span class="tz-status is-success">Informado</span>@else<span class="tz-status is-neutral">Por informar</span>@endif</td>
                            <td><a class="tz-btn tz-btn-navy tz-btn-sm" href="{{ route('tesis-proyecto-tesis.secretaria.actas.ver', $a) }}">Abrir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
