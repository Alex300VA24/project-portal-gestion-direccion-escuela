<main class="quality-page gh-ui" style="padding:2rem 1rem;margin:0 auto;max-width:1180px">
    @include('modules.gestion-horarios.ui-estilos')
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Revisión y publicación','subtitulo'=>'Revisión del horario','descripcion'=>'Consulte el horario de trabajo, sus revisiones y exportaciones.'])
    @include('modules.gestion-horarios.workspace-navegacion', ['workspaceEtapa'=>'revision','workspaceSub'=>'trabajo','workspacePlan'=>$plan,'workspacePeriodo'=>$periodo])
    @if (!$actual)
        <div class="quality-empty"><h2>No hay horario registrado</h2><a class="institutional-primary" href="{{ route('gestion-horarios.borradores-qa',['plan'=>$plan,'periodo'=>$periodo]) }}">Abrir horario de trabajo</a></div>
    @else
        <section class="quality-panel" style="padding:1.25rem">
            <h2>{{ $actual['nombre'] }} · 2026-II · V{{ $actual['version'] }}</h2>
            <p>Horario de trabajo de demostración. La publicación institucional es independiente.</p>
            <div class="gh-ui-metrics"><div class="gh-ui-metric"><strong>{{ count($actual['datos']['sesiones']) }}</strong><small>Sesiones programadas</small></div><div class="gh-ui-metric"><strong>{{ count($actual['datos']['actividades'])-count($actual['datos']['sesiones']) }}</strong><small>Sesiones pendientes</small></div></div>
            <div class="gh-ui-actions">
                <a class="institutional-primary" href="{{ route('gestion-horarios.borradores-qa',['plan'=>$plan,'periodo'=>$periodo]) }}">Abrir horario</a>
                @if (count($actual['datos']['sesiones']))
                    <a class="institutional-secondary" href="{{ route('gestion-horarios.exportar-trabajo-qa',['version'=>$actual['version'],'formato'=>'pdf']) }}">Descargar PDF</a>
                    <a class="institutional-secondary" href="{{ route('gestion-horarios.exportar-trabajo-qa',['version'=>$actual['version'],'formato'=>'xlsx']) }}">Descargar Excel</a>
                @endif
            </div>
            <details class="gh-ui-group"><summary>Historial de revisiones ({{ $versiones->count() }})</summary><div class="gh-catalogo-tabla"><table><thead><tr><th>Versión</th><th>Nombre</th><th>Fecha de registro</th><th>Responsable</th><th>Acciones</th></tr></thead><tbody>
                @foreach ($versiones as $v)<tr><td>V{{ $v->version }} @if ((int)$v->version === $actual['version']) · Vigente @endif</td><td>{{ $v->nombre }}</td><td>{{ \Illuminate\Support\Carbon::parse($v->creado_en)->timezone('America/Lima')->format('d/m/Y H:i') }}</td><td>{{ $v->actor_qa }}</td><td><a class="institutional-secondary" href="{{ route('gestion-horarios.borradores-qa',['plan'=>$plan,'periodo'=>$periodo,'version'=>$v->version]) }}">Consultar</a></td></tr>@endforeach
            </tbody></table></div></details>
        </section>
        @if($cicloDisponible)
            <section class="quality-panel gh-ciclo-vida" style="padding:1rem;margin-top:1rem">
                <h2>Aprobación y publicación</h2>
                <p>Versión aprobada: {{ $aprobada ? 'V'.$aprobada->version : 'Sin aprobación' }} · Publicación vigente: {{ $publicada ? 'V'.$publicada->version : 'Sin publicación' }}</p>
                @if(session('gh_aviso'))<p role="status" class="gh-operacion-exito">{{ session('gh_aviso') }}</p>@endif
                @if($errors->any())
                    <div role="alert" class="gh-operacion-error">
                        <strong>{{ match(session('gh_accion_fallida')) {'aprobar'=>'No se pudo aprobar el horario','publicar'=>'No se pudo publicar el horario','retirar'=>'No se pudo retirar la publicación',default=>'Revise los datos de la operación'} }}</strong>
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <div x-data="{ accion: '', version: 0, titulo: '', descripcion: '', motivo: '', enviado: false }">
                    <div class="gh-ui-actions" style="flex-wrap:wrap;margin-top:.6rem">
                        <button type="button" class="institutional-secondary" @disabled($aprobada && (int)$aprobada->version===$actual['version'])
                            x-on:click="accion='aprobar'; version={{ (int)$actual['version'] }}; titulo='Aprobar horario'; descripcion='Se verificará la versión vigente. La aprobación no publica el horario.'; motivo=''; enviado=false; $refs.confirmacion.showModal()">Aprobar horario</button>
                        <button type="button" class="institutional-primary" @disabled(!$aprobada || (int)$aprobada->version!==$actual['version'] || ($publicada && (int)$publicada->version===$actual['version']))
                            x-on:click="accion='publicar'; version={{ (int)$actual['version'] }}; titulo='{{ $publicada ? 'Actualizar publicación' : 'Publicar horario' }}'; descripcion='La versión aprobada estará disponible en Consulta y Panel de demostración.'; motivo=''; enviado=false; $refs.confirmacion.showModal()">{{ $publicada ? 'Actualizar publicación' : 'Publicar horario' }}</button>
                        @if($publicada)
                            <button type="button" class="institutional-secondary" x-on:click="accion='retirar'; version={{ (int)$publicada->version }}; titulo='Retirar publicación'; descripcion='La versión dejará de estar disponible en Consulta y Panel de demostración. Se conservará el historial.'; motivo=''; enviado=false; $refs.confirmacion.showModal()">Retirar publicación V{{ $publicada->version }}</button>
                        @endif
                    </div>
                    <dialog x-ref="confirmacion" class="gh-operacion-dialogo" aria-labelledby="gh-operacion-titulo" x-on:cancel="if(enviado) $event.preventDefault()">
                        <form method="post" action="{{ route('gestion-horarios.publicacion-qa.accion') }}" x-on:submit="enviado=true">
                            @csrf
                            <input type="hidden" name="accion" x-bind:value="accion">
                            <input type="hidden" name="version" x-bind:value="version">
                            <h2 id="gh-operacion-titulo" x-text="titulo"></h2>
                            <p class="gh-operacion-contexto">Periodo 2026-II · Versión <span x-text="version"></span></p>
                            <p x-text="descripcion"></p>
                            <label for="gh-operacion-motivo">Motivo de la operación</label>
                            <textarea id="gh-operacion-motivo" name="motivo" x-model="motivo" class="quality-select" maxlength="300" required rows="3" placeholder="Indique el motivo de la decisión"></textarea>
                            <p class="gh-ui-note">La decisión quedará registrada en el historial.</p>
                            <div class="gh-ui-actions gh-operacion-botones">
                                <button type="button" class="institutional-secondary" x-bind:disabled="enviado" x-on:click="$refs.confirmacion.close()">Cancelar</button>
                                <button type="submit" class="institutional-primary" x-bind:disabled="enviado || !motivo.trim()" x-text="enviado ? 'Verificando…' : titulo"></button>
                            </div>
                        </form>
                    </dialog>
                </div>
                @if($publicada)
                    <div class="gh-ui-actions"><a class="institutional-secondary" href="{{ route('gestion-horarios.consulta-publicados') }}">Consultar publicación</a><a class="institutional-secondary" href="{{ route('gestion-horarios.panel') }}">Ver panel</a></div>
                @endif
                <style>
                    .gh-ui .gh-operacion-dialogo{box-sizing:border-box;width:min(31rem,calc(100% - 2rem));max-height:calc(100dvh - 2rem);overflow:auto;border:1px solid #cbd9d0;border-radius:12px;padding:1.5rem;color:inherit;background:#fff;box-shadow:0 18px 60px #10291e33}
                    .gh-ui .gh-operacion-dialogo::backdrop{background:#142d23a8}
                    .gh-ui .gh-operacion-dialogo h2{margin:0;color:#174a39;font-size:1.25rem}
                    .gh-ui .gh-operacion-dialogo textarea{box-sizing:border-box;width:100%;margin-top:.4rem;resize:vertical}
                    .gh-ui .gh-operacion-contexto{font-size:.85rem;color:#52675b}
                    .gh-ui .gh-operacion-botones{justify-content:flex-end;flex-wrap:wrap;margin-top:1rem}
                    .gh-ui .gh-operacion-error,.gh-ui .gh-operacion-exito{padding:.8rem 1rem;margin:.75rem 0;border:1px solid;border-radius:8px}
                    .gh-ui .gh-operacion-error{background:#fff4ee;border-color:#e0ad92;color:#782f1b}
                    .gh-ui .gh-operacion-error p{margin:.35rem 0 0}
                    .gh-ui .gh-operacion-exito{background:#eef6f0;border-color:#a7c9b2;color:#174a39}
                </style>
                <details class="gh-ui-group"><summary>Auditoría de aprobación y publicación ({{ $eventos->count() }})</summary><div style="overflow:auto"><table><thead><tr><th>Versión</th><th>Acción</th><th>Responsable</th><th>Motivo</th><th>Hora Perú</th></tr></thead><tbody>@foreach($eventos as $e)<tr><td>V{{ $e->version }}</td><td>{{ $e->evento }}</td><td>{{ $e->actor }}</td><td>{{ $e->motivo }}</td><td>{{ \Illuminate\Support\Carbon::parse($e->creado_en)->timezone('America/Lima')->format('d/m/Y H:i:s') }}</td></tr>@endforeach</tbody></table></div></details>
                <p class="gh-ui-note">Solo entorno local de demostración. Una nueva versión de trabajo no reemplaza automáticamente la publicación.</p>
            </section>
        @else <p class="gh-ui-note">Prepare el almacenamiento local para aprobación y publicación.</p>@endif
    @endif
</main>
