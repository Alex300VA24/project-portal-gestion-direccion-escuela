<section class="quality-panel gh-ui" style="padding:1rem;margin-top:1rem" aria-label="Borrador semanal de actividades" x-data="{}">
    @include('modules.gestion-horarios.ui-estilos')
    <h3>Editor semanal · simulación</h3>
    <p>Pruebe cambios de día y hora sin modificar las actividades registradas. No es un horario generado ni valida disponibilidad docente, ambientes, sábados o todas las reglas académicas.</p>
    @if ($cambioOrigen)<p class="quality-alert" role="alert">Los datos de origen cambiaron. Se conserva su borrador; restablezca para editar nuevamente.</p>@endif
    @error('borrador')<p class="quality-alert" role="alert">{{ $message }}</p>@enderror
    <form wire:submit="moverSeleccion" style="display:flex;flex-wrap:wrap;align-items:end;gap:.5rem">
        <label>Actividad<br><select class="quality-select" wire:model="actividad" required><option value="">Seleccione</option>@foreach ($sesiones as $s)<option value="{{ $s['id_actividad'] }}">{{ $s['curso_nombre'] }} · {{ $s['tipo_actividad'] }} · {{ $s['dia_semana'] }} {{ substr($s['hora_inicio'],0,5) }}</option>@endforeach</select></label>
        <label>Día<br><select class="quality-select" wire:model="dia">@foreach ($dias as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach</select></label>
        <label>Inicio<br><select class="quality-select" wire:model="hora" required><option value="">Seleccione</option>@foreach ($bloques as $b)<option value="{{ $b['hora_inicio'] }}">{{ substr($b['hora_inicio'],0,5) }}</option>@endforeach</select></label>
        <button type="submit" class="institutional-primary" @disabled($cambioOrigen) wire:loading.attr="disabled">Mover en borrador</button>
    </form>
    <div style="display:flex;gap:.5rem;margin:.75rem 0;flex-wrap:wrap">
        <button type="button" class="institutional-secondary" wire:click="deshacer" @disabled(! $historial) wire:loading.attr="disabled">Deshacer</button>
        <button type="button" class="institutional-secondary" wire:click="restablecer" wire:confirm="¿Descartar las modificaciones locales?">Restablecer</button>
        <button type="button" class="institutional-secondary" wire:click="$refresh">Comprobar cambios de origen</button>
    </div>
    <p wire:loading role="status">Actualizando borrador…</p>
    <p>También puede arrastrar una tarjeta a otro bloque. El formulario ofrece la misma operación mediante teclado.</p>
    <div style="overflow:auto"><table class="gh-semana" style="min-width:780px" aria-label="Cuadrícula editable de simulación"><thead><tr><th scope="col">Hora</th>@foreach ($dias as $d)<th scope="col">{{ $d }}</th>@endforeach</tr></thead><tbody>
        @php($horas = collect($bloques)->pluck('hora_inicio')->merge(collect($sesiones)->pluck('hora_inicio'))->unique()->sort()->values())
        @foreach ($horas as $h)
            <tr wire:key="borrador-hora-{{ $h }}"><th scope="row">{{ substr($h,0,5) }}</th>
                @foreach ($dias as $d)
                    <td style="min-height:3rem" @if (! $cambioOrigen && collect($bloques)->contains('hora_inicio', $h)) x-on:dragover.prevent x-on:drop.prevent="$wire.mover($event.dataTransfer.getData('text/plain'), @js($d), @js($h))" @endif>
                        @foreach ($sesiones as $s)
                            @if ($s['dia_semana'] === $d && $s['hora_inicio'] === $h)
                                <article wire:key="borrador-actividad-{{ $s['id_actividad'] }}" data-tipo="{{ $s['tipo_actividad'] }}" draggable="{{ $cambioOrigen ? 'false' : 'true' }}" x-on:dragstart="$event.dataTransfer.setData('text/plain', @js((string) $s['id_actividad']))" style="cursor:grab">
                                    <strong>{{ $s['curso_nombre'] }}</strong><small>{{ $s['tipo_actividad'] }} · {{ substr($s['hora_inicio'],0,5) }}–{{ substr($s['hora_fin'],0,5) }}</small>
                                </article>
                            @endif
                        @endforeach
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody></table></div>
    @if ($coincidencias)
        <details><summary>{{ count($coincidencias) }} coincidencias temporales para revisar</summary><p>No todas son conflictos: los laboratorios de distintos subgrupos pueden ser simultáneos.</p><ul>@foreach (array_slice($coincidencias,0,30) as $c)<li>{{ $c['dia'] }} · {{ $c['primera'] }} / {{ $c['segunda'] }}</li>@endforeach</ul>@if(count($coincidencias)>30)<p>Se muestran las primeras 30 coincidencias.</p>@endif</details>
    @else
        <p>No se detectaron coincidencias dentro de este filtro. No acredita factibilidad.</p>
    @endif
</section>
