@extends('modules.bienestar.layout')

@section('title', 'Revisión de Solicitud #' . $solicitud->id_solicitud . ' · Secretaría')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.secretaria.solicitudes'), 'texto' => 'Solicitudes'])@endsection
@section('page-label', 'Revisión de Requisitos')

@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold mt-1" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Verificación Documental de Requisitos</h1>
        </div>
        <div>
            <span class="px-3 py-1 rounded-full text-xs font-bold
                {{ $solicitud->estado === 'enviada' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $solicitud->estado === 'recibida' ? 'bg-unt-papel text-unt-dorado-texto border border-unt-dorado' : '' }}
                {{ $solicitud->estado === 'observada' ? 'bg-red-100 text-red-800' : '' }}
                {{ $solicitud->estado === 'remitida' ? 'bg-[var(--unt-azul)] text-white' : '' }}
                {{ $solicitud->estado === 'aprobada' ? 'bg-unt-azul text-white' : '' }}
                {{ $solicitud->estado === 'rechazada' ? 'bg-slate-200 text-slate-700' : '' }}">
                Estado Actual: {{ ucfirst($solicitud->estado) }}
            </span>
        </div>
    </div>


    <!-- Datos Administrativos del Trámite y Estudiante -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="card-unt p-4 space-y-2">
            <h2 class="text-xs font-bold uppercase tracking-wider" style="color: var(--unt-azul-tinta);">Datos Administrativos del Estudiante</h2>
            <div class="text-sm font-bold text-[var(--unt-texto)]">
                {{ $solicitud->estudiante?->persona?->nombres }} {{ $solicitud->estudiante?->persona?->apellido_paterno }} {{ $solicitud->estudiante?->persona?->apellido_materno }}
            </div>
            <div class="text-xs font-mono" style="color: var(--unt-texto-suave);">
                Código Universitario: <strong>{{ $solicitud->estudiante?->codigo_universitario }}</strong> · {{ $ciclo }}
            </div>
            <div class="text-xs" style="color: var(--unt-texto-suave);">
                Fecha de Envío: {{ $solicitud->solicitada_en?->setTimezone('America/Lima')->format('d/m/Y H:i:s') }}
            </div>
        </div>

        <div class="card-unt p-4 space-y-2">
            <h2 class="text-xs font-bold uppercase tracking-wider" style="color: var(--unt-azul-tinta);">Procedimiento / Trámite UNT</h2>
            <div class="text-sm font-bold text-[var(--unt-texto)]">
                {{ $solicitud->tipoSolicitud?->nombre ?? 'General (migrado)' }}
            </div>
        </div>
    </div>

    <!-- Formulario de Revisión de Requisitos -->
    <form method="POST" action="{{ route('bienestar.secretaria.solicitudes.guardar-revision', $solicitud) }}" id="revision" class="space-y-6">
        @csrf
        <fieldset class="space-y-6" @disabled(! $solicitud->puedeRevisarse())>

        <div class="card-unt overflow-hidden">
            <div class="p-4 border-b border-[var(--unt-borde)] flex items-center justify-between" style="background-color: var(--unt-fondo-alt);">
                <h3 class="text-sm font-bold" style="color: var(--unt-azul-tinta);">Verificación Individual de Requisitos Documentarios</h3>
                <span class="text-xs" style="color: var(--unt-texto-suave);">Cada requisito debe marcarse como Conforme para permitir la remisión.</span>
            </div>

            <div class="divide-y divide-[var(--unt-borde)]">
                @php
                    $docsMap = $solicitud->documentosRequisitos->keyBy('id_requisito');
                @endphp

                @forelse ($solicitud->tipoSolicitud?->requisitosActivos ?? [] as $index => $req)
                    @php
                        $doc = $docsMap->get($req->id_requisito);
                        $estadoActual = $doc?->estado_revision ?? 'pendiente';
                    @endphp
                    <div class="p-4 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="space-y-1 max-w-xl">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-[var(--unt-texto)]">{{ $index + 1 }}. {{ $req->nombre }}</span>
                                    @if ($req->es_obligatorio)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-800 border border-red-200">Obligatorio</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Opcional</span>
                                    @endif
                                </div>
                                <p class="text-xs text-[var(--unt-texto)]">{{ $req->descripcion }}</p>
                                <div class="text-[11px]" style="color: var(--unt-texto-suave);">
                                    Formatos permitidos: {{ strtoupper($req->formatos_permitidos) }} · Máx. {{ $req->tamano_maximo_mb }} MB
                                </div>
                            </div>

                            <!-- Documento adjunto por el estudiante -->
                            <div class="sm:text-right shrink-0">
                                @if ($doc && $doc->archivo)
                                    <a href="{{ route('bienestar.secretaria.documentos.descargar', $doc) }}" class="btn-unt-outline py-1 px-3">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        <span>Descargar / Ver Adjunto</span>
                                    </a>
                                    <div class="text-[10px] mt-1 font-mono" style="color: var(--unt-texto-suave);">
                                        {{ $doc->archivo->nombre_original }} ({{ number_format($doc->archivo->tamano_bytes / 1024, 1) }} KB)
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded text-xs font-medium" style="background-color: var(--unt-crema); color: var(--u-dorado-texto); border: 1px solid var(--unt-crema-borde);">
                                        No adjuntado
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Opciones de revisión para Secretaría -->
                        <input type="hidden" name="revisiones[{{ $index }}][id_requisito]" value="{{ $req->id_requisito }}">

                        <div class="pt-2 flex flex-col sm:flex-row sm:items-center gap-4 p-3 rounded-lg border border-[var(--unt-borde)]" style="background-color: var(--unt-fondo-alt);">
                            <span class="text-xs font-bold text-[var(--unt-texto)]">Dictamen del Requisito:</span>
                            <div class="flex items-center gap-4 text-xs">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="revisiones[{{ $index }}][estado]" value="conforme" @checked(old("revisiones.{$index}.estado", $estadoActual) === 'conforme') class="text-unt-azul focus:ring-[var(--unt-dorado)]">
                                    <span class="font-bold text-unt-azul">Conforme</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="revisiones[{{ $index }}][estado]" value="observado" @checked(old("revisiones.{$index}.estado", $estadoActual) === 'observado') class="text-red-700 focus:ring-[var(--unt-dorado)]">
                                    <span class="font-bold text-red-800">Observado</span>
                                </label>
                            </div>

                            <div class="flex-1">
                                <input type="text" name="revisiones[{{ $index }}][comentario]" value="{{ old("revisiones.{$index}.comentario", $doc?->comentario_revision) }}" placeholder="Si observa este requisito, indique el motivo aquí..." class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs" style="color: var(--unt-texto-suave);">
                        No hay requisitos catalogados para este trámite.
                    </div>
                @endforelse
            </div>
        </div>

        </fieldset>
        @if ($solicitud->puedeRevisarse())
        <!-- Botones de Acción de Secretaría -->
        <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
            @if ($solicitud->estado === 'enviada')<button type="button" class="btn-unt-outline" data-abrir-modal="m-recibir">Pasar a revisión</button>@endif
            <button type="button" class="btn-unt-secondary" data-abrir-modal="m-observar">Observar</button>
            <button type="button" class="btn-unt-primary" data-abrir-modal="m-remitir">Remitir a Bienestar</button>
        </div>
        @endif
    </form>
</div>
@if ($solicitud->puedeRevisarse())
    @foreach ([['m-recibir', 'Pasar a revisión', 'Confirma el cambio de estado.', 'recibir', 'Pasar a revisión', 'primary'], ['m-observar', 'Observar solicitud', 'Vuelve al estudiante, que solo resubirá los requisitos que marcaste como observados.', 'observar', 'Observar', 'secondary'], ['m-remitir', 'Remitir a Bienestar', 'Todos los requisitos obligatorios deben estar conformes.', 'remitir', 'Remitir', 'primary']] as [$idm, $tit, $det, $acc, $btn, $tono])
        <dialog id="{{ $idm }}" class="modal-unt" style="max-width: 34rem" aria-labelledby="{{ $idm }}-titulo">
            <header><div><h2 id="{{ $idm }}-titulo">{{ $tit }}</h2><p>{{ $det }}</p></div></header>
            <div class="modal-unt-cuerpo">
                @if ($acc === 'observar')
                    <label class="campo">Indicaciones generales<textarea form="revision" name="observacion_general" rows="3">{{ old('observacion_general', $solicitud->observacion_general_secretaria) }}</textarea></label>
                @elseif ($acc === 'remitir')
                    <label class="casilla"><input form="revision" type="checkbox" name="confirma_folio_externo" value="1" @checked(old('confirma_folio_externo'))><span><b>Confirmo que armé el folio fuera del sistema *</b><small>El sistema no genera folio, numeración ni carátula.</small></span></label>
                @endif
                <p class="nota">El estudiante recibirá: «{{ \App\Modules\Bienestar\Support\EstadoSolicitud::aviso(['recibir' => 'recibida', 'observar' => 'observada', 'remitir' => 'remitida'][$acc], $solicitud->id_solicitud) }}»</p>
            </div>
            <footer><button type="button" class="btn-unt-outline" data-cerrar-modal>Cancelar</button><button form="revision" type="submit" name="accion" value="{{ $acc }}" class="btn-unt-{{ $tono }}">{{ $btn }}</button></footer>
        </dialog>
    @endforeach
@endif
@endsection
