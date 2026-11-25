<nav class="quality-segment gh-lectura-nav" aria-label="Consultas académicas">
    @php
        $enlaces = ['gestion-horarios.consulta-publicados'=>'Consulta de horarios','gestion-horarios.panel'=>'Panel de ambientes'];
        if(auth()->user()?->hasRole('profesor','web')) $enlaces = ['gestion-horarios.mi-horario'=>'Mi horario','gestion-horarios.mi-disponibilidad'=>'Mi disponibilidad'] + $enlaces;
    @endphp
    @foreach($enlaces as $ruta=>$etiqueta)
        <a href="{{ route($ruta) }}" @if(request()->routeIs($ruta, $ruta.'.legado')) aria-current="page" @endif>{{ $etiqueta }}</a>
    @endforeach
</nav>
