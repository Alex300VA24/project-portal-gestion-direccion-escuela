<div class="quality-page gh-ui" style="padding:2rem 1rem;margin:0 auto;max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Recursos académicos', 'subtitulo'=>null, 'descripcion'=>'Registro general, independiente del periodo académico.'])
    @include('modules.gestion-horarios.workspace-navegacion',['workspaceEtapa'=>'recursos','workspaceSub'=>$recurso,'workspacePlan'=>$plan,'workspacePeriodo'=>session('gestion_horarios.periodo_seleccionado','')])
    <section class="quality-panel">
        <header class="quality-panel-heading"><h2>{{ ucfirst($recurso) }} · {{ $filas->count() }} resultados</h2><label>Buscar <input type="search" maxlength="120" wire:model.live.debounce.350ms="buscar" placeholder="Nombre o código (si corresponde)"></label></header>
        <p wire:loading role="status">Actualizando catálogo…</p>
        @if ($filas->isEmpty())<div class="quality-empty">No se encontraron registros.</div>@else
        <div class="gh-catalogo-tabla" role="region" tabindex="0" aria-label="Catálogo">
            <table><thead><tr>
                @if ($recurso !== 'docentes')<th>Código</th>@endif<th>Nombre</th>
                @if ($recurso === 'cursos')<th>Ciclo</th><th>Teoría</th><th>Práctica</th><th>Laboratorio</th>
                @elseif ($recurso === 'docentes')<th>Estado</th>
                @else<th>Capacidad</th><th>Estado</th><th>Acciones</th>@endif
            </tr></thead><tbody>
                @foreach ($filas as $fila)<tr>
                    @if ($recurso !== 'docentes')<td>{{ $fila->codigo }}</td>@endif<td>{{ $fila->nombre }}</td>
                    @if ($recurso === 'cursos')<td>{{ $fila->ciclo }}</td><td>{{ $fila->horas_teoria }}</td><td>{{ $fila->horas_practica }}</td><td>{{ $fila->horas_laboratorio }}</td>
                    @elseif ($recurso === 'docentes')<td>{{ $fila->estado }}</td>
                    @else<td>{{ $fila->capacidad }}</td><td>{{ $fila->estado }}</td><td><a class="quality-open-link" href="{{ route('gestion-horarios.ambientes',['tipo'=>$fila->tipo,'ambiente'=>$fila->id_ambiente,'plan'=>$plan,'periodo'=>session('gestion_horarios.periodo_seleccionado','')]) }}">Ver detalle</a></td>@endif
                </tr>@endforeach
            </tbody></table>
        </div>@endif
    </section>
    <p class="gh-ui-note">Catálogo de demostración local; consulta sin edición de los registros de Core.</p>
</div>
