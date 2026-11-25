<x-app-layout>
<section class="quality-page gh-ui" style="max-width:100rem;margin:auto;padding:1.5rem">
    @include('modules.gestion-horarios.ui-estilos')
    <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.index') }}">Gestión de Horarios</a> / Panel</nav>
    <header class="quality-page-heading"><h1>Panel de ambientes</h1><p>Ocupación programada de aulas y laboratorios.</p></header>
    @include('modules.gestion-horarios.navegacion-lectura')
    <form method="get" action="{{ route('gestion-horarios.panel') }}" class="gh-ui-context">
        <label>Periodo académico<select class="quality-select" name="periodo"><option value="">Periodo vigente automáticamente</option>@foreach($periodos as $p)<option value="{{ $p->id_periodo }}" @selected((string)request('periodo')===(string)$p->id_periodo)>{{ $p->codigo }}</option>@endforeach</select></label>
        <div class="gh-ui-actions"><button type="submit">Consultar</button><button type="button" onclick="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()">Pantalla completa</button></div>
    </form>
    @error('periodo')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
    <p id="gh-panel-aviso" role="status" hidden class="quality-alert">No se pudo actualizar el panel. Se muestran los últimos datos recibidos; compruebe la conexión.</p>
    <div id="gh-panel-datos">@include('modules.gestion-horarios.panel-ocupacion-contenido')</div>
    <p><small>Actualización automática cada 30 segundos. El estado se basa en la programación publicada, no en sensores de presencia.</small></p>
</section>
<script>
(() => {
    const destino=document.getElementById('gh-panel-datos'), aviso=document.getElementById('gh-panel-aviso');
    const selector=document.querySelector('select[name=periodo]');
    let ocupado=false;
    async function actualizar(){
        if(ocupado || document.hidden) return;
        ocupado=true;
        const abortar=new AbortController(); const limite=setTimeout(()=>abortar.abort(),15000);
        try {
            const url=new URL(window.location.href);url.searchParams.set('fragmento','1');
            const r=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:abortar.signal,headers:{'Accept':'text/html'}});
            if(!r.ok||r.redirected) throw new Error('Sin actualización');
            const texto=await r.text();
            const doc=new DOMParser().parseFromString(texto,'text/html');
            const datos=doc.querySelector('[data-gh-panel-publicado]');
            if(!datos) throw new Error('Respuesta inesperada');
            destino.replaceChildren(datos);aviso.hidden=true;
            if(selector && datos.dataset.periodos){
                const elegido=selector.value, periodos=JSON.parse(datos.dataset.periodos);
                selector.replaceChildren(new Option('Periodo vigente automáticamente',''));
                for(const p of periodos) selector.add(new Option(p.codigo,p.id));
                selector.value=periodos.some(p=>p.id===elegido)?elegido:'';
                if(elegido && selector.value!==elegido){const limpia=new URL(location.href);limpia.searchParams.delete('periodo');history.replaceState(null,'',limpia);}
            }
        }catch(e){aviso.hidden=false;}finally{clearTimeout(limite);ocupado=false;}
    }
    setInterval(actualizar,30000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden) actualizar()});
})();
</script>
</x-app-layout>
