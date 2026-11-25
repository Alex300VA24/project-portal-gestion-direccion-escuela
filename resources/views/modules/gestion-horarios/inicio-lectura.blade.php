<section class="quality-page gh-ui gh-lectura-page">
    @include('modules.gestion-horarios.ui-estilos')
    <header class="quality-page-heading">
        <span class="quality-eyebrow">Gestión de Horarios</span>
        <h1>{{ auth()->user()->hasRole('profesor','web') ? 'Mi espacio docente' : 'Consulta académica' }}</h1>
        <p>Consulte los horarios publicados por la Escuela y la programación de aulas y laboratorios.</p>
    </header>
    @include('modules.gestion-horarios.navegacion-lectura')
    <div class="gh-lectura-cards">
        @php
            $opciones = [
                ['gestion-horarios.consulta-publicados','Consulta de horarios','Revise las clases por ciclo, sección, docente o ambiente. Descargue el horario en PDF.'],
                ['gestion-horarios.panel','Panel de ambientes','Consulte las clases actuales y siguientes en aulas y laboratorios.'],
            ];
            if(auth()->user()->hasRole('profesor','web')) array_unshift($opciones,
                ['gestion-horarios.mi-horario','Mi horario','Consulte y descargue las clases asignadas a su cuenta.'],
                ['gestion-horarios.mi-disponibilidad','Mi disponibilidad','Revise sus asignaciones y franjas declaradas.']);
        @endphp
        @foreach($opciones as [$ruta,$titulo,$detalle])
            <article class="quality-panel gh-lectura-card">
                <h2>{{ $titulo }}</h2><p>{{ $detalle }}</p>
                <a class="institutional-primary" href="{{ route($ruta) }}">Abrir {{ mb_strtolower($titulo) }}</a>
            </article>
        @endforeach
    </div>
</section>
