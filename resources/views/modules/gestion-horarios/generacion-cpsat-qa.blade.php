<section class="quality-panel" style="padding:1.25rem;margin-top:1rem" aria-labelledby="gh-generacion-titulo" @if ($ejecucionCpsat && in_array($ejecucionCpsat['estado'], ['PENDIENTE','EJECUTANDO'], true)) wire:poll.2s @endif>
    <h2 id="gh-generacion-titulo">Generar horario</h2>
    <p>Periodo 2026-II. Una solución verificada guarda una versión solo si cambia el contenido académico. Si no hay solución, se conserva el horario.</p>
    @if (!$colaDisponible)
        <p class="gh-ui-note">El servicio de generación no está disponible. Comuníquese con soporte.</p>
    @else
        @php($activa = in_array($ejecucionCpsat['estado'] ?? '', ['PENDIENTE','EJECUTANDO'], true))
        <div class="gh-ui-context">
            <label>Tiempo de búsqueda (segundos)<input class="quality-select" type="number" min="1" max="300" step="1" wire:model="segundosCpsat" @disabled($activa)></label>
            <div class="gh-ui-actions" style="align-self:end">
                <button class="institutional-secondary" type="button" wire:click="solicitarCpsat('prevalidar')" wire:loading.attr="disabled" @disabled($activa)>Revisar datos</button>
                <button class="institutional-primary" type="button" wire:click="solicitarCpsat('generar')" wire:loading.attr="disabled" @disabled($activa)>Generar horario</button>
                <button class="institutional-secondary" type="button" wire:click="solicitarCpsat('reorganizar')" wire:confirm="¿Reorganizar el horario vigente? Se conservará la versión anterior si no hay una solución válida." wire:loading.attr="disabled" @disabled($activa)>Reorganizar horario</button>
                <button class="institutional-secondary" type="button" wire:click="$refresh">Actualizar</button>
            </div>
        </div>
        <small>150 segundos por defecto. Preparación y validación pueden añadir tiempo al presupuesto de búsqueda. </small>
        @if ($ejecucionCpsat)
            @php($r = $ejecucionCpsat['resultado'])
            @php($estadoGeneracion = \App\Modules\GestionHorarios\Services\EstadoGeneracionHorario::presentar($ejecucionCpsat))
            <div class="gh-ui-editor" aria-live="off">
                <div class="gh-ui-actions" style="justify-content:space-between;align-items:center">
                    <strong role="status" aria-live="polite">{{ $activa ? $estadoGeneracion['fase'] : $estadoGeneracion['estado'] }}</strong>
                    <span>{{ $estadoGeneracion['estado'] }}</span>
                </div>
                <div class="gh-ui-metrics">
                    <div class="gh-ui-metric"><strong style="font-variant-numeric:tabular-nums">{{ $estadoGeneracion['transcurrido'] }}</strong><small>{{ $ejecucionCpsat['estado'] === 'PENDIENTE' ? 'Tiempo en espera' : 'Tiempo transcurrido' }}</small></div>
                    <div class="gh-ui-metric"><strong>{{ $ejecucionCpsat['segundos'] }} s</strong><small>Tiempo de búsqueda configurado</small></div>
                </div>
                <p>{{ match($ejecucionCpsat['modo']) {'generar'=>'Generación del horario','reorganizar'=>'Reorganización del horario',default=>'Revisión de datos'} }}</p>
                <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;margin:.8rem 0">
                    <div><dt>Solicitud</dt><dd>{{ $estadoGeneracion['creada'] ?? '—' }}</dd></div>
                    <div><dt>Inicio</dt><dd>{{ $estadoGeneracion['inicio'] ?? '—' }}</dd></div>
                    <div><dt>Última comunicación</dt><dd>{{ $estadoGeneracion['latido'] ?? '—' }}</dd></div>
                    @if (!$activa)<div><dt>Finalización</dt><dd>{{ $estadoGeneracion['fin'] ?? '—' }}</dd></div>@endif
                </dl>
                <small>Hora local de Perú (UTC−5). El tiempo de preparación y verificación puede superar el tiempo de búsqueda configurado.</small>
                @if ($estadoGeneracion['historial'])
                    <details class="gh-ui-group" wire:key="fases-{{ $ejecucionCpsat['id'] }}"><summary>Historial de etapas ({{ count($estadoGeneracion['historial']) }})</summary>
                        <ol style="padding: .5rem 1.5rem">@foreach ($estadoGeneracion['historial'] as $evento)<li>{{ $evento['fase'] }} · {{ $evento['hora'] }}</li>@endforeach</ol>
                    </details>
                @endif
                @if ($activa)
                    @if ($ejecucionCpsat['estado'] === 'EJECUTANDO')
                        <progress aria-label="Cálculo en ejecución" style="width:100%;accent-color:#174a39"></progress>
                        <p class="gh-ui-note">Las etapas pueden repetirse. El horario sigue disponible para consulta.</p>
                        @if ($estadoGeneracion['sin_latido'])<p class="quality-alert">Sin comunicación reciente. No reenvíe la solicitud; si persiste, contacte con soporte.</p>@endif
                    @else
                        <p>La solicitud está en espera. Puede recargar sin perderla.</p>
                        <button type="button" class="institutional-secondary" wire:click="cancelarSolicitudPendiente('{{ $ejecucionCpsat['id'] }}')" wire:confirm="¿Cancelar esta solicitud en espera?" wire:loading.attr="disabled">Cancelar solicitud</button>
                    @endif
                @elseif ($ejecucionCpsat['estado'] === 'ERROR')
                    <p class="quality-alert">{{ $estadoGeneracion['cancelada'] ? 'Solicitud cancelada antes de iniciar.' : ($estadoGeneracion['interrumpida'] ? 'La ejecución fue interrumpida.' : 'No fue posible completar la operación.') }} Consulte la última versión guardada.</p>
                @else
                    <p><strong>{{ match($r['estado'] ?? '') { 'FEASIBLE'=>'Horario compatible', 'OPTIMAL'=>'Horario optimizado', 'INFEASIBLE'=>'No hay horario compatible', 'UNKNOWN'=>'Búsqueda sin resultado concluyente', 'LISTO'=>'Datos preparados', 'REQUIERE_ATENCION'=>'Datos con advertencias', 'NO_SE_PUEDE_GENERAR'=>'Datos incompletos o incompatibles', default=>'Revisión requerida' } }}</strong> · Bloqueadas: {{ $r['bloqueadas'] ?? '—' }} · Advertencias: {{ $r['advertencias'] ?? '—' }}</p>
                    @if (!empty($ejecucionCpsat['propuesta']) && $ejecucionCpsat['propuesta'] === $propuesta)
                        @if (array_key_exists('nueva_version',$r))<p>{{ $r['nueva_version'] ? 'Nueva versión guardada.' : 'Sin cambios de contenido: se conserva la versión vigente.' }} V{{ $r['version'] ?? '' }}</p>@endif
                        <button type="button" class="institutional-primary" wire:click="abrir('{{ $ejecucionCpsat['propuesta'] }}')">Abrir última versión</button>
                    @elseif (!empty($ejecucionCpsat['propuesta']))<p>Resultado de un horario anterior conservado como archivo.</p>
                    @elseif (($r['estado'] ?? '') === 'INFEASIBLE')<p>No existe un horario compatible con las condiciones actuales.</p>
                    @elseif (($r['estado'] ?? '') === 'NO_SE_PUEDE_GENERAR')<p>La preparación contiene bloqueos que impiden el cálculo.</p>
                    @elseif (in_array($r['estado'] ?? '', ['MODEL_INVALID','INPUT_INVALID'], true))<p>No fue posible interpretar los datos académicos.</p>
                    @elseif (($r['estado'] ?? '') === 'UNKNOWN')<p>No se encontró una solución dentro del tiempo de búsqueda. Esto no demuestra que sea imposible.</p>@endif
                    @foreach (($r['deficit_docente_previo'] ?? []) as $d)
                        <details class="gh-ui-group"><summary>{{ $nombresDocentes[$d['docente']] ?? 'Docente sin nombre conciliado' }} · déficit {{ $d['deficit'] }} h</summary><div style="padding:.8rem"><p>Carga: {{ $d['carga'] }} h; compatibles: {{ $d['horas_compatibles'] }} h.</p><ul>@foreach ($d['actividades'] ?? [] as $a)<li>{{ $nombresCursos[$a['curso']] ?? $a['curso'] }} · {{ $a['seccion'] }} · {{ $a['tipo'] }} · {{ $a['horas'] }} h</li>@endforeach</ul><p>Franjas compatibles:</p><ul>@foreach ($d['franjas_compatibles'] ?? [] as $f)<li>{{ $f['dia'] }} {{ $f['inicio'] }}:00–{{ $f['fin'] }}:00</li>@endforeach</ul></div></details>
                    @endforeach
                    @foreach (($r['disponibilidad_actualizada_qa'] ?? []) as $d)
                        <p>Disponibilidad vigente aplicada: {{ $nombresDocentes[$d['docente']] ?? 'Docente sin nombre conciliado' }} · {{ $d['estado'] }} · {{ $d['bloques'] }} bloques.</p>
                    @endforeach
                    @if (!empty($r['diagnosticos']))<p>Revise las asignaciones, disponibilidades y restricciones indicadas.</p>@endif
                    @foreach (($r['diagnosticos_detalle'] ?? []) as $d)
                        <details class="gh-ui-group"><summary>{{ $d['categoria'] ?? 'Diagnóstico' }} @if (!empty($d['docente'])) · {{ $nombresDocentes[$d['docente']] ?? 'Docente sin nombre conciliado' }} @endif</summary><div style="padding:.8rem">@foreach (['carga'=>'Carga','horas_requeridas'=>'Horas requeridas','horas_compatibles'=>'Horas compatibles','horas_unicas_en_dominio'=>'Horas únicas en dominio','deficit'=>'Déficit','ciclo'=>'Ciclo','candidatas_rechazadas'=>'Candidatas rechazadas','certeza'=>'Certeza'] as $k => $etiqueta) @if (isset($d[$k]) && is_scalar($d[$k]))<p>{{ $etiqueta }}: {{ $d[$k] }}</p>@endif @endforeach</div></details>
                    @endforeach
                    @if (($r['advertencias'] ?? 0) > 0)<p class="gh-ui-note">La revisión conserva advertencias. Esta pantalla no constituye certificación institucional.</p>@endif
                @endif
            </div>
        @endif
    @endif
</section>
