@php
    use App\Modules\GestionPracticas\Models\Practica;

    $campo = 'mt-1 block w-full rounded-[10px] border-[#bbc2ce] focus:border-[color:var(--portal-green)] focus:ring-[3px] focus:ring-[rgba(13,122,80,.15)]';
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Práctica de {{ $estudiante }}</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-[color:var(--portal-ink)]">{{ $empresa }}</p>
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
                </div>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-[color:var(--portal-muted)]">Área</dt><dd>{{ $practica->area }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Horas planificadas</dt><dd>{{ $practica->horas_totales }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Inicio</dt><dd>{{ $practica->fecha_inicio->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-[color:var(--portal-muted)]">Fin</dt><dd>{{ $practica->fecha_fin->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Funciones</dt><dd class="whitespace-pre-line">{{ $practica->funciones }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[color:var(--portal-muted)]">Docente supervisor</dt><dd>{{ $supervisor ?? 'Sin asignar' }}</dd></div>
                    @if ($practica->estado === Practica::FINALIZADA)
                        <div><dt class="text-[color:var(--portal-muted)]">Nota final</dt><dd class="font-bold">{{ $practica->nota_final }} ({{ $practica->resultado }})</dd></div>
                    @endif
                </dl>
            </div>

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                <h3 class="font-bold text-[color:var(--portal-ink)] mb-3">Documentos</h3>
                @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => true])
            </div>

            @if ($practica->estado === Practica::EN_REVISION)
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-6">
                    <h3 class="font-bold text-[color:var(--portal-ink)]">¿La información es válida?</h3>

                    <form method="POST" action="{{ route('gestion-practicas.revision.practicas.validar', $practica) }}" class="space-y-2">
                        @csrf
                        <x-input-label for="id_docente" value="Sí: validar y asignar docente supervisor" />
                        <select id="id_docente" name="id_docente" required class="{{ $campo }}">
                            <option value="">Seleccione un docente…</option>
                            @foreach ($docentes as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Validar y poner en curso</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('gestion-practicas.revision.practicas.observar', $practica) }}" class="space-y-2">
                        @csrf
                        <x-input-label for="descripcion" value="No: registrar observaciones" />
                        <textarea id="descripcion" name="descripcion" rows="3" required class="{{ $campo }}" placeholder="Indique qué debe corregir el estudiante"></textarea>
                        <button class="px-4 py-2 bg-[color:var(--portal-danger)] text-white rounded-[10px] text-sm font-bold hover:bg-[#8e1a11]">Devolver con observaciones</button>
                    </form>
                </div>
            @endif

            @if ($observaciones->isNotEmpty())
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                    <h3 class="font-bold text-[color:var(--portal-ink)] mb-3">Historial de observaciones</h3>
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
        </div>
    </div>
</x-app-layout>
