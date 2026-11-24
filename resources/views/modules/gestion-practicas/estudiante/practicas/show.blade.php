@php
    use App\Modules\GestionPracticas\Models\Practica;

    $corregible = in_array($practica->estado, [Practica::REGISTRADA, Practica::OBSERVADA], true);
    $tiposSubida = $practica->estado === Practica::EN_CURSO
        ? \Illuminate\Support\Arr::only($tiposDocumento, ['informe_parcial', 'informe_final', 'constancia', 'otro'])
        : \Illuminate\Support\Arr::only($tiposDocumento, ['carta_aceptacion', 'plan_practica', 'otro']);
    $puedeSubir = $corregible || $practica->estado === Practica::EN_CURSO;
@endphp
@extends('modules.gestion-practicas.layout')

@section('title', 'Mi práctica')
@section('page-label', 'Mi práctica')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Mi práctica en {{ $empresa }}</h1><p>{{ $practica->area }}</p></div>
        <div class="gp-actions">
            @include('modules.gestion-practicas.partials.volver', ['ruta' => route('gestion-practicas.estudiante.practicas.index'), 'texto' => 'mis prácticas'])
            @if ($practica->estado === Practica::FINALIZADA)
                <dl class="quality-inline-stats" aria-label="Resultado"><div><dt>Nota final · {{ $practica->resultado }}</dt><dd>{{ $practica->nota_final }}</dd></div></dl>
            @endif
            @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
        </div>
    </header>

    <div class="gp-stack">
        <section class="quality-panel gp-compact" aria-labelledby="detalle-titulo">
            <header class="quality-panel-heading"><div><h2 id="detalle-titulo">Datos de la práctica</h2></div></header>
            <div class="quality-compact-list">
                <article><div><small>Área</small><strong>{{ $practica->area }}</strong></div></article>
                <article><div><small>Horas planificadas</small><strong>{{ $practica->horas_totales }}</strong></div></article>
                <article><div><small>Inicio</small><strong>{{ $practica->fecha_inicio->format('d/m/Y') }}</strong></div></article>
                <article><div><small>Fin</small><strong>{{ $practica->fecha_fin->format('d/m/Y') }}</strong></div></article>
                <article><div><small>Docente supervisor</small><strong>{{ $supervisor ?? 'Aún sin asignar' }}</strong></div></article>
            </div>
            <div class="gp-body"><h3>Funciones</h3><p class="gp-text">{{ $practica->funciones }}</p></div>
            @if ($corregible)
                <div class="gp-body gp-actions">
                    <a class="institutional-secondary" href="{{ route('gestion-practicas.estudiante.practicas.edit', $practica) }}">Corregir datos</a>
                    <form method="POST" action="{{ route('gestion-practicas.estudiante.practicas.enviar', $practica) }}">@csrf<button class="institutional-primary">Enviar información para revisión</button></form>
                </div>
            @endif
        </section>

        @if ($observaciones->isNotEmpty())
            <section class="quality-panel" aria-labelledby="obs-titulo">
                <header class="quality-panel-heading"><div><h2 id="obs-titulo">Observaciones del administrador</h2><p>Corrija lo pendiente y vuelva a enviar la información.</p></div></header>
                <ul class="gp-timeline">
                    @foreach ($observaciones as $observacion)
                        <li @class(['is-pending' => $observacion->estado === 'pendiente'])>
                            <p>{{ $observacion->descripcion }}</p>
                            <small>{{ $observacion->registrado_en->format('d/m/Y H:i') }} @include('modules.gestion-practicas.partials.estado', ['estado' => $observacion->estado])</small>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="quality-panel" aria-labelledby="docs-titulo">
            <header class="quality-panel-heading"><div><h2 id="docs-titulo">Documentos</h2><p>Carta de aceptación e informes de la práctica.</p></div></header>
            @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => false])
            @if ($puedeSubir)
                <form method="POST" enctype="multipart/form-data" action="{{ route('gestion-practicas.documentos.store', $practica) }}" class="gp-form is-two">
                    @csrf
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'tipo_documento', 'label' => 'Tipo de documento', 'tipo' => 'select', 'opciones' => $tiposSubida])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'archivo', 'label' => 'Archivo', 'tipo' => 'file', 'ayuda' => 'Máximo 10 MB.'])
                    <div class="gp-span"><button class="institutional-primary">Subir documento</button></div>
                </form>
            @endif
        </section>

        @if ($seguimientos->isNotEmpty())
            <section class="quality-panel" aria-labelledby="seg-titulo">
                <header class="quality-panel-heading"><div><h2 id="seg-titulo">Seguimiento del supervisor</h2></div></header>
                <ul class="gp-timeline">
                    @foreach ($seguimientos as $seguimiento)
                        <li>
                            <p>{{ $seguimiento->descripcion }}</p>
                            @if ($seguimiento->observaciones)<p>{{ $seguimiento->observaciones }}</p>@endif
                            <small>{{ $seguimiento->fecha->format('d/m/Y') }} · {{ str_replace('_', ' ', $seguimiento->tipo) }}</small>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
