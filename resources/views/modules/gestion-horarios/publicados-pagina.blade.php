<x-app-layout>
<section class="quality-page gh-ui" style="max-width:96rem;width:100%;min-width:0;box-sizing:border-box;margin:auto;padding:1.5rem">
    @include('modules.gestion-horarios.ui-estilos')
    @php
        $periodoActual = (string) $periodo;
        $modalidadActual = $filtrosConsulta['modalidad'] ?? request('modalidad','ciclo');
        if (!in_array($modalidadActual,['ciclo','docente','ambiente'],true)) $modalidadActual='ciclo';
        $valorActual = (string) ($filtrosConsulta['valor'] ?? request('valor',''));
        $seccionActual = (string) ($filtrosConsulta['seccion'] ?? request('seccion',''));
    @endphp
    <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.index') }}">Gestión de Horarios</a> / Consulta</nav>
    <header class="quality-page-heading"><h1>Consulta de horarios</h1><p>Consulte la programación publicada por la Escuela.</p>@if($publicacionQa??false)<p class="gh-ui-note">Vista previa de publicación local; no es una publicación institucional.</p>@endif</header>
    @include('modules.gestion-horarios.navegacion-lectura')
    <section class="quality-panel" style="padding:1rem;margin:1rem 0">
        <form method="get" action="{{ route('gestion-horarios.consulta-publicados') }}" class="gh-ui-context" style="margin:0">
            <label>Periodo académico<select class="quality-select" name="periodo" onchange="this.form.submit()"><option value="">Seleccione un periodo</option>@foreach($periodos as $p)<option value="{{ $p->id_periodo }}" @selected((string)$p->id_periodo===$periodoActual)>{{ $p->codigo }}</option>@endforeach</select></label>
            <input type="hidden" name="modalidad" value="{{ $modalidadActual }}"><div class="gh-ui-actions"><button type="submit">Seleccionar periodo</button></div>
        </form>
        <div class="quality-segment gh-lectura-nav" aria-label="Modalidad de consulta" style="margin:1rem 0">
            @foreach(['ciclo'=>'Por ciclo y sección','docente'=>'Por docente','ambiente'=>'Por ambiente'] as $m=>$label)
                <a class="gh-lectura-segmento" href="{{ route('gestion-horarios.consulta-publicados',['periodo'=>$periodoActual,'modalidad'=>$m]) }}" @if($modalidadActual===$m) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        @if($periodoActual!=='' && $catalogos['ciclos']->isNotEmpty())
        <form method="get" action="{{ route('gestion-horarios.consulta-publicados') }}" class="gh-ui-context" x-data="{ciclo:@js($valorActual),seccion:@js($seccionActual), secciones:@js($catalogos['secciones']->values()->all())}">
            <input type="hidden" name="periodo" value="{{ $periodoActual }}"><input type="hidden" name="modalidad" value="{{ $modalidadActual }}">
            @if($modalidadActual==='ciclo')
                <label>Ciclo<select class="quality-select" name="valor" required x-model="ciclo" x-on:change="seccion=''" ><option value="">Seleccione un ciclo</option>@foreach($catalogos['ciclos'] as $c)<option value="{{ $c }}" @selected((string)$c===$valorActual)>Ciclo {{ $c }}</option>@endforeach</select></label>
                <label>Sección<select class="quality-select" name="seccion" x-model="seccion"><option value="">Todas las secciones</option><template x-for="sec in secciones.filter(s=>String(s.ciclo)===String(ciclo))" x-bind:key="String(sec.ciclo)+'|'+sec.codigo"><option x-bind:value="sec.codigo" x-text="sec.codigo" x-bind:selected="seccion===sec.codigo"></option></template></select></label>
            @elseif($modalidadActual==='docente')
                @include('modules.gestion-horarios.selector-consulta',['opciones'=>$catalogos['docentes'],'etiqueta'=>'Docente','placeholder'=>'Seleccione un docente'])
            @else
                @include('modules.gestion-horarios.selector-consulta',['opciones'=>$catalogos['ambientes'],'etiqueta'=>'Ambiente','placeholder'=>'Buscar aula o laboratorio'])
            @endif
            <div class="gh-ui-actions"><button type="submit" class="institutional-primary">Consultar horario</button></div>
        </form>
        @elseif($periodoActual!=='')<p role="status">Este periodo no tiene actividades publicadas.</p>
        @else<div class="quality-empty"><h2>No hay horarios publicados disponibles</h2><p>Cuando la Escuela publique un horario, podrá consultarlo aquí y descargarlo en PDF.</p></div>@endif
        @foreach(['periodo','modalidad','valor','seccion'] as $campo) @error($campo)<p class="quality-alert" role="alert">{{ $message }}</p>@enderror @endforeach
    </section>
    @if(isset($planos) && count($planos))
        <div class="gh-ui-actions gh-descarga-publicada">
            <a class="institutional-primary" href="{{ route('gestion-horarios.consulta-publicados', ['periodo'=>$periodoActual,'modalidad'=>$modalidadActual,'valor'=>$valorActual,'seccion'=>$seccionActual,'descargar'=>'pdf']) }}">Descargar PDF</a>
            <span>Horario publicado · A4 horizontal</span>
        </div>
    @endif
    @if(isset($planos))
        @forelse($planos as $plano) @include('modules.gestion-horarios.consulta-cuadricula',['plano'=>$plano]) @empty <p class="quality-panel" style="padding:1rem">No hay sesiones para los filtros seleccionados.</p> @endforelse
    @endif
    <details class="quality-panel" style="padding:1rem;margin:1rem 0" @if(!isset($planos)) open @endif>
        <summary style="font-weight:700;cursor:pointer">Versiones publicadas</summary>
        @if($horarios->isEmpty())<p role="status">No se encontraron horarios publicados.</p>@else
        <div style="overflow:auto"><table style="width:100%;text-align:left"><thead><tr><th>Periodo</th><th>Ámbito</th><th>Versión</th><th>Publicación (hora Perú)</th></tr></thead><tbody>
        @foreach($horarios as $h)<tr><td>{{ $periodos->firstWhere('id_periodo',$h->id_periodo)?->codigo ?? '—' }}</td><td>{{ $h->ambito }}</td><td>V{{ $h->version }}</td><td>{{ \Illuminate\Support\Carbon::parse($h->publicado_en)->timezone('America/Lima')->format('d/m/Y H:i') }}</td></tr>@endforeach
        </tbody></table></div>{{ $horarios->withQueryString()->links() }}@endif
    </details>
</section>
</x-app-layout>
