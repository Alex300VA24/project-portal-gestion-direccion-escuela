@php
    use App\Modules\GestionPracticas\Models\Practica;

    $corregible = in_array($practica->estado, [Practica::REGISTRADA, Practica::OBSERVADA], true);
    $tiposSubida = $practica->estado === Practica::EN_CURSO
        ? \Illuminate\Support\Arr::only($tiposDocumento, ['informe_parcial', 'informe_final', 'constancia', 'otro'])
        : \Illuminate\Support\Arr::only($tiposDocumento, ['carta_aceptacion', 'plan_practica', 'otro']);
    $puedeSubir = $corregible || $practica->estado === Practica::EN_CURSO;
    $campo = 'mt-1 block w-full rounded-[10px] border-[#bbc2ce] focus:border-[color:var(--portal-green)] focus:ring-[3px] focus:ring-[rgba(13,122,80,.15)]';
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Mi práctica en {{ $empresa }}</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <div class="flex items-center justify-between">
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
                    @if ($practica->estado === Practica::FINALIZADA)
                        <span class="text-sm font-bold">Nota final: {{ $practica->nota_final }} ({{ $practica->resultado }})</span>
                    @endif
                </div>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-[color:var(--portal-muted)]">Área</dt><dd>{{ $practica->area }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Horas planificadas</dt><dd>{{ $practica->horas_totales }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Inicio</dt><dd>{{ $practica->fecha_inicio->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Fin</dt><dd>{{ $practica->fecha_fin->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Funciones</dt><dd class="whitespace-pre-line">{{ $practica->funciones }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Docente supervisor</dt><dd>{{ $supervisor ?? 'Aún sin asignar' }}</dd></div>
                </dl>

                @if ($corregible)
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('gestion-practicas.estudiante.practicas.edit', $practica) }}" class="px-4 py-2 border border-[#bbc2ce] rounded-[10px] text-sm font-bold text-[color:var(--portal-navy)] hover:bg-[color:var(--portal-surface-alt)]">Corregir datos</a>
                        <form method="POST" action="{{ route('gestion-practicas.estudiante.practicas.enviar', $practica) }}">@csrf<x-primary-button>Enviar información para revisión</x-primary-button></form>
                    </div>
                @endif
            </div>

            @if ($observaciones->isNotEmpty())
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                    <h3 class="font-bold text-[color:var(--portal-ink)] mb-3">Observaciones del administrador</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach ($observaciones as $observacion)
                            <li class="border-s-4 {{ $observacion->estado === 'pendiente' ? 'border-[color:var(--portal-danger)]' : 'border-[color:var(--portal-green)]' }} ps-3">
                                <p>{{ $observacion->descripcion }}</p>
                                <p class="text-xs text-[color:var(--portal-muted)] mt-1">{{ $observacion->registrado_en->format('d/m/Y H:i') }} · @include('modules.gestion-practicas.partials.estado', ['estado' => $observacion->estado])</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <h3 class="font-bold text-[color:var(--portal-ink)]">Documentos</h3>
                @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => false])

                @if ($puedeSubir)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('gestion-practicas.documentos.store', $practica) }}" class="grid gap-3 sm:grid-cols-3 items-end border-t border-[color:var(--portal-border)] pt-4">
                        @csrf
                        <div>
                            <x-input-label for="tipo_documento" value="Tipo de documento" />
                            <select id="tipo_documento" name="tipo_documento" required class="{{ $campo }}">
                                @foreach ($tiposSubida as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="archivo" value="Archivo (máx. 10 MB)" />
                            <input id="archivo" name="archivo" type="file" required class="mt-1 block w-full text-sm">
                        </div>
                        <x-primary-button>Subir documento</x-primary-button>
                    </form>
                @endif
            </div>

            @if ($seguimientos->isNotEmpty())
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                    <h3 class="font-bold text-[color:var(--portal-ink)] mb-3">Seguimiento del supervisor</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach ($seguimientos as $seguimiento)
                            <li class="border-s-4 border-[color:var(--portal-border)] ps-3">
                                <p class="text-xs text-[color:var(--portal-muted)]">{{ $seguimiento->fecha->format('d/m/Y') }} · {{ str_replace('_', ' ', $seguimiento->tipo) }}</p>
                                <p>{{ $seguimiento->descripcion }}</p>
                                @if ($seguimiento->observaciones)<p class="text-[color:var(--portal-muted)]">{{ $seguimiento->observaciones }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
