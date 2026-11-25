<div class="quality-page gh-ui" style="padding:2rem 1rem; margin:0 auto; max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Revisión y publicación', 'subtitulo'=>'Revisiones de oferta', 'descripcion'=>'Solicitudes registradas al intentar modificar ofertas con secciones relacionadas.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa' => 'revision', 'workspaceSub' => 'oferta-revision', 'workspacePlan' => $plan ?? session('gestion_horarios.workspace.plan', ''), 'workspacePeriodo' => $periodo ?? session('gestion_horarios.periodo_seleccionado', '')])
    @if ($error)
        <div class="quality-alert" role="alert">No se pudieron consultar las revisiones. Intente nuevamente o comuníquese con soporte.</div>
    @elseif ($adscripcionPendiente)
        <div class="quality-alert" role="status">Para consultar las revisiones es necesario verificar la escuela autorizada de su cuenta institucional.</div>
    @elseif (! $revisionDisponible)
        <div class="quality-alert" role="status">El registro de revisiones de oferta está pendiente de una actualización institucional del módulo.</div>
    @elseif ($revisiones->isEmpty())
        <div class="quality-empty"><h2>No hay revisiones registradas</h2><p>Las solicitudes aparecerán aquí cuando una oferta necesite revisión.</p></div>
    @else
        <section class="quality-panel" aria-label="Revisiones pendientes">
            <p style="padding:1rem">Se muestran hasta 200 registros recientes. Una revisión puede repetirse si se intentó modificar la oferta más de una vez. La resolución aún no está habilitada.</p>
            <div style="overflow-x:auto"><table style="width:100%; min-width:800px; text-align:left"><thead><tr><th>Periodo</th><th>Curso</th><th>Oferta</th><th>Motivo</th><th>Registrada</th><th>Horarios relacionados</th><th>Detalle</th></tr></thead><tbody>
                @foreach ($revisiones as $revision)
                    <tr wire:key="revision-{{ $revision->id_revision }}"><td>{{ $revision->periodo }}</td><td><span class="quality-code">{{ $revision->codigo_curso }}</span> {{ $revision->nombre }}</td><td>{{ $revision->tipo_oferta }} · {{ $revision->estado_oferta }}</td><td>{{ $revision->motivo }}</td><td>{{ \Illuminate\Support\Carbon::parse($revision->creada_en)->timezone('America/Lima')->format('d/m/Y H:i') }}</td><td>{{ $revision->estados_horario ? implode(', ', $revision->estados_horario) : 'Sin horario relacionado' }}</td><td><a class="quality-open-link" href="{{ route('gestion-horarios.oferta', ['periodo' => $revision->id_periodo, 'oferta' => $revision->id_oferta]) }}">Ver oferta</a></td></tr>
                @endforeach
            </tbody></table></div>
        </section>
    @endif
</div>
