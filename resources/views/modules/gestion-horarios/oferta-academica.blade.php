<div class="quality-page gh-ui gh-oferta" style="padding: 2rem 1rem; margin: 0 auto; max-width: 1180px">
    @include('modules.gestion-horarios.ui-estilos')
    <style>
        .gh-oferta .quality-page-heading { display:block; }
        .gh-oferta label,.gh-oferta select,.gh-oferta input { min-width:0;max-width:100%; }
    </style>
    @include('modules.gestion-horarios.cabecera-administrativa', ['titulo'=>'Preparación académica', 'subtitulo'=>'Oferta académica', 'descripcion'=>'Consulte los cursos ofertados y la demanda registrada para cada periodo.'])

    @include('modules.gestion-horarios.workspace-navegacion', [
        'workspaceEtapa' => 'preparacion', 'workspaceSub' => 'oferta',
        'workspacePlan' => ($planValido ?? false) ? $plan : '',
        'workspacePeriodo' => ($planValido ?? false) && isset($periodos) && $periodos->contains('id_periodo', (int) $periodo) ? $periodo : '',
    ])
    @if ($errorConsulta)
        <div class="quality-alert" role="alert">No se pudo consultar la información. Intente nuevamente o comuníquese con soporte.</div>
    @else
        <section class="quality-panel" aria-label="Filtros de oferta">
            <div style="display:flex; flex-wrap:wrap; gap:1rem; padding:1.25rem">
                <label>Periodo académico<br><select class="quality-select" wire:model.live="periodo"><option value="">Seleccione un periodo</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}">{{ $p->codigo }}</option>@endforeach</select></label>
                <label>Buscar por código o nombre<br><input class="quality-select" type="search" wire:model.live.debounce.400ms="buscar" maxlength="120" placeholder="Código o asignatura"></label>
                <label>Plan de estudios<br><select class="quality-select" wire:model.live="plan"><option value="">Seleccione un plan</option>@foreach ($planes as $item)<option value="{{ $item->id_plan }}">{{ $item->codigo }} · {{ $item->nombre }} · Escuela {{ $item->id_escuela }}</option>@endforeach</select></label>
                <label>Ciclo<br><select class="quality-select" wire:model.live="ciclo" @disabled(! $planValido)><option value="">Todos</option>@foreach ($ciclos as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
                @if ($avisoPlan)<p role="status">{{ $avisoPlan }}</p>@endif
                @if (! $planValido)<p>Seleccione un plan de estudios; el periodo no determina automáticamente un plan.</p>@elseif ($ciclos->isEmpty())<p>El plan seleccionado no tiene ciclos registrados.</p>@endif
                <label>Tipo de oferta<br><select class="quality-select" wire:model.live="tipoFiltro"><option value="">Todos</option><option value="REGULAR">Regular</option><option value="REPETICION">Repetición</option></select></label>
                <label>Estado de oferta<br><select class="quality-select" wire:model.live="estado"><option value="">Todos</option><option value="PLANIFICADA">Planificada</option><option value="OFERTADA">Ofertada</option><option value="CERRADA">Cerrada</option></select></label>
            </div>
        </section>
        <p wire:loading role="status" aria-live="polite">Actualizando información…</p>
        @if ($mensaje)<p class="auth-status" role="status">{{ $mensaje }}</p>@endif
            @if ($escrituraDisponible)<div style="margin-top:1rem"><button class="institutional-primary" type="button" wire:click="nueva">Crear oferta</button></div>@endif
            @if ($formularioAbierto && $escrituraDisponible)
                <section class="quality-panel" style="margin-top:1rem; padding:1.25rem" aria-labelledby="formulario-oferta">
                    <h2 id="formulario-oferta">{{ $ofertaEditar ? 'Detalle y edición de oferta' : 'Nueva oferta académica' }}</h2>
                    @if ($ofertaEditar)<p>{{ $detalleOferta }}</p>@endif
                    @if ($errors->any())<div class="auth-error-summary" role="alert"><strong>Revise la información:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <form wire:submit="guardar" wire:confirm="¿Desea guardar los cambios en la oferta académica?">
                        <div style="display:flex; flex-wrap:wrap; gap:1rem">
                            @if (! $ofertaEditar)
                                @if ($periodo === '')
                                    <label>Código del periodo académico<br><input class="quality-select" wire:model="codigoPeriodoFormulario" maxlength="20" placeholder="2026-II" required></label>
                                @endif
                                <label>Asignatura del plan<br><select class="quality-select" wire:model="cursoFormulario" required><option value="">Seleccione un curso</option>@foreach ($catalogo as $item)<option value="{{ $item->id_curso_plan }}">{{ $item->codigo }} · {{ $item->nombre }} · ciclo {{ $item->ciclo }}</option>@endforeach</select></label>
                                <label>Tipo<br><select class="quality-select" wire:model="tipoFormulario"><option value="REGULAR">Regular</option><option value="REPETICION">Repetición</option></select></label>
                            @else
                                <label>Estado<br><select class="quality-select" wire:model="estadoFormulario"><option value="PLANIFICADA">Planificada</option><option value="OFERTADA">Ofertada</option><option value="CERRADA">Cerrada</option></select></label>
                            @endif
                            <label>Docentes mínimos<br><input class="quality-select" type="number" min="1" max="32767" wire:model="docentesFormulario" required></label>
                        </div>
                        @if ($ofertaEditar)<p>Las ofertas con secciones requieren revisión antes de modificarse.</p>@endif
                        <button class="institutional-primary" type="submit" wire:loading.attr="disabled">Guardar oferta</button>
                        <button class="institutional-secondary" type="button" wire:click="$set('formularioAbierto', false)">Cancelar</button>
                    </form>
                </section>
            @endif
        @if (! $planValido)
            <div class="quality-empty"><h2>Seleccione un plan autorizado</h2><p>Los periodos se muestran cuando tienen datos vinculados al plan.</p></div>
        @elseif ($periodos->isEmpty())
            <div class="quality-empty"><h2>No hay periodos vinculados</h2><p>No se encontraron ofertas, secciones ni demanda para este plan.</p></div>
        @elseif ($periodo === '')
            <div class="quality-empty"><h2>Seleccione un periodo</h2><p>Elija un periodo para consultar sus cursos.</p></div>
        @else
            <section class="quality-panel" style="margin-top:1rem; padding:1.25rem" aria-labelledby="resumen-preparacion">
                <h2 id="resumen-preparacion">Preparación del plan</h2>
                <p>Compruebe los datos disponibles para el plan y periodo seleccionados. Esta revisión no genera horarios.</p>
                <button type="button" class="institutional-secondary" wire:click="comprobarPreparacion" wire:loading.attr="disabled">Comprobar preparación parcial</button>
                @if ($resumenPreparacion !== null)
                    <ul aria-live="polite">
                        @foreach ($resumenPreparacion as $diagnostico)
                            <li><strong>{{ $diagnostico['nivel'] }}:</strong> {{ $diagnostico['mensaje'] }}</li>
                        @endforeach
                    </ul>
                @endif
                <p><a href="{{ route('gestion-horarios.secciones', ['plan' => $plan, 'periodo' => $periodo]) }}">Continuar a secciones</a></p>
            </section>
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="demanda-titulo">
                <header class="quality-panel-heading"><div><h2 id="demanda-titulo">Demanda académica</h2>
                    <p>Los totales visibles corresponden a cursos del plan seleccionado; son estimaciones y no representan matrícula.</p>
                    @if ($demanda && $cursos->contains(fn ($curso) => $curso->total !== null))
                        <p role="status">Versión vigente {{ $demanda->version }}@if ($demanda->requiere_revision) · Revisión pendiente @endif</p>
                    @else
                        <p role="status">No hay datos de demanda visibles para los filtros seleccionados.</p>
                    @endif
                </div>
                </header>
            </section>
            <section class="quality-panel" style="margin-top:1rem" aria-labelledby="cursos-titulo">
                <header class="quality-panel-heading"><div><h2 id="cursos-titulo">Cursos del periodo</h2><p>Se muestran cursos ofertados o incluidos en la demanda vigente. Los valores de demanda corresponden al curso, no a cada tipo de oferta.</p></div></header>
                @if ($cursos->isEmpty())
                    <div class="quality-empty"><h3>No se encontraron cursos</h3><p>No hay oferta o demanda para los filtros seleccionados.</p></div>
                @else
                    <div style="overflow-x:auto"><table style="width:100%; min-width:700px; border-collapse:collapse; text-align:left">
                        <thead><tr><th>Código</th><th>Asignatura</th><th>Ciclo</th><th>Tipo de oferta</th><th>Estado</th><th>Demanda</th><th>Mañana / tarde</th><th>Detalle</th></tr></thead>
                        <tbody>@foreach ($cursos as $curso)<tr wire:key="curso-{{ $curso->id_curso_plan }}-{{ $curso->id_oferta ?? 'sin-oferta' }}">
                            <td class="quality-code">{{ $curso->codigo }}</td><td>{{ $curso->nombre }}</td><td>{{ $curso->ciclo ?? '—' }}</td><td>{{ $curso->tipo_oferta ? ucfirst(mb_strtolower($curso->tipo_oferta)) : 'Sin oferta' }}</td><td>{{ $curso->estado ? ucfirst(mb_strtolower($curso->estado)) : 'Sin oferta' }}</td><td>{{ $curso->total ?? 'Sin datos' }}</td><td>{{ $curso->total !== null ? $curso->manana.' / '.$curso->tarde : '—' }}</td>
                            <td>@if ($curso->id_oferta && $escrituraDisponible)<button type="button" class="quality-open-link" wire:click="editar({{ $curso->id_oferta }})">Ver y editar</button>@endif</td>
                        </tr>@endforeach</tbody>
                    </table></div>
                @endif
            </section>
        @endif
    @endif
</div>
