<x-app-layout>
<section class="quality-page gh-ui" style="max-width:96rem;width:100%;min-width:0;box-sizing:border-box;margin:auto;padding:1.5rem">
    @include('modules.gestion-horarios.ui-estilos')
    <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.index') }}">Gestión de Horarios</a> / Mi horario</nav>
    <header class="quality-page-heading"><h1>Mi horario docente</h1><p>Consulte sus clases programadas en el horario publicado.</p>@if($publicacionQa)<p class="gh-ui-note">Publicación de demostración local.</p>@endif</header>
    @include('modules.gestion-horarios.navegacion-lectura')
    <section class="quality-panel" style="padding:1rem;margin:1rem 0">
        @if($errorConsulta)<p role="alert">No se pudo consultar su horario. Vuelva a intentarlo.</p>
        @elseif(!$vinculado)<p role="status">Su cuenta aún no tiene una vinculación docente acreditada. Comuníquese con la administración.</p>
        @elseif($periodos->isEmpty())<p role="status">No hay horarios publicados para su cuenta.</p>
        @else
        <form method="get" action="{{ route('gestion-horarios.mi-horario') }}" class="gh-ui-context">
            <label>Periodo académico<select class="quality-select" name="periodo" required>@foreach($periodos as $p)<option value="{{ $p->id_periodo }}" @selected((int)($periodo?->id_periodo ?? 0)===(int)$p->id_periodo)>{{ $p->codigo }}</option>@endforeach</select></label><button class="institutional-primary" type="submit">Consultar horario</button>
        </form>
        @if(!$periodo)<p role="status">Seleccione un periodo publicado.</p>@endif
        @endif
        @error('periodo')<p role="alert">{{ $message }}</p>@enderror
    </section>
    @if($vinculado && $periodo && !$errorConsulta)
        @if(count($planos))<div class="gh-ui-actions"><a class="institutional-primary" href="{{ route('gestion-horarios.mi-horario',['periodo'=>$periodo->id_periodo,'descargar'=>'pdf']) }}">Descargar mi horario (PDF)</a></div>@endif
        @forelse($planos as $plano) @include('modules.gestion-horarios.consulta-cuadricula',['plano'=>$plano]) @empty <p class="quality-panel" style="padding:1rem" role="status">No tiene clases publicadas en este periodo.</p> @endforelse
    @endif
</section>
</x-app-layout>
