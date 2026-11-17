@php
    use App\Modules\GestionPracticas\Models\Practica;

    $enCurso = $practica->estado === Practica::EN_CURSO;
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
                    @if ($practica->estado === Practica::FINALIZADA)
                        <div><dt class="text-[color:var(--portal-muted)]">Nota final</dt><dd class="font-bold">{{ $practica->nota_final }} ({{ $practica->resultado }})</dd></div>
                    @endif
                </dl>
            </div>

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                <h3 class="font-bold text-[color:var(--portal-ink)] mb-3">Documentos (carta de aceptación e informes)</h3>
                @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => true])
            </div>

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <h3 class="font-bold text-[color:var(--portal-ink)]">Seguimiento</h3>

                @if ($enCurso)
                    <form method="POST" action="{{ route('gestion-practicas.supervisor.practicas.seguimiento', $practica) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div><x-input-label for="fecha" value="Fecha" /><input id="fecha" name="fecha" type="date" max="{{ now()->toDateString() }}" value="{{ old('fecha', now()->toDateString()) }}" required class="{{ $campo }}"></div>
                        <div>
                            <x-input-label for="tipo" value="Tipo" />
                            <select id="tipo" name="tipo" required class="{{ $campo }}">
                                @foreach ($tiposSeguimiento as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2"><x-input-label for="descripcion" value="Descripción" /><textarea id="descripcion" name="descripcion" rows="3" required class="{{ $campo }}">{{ old('descripcion') }}</textarea></div>
                        <div class="sm:col-span-2"><x-input-label for="observaciones" value="Observaciones (opcional)" /><textarea id="observaciones" name="observaciones" rows="2" class="{{ $campo }}">{{ old('observaciones') }}</textarea></div>
                        <div class="sm:col-span-2"><x-primary-button>Registrar seguimiento</x-primary-button></div>
                    </form>
                @endif

                <ul class="space-y-3 text-sm {{ $enCurso ? 'border-t border-[color:var(--portal-border)] pt-4' : '' }}">
                    @forelse ($seguimientos as $seguimiento)
                        <li class="border-s-4 border-[color:var(--portal-border)] ps-3">
                            <p class="text-xs text-[color:var(--portal-muted)]">{{ $seguimiento->fecha->format('d/m/Y') }} · {{ str_replace('_', ' ', $seguimiento->tipo) }}</p>
                            <p>{{ $seguimiento->descripcion }}</p>
                            @if ($seguimiento->observaciones)<p class="text-[color:var(--portal-muted)]">{{ $seguimiento->observaciones }}</p>@endif
                        </li>
                    @empty
                        <li class="text-[color:var(--portal-muted)]">Todavía no hay seguimientos registrados.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-4">
                <h3 class="font-bold text-[color:var(--portal-ink)]">Evaluación final</h3>

                @if ($criterios->isEmpty())
                    <p class="text-sm text-[color:var(--portal-muted)]">No hay criterios de evaluación configurados.</p>
                @else
                    <form method="POST" action="{{ route('gestion-practicas.supervisor.practicas.evaluar', $practica) }}" class="space-y-3">
                        @csrf
                        @foreach ($criterios as $criterio)
                            <div class="grid gap-2 sm:grid-cols-[1fr_8rem] items-center">
                                <label for="nota-{{ $criterio->id_criterio }}" class="text-sm text-[color:var(--portal-muted)]">{{ $criterio->nombre }} <span class="text-[color:var(--portal-muted)]">({{ rtrim(rtrim($criterio->peso, '0'), '.') }}%)</span></label>
                                <input id="nota-{{ $criterio->id_criterio }}" name="notas[{{ $criterio->id_criterio }}]" type="number" step="0.01" min="0" max="20"
                                       value="{{ old('notas.'.$criterio->id_criterio, $notasActuales[$criterio->id_criterio] ?? '') }}" @disabled(! $enCurso) required class="rounded-[10px] border-[#bbc2ce] text-sm">
                            </div>
                        @endforeach
                        <div>
                            <x-input-label for="comentario" value="Observación final" />
                            <textarea id="comentario" name="comentario" rows="3" @disabled(! $enCurso) class="{{ $campo }}">{{ old('comentario', $evaluacion?->comentario) }}</textarea>
                        </div>
                        @if ($enCurso)<x-primary-button>Registrar nota</x-primary-button>@endif
                    </form>
                @endif

                @if ($notaFinal !== null)
                    <p class="text-sm">Nota final calculada: <strong>{{ number_format($notaFinal, 2) }}</strong></p>
                @endif

                @if ($enCurso)
                    <form method="POST" action="{{ route('gestion-practicas.supervisor.practicas.cerrar', $practica) }}" class="border-t border-[color:var(--portal-border)] pt-4">
                        @csrf
                        <button class="px-4 py-2 bg-[color:var(--portal-navy)] text-white rounded-[10px] text-sm font-bold hover:bg-[color:var(--portal-ink)]">Cerrar práctica</button>
                        <p class="text-xs text-[color:var(--portal-muted)] mt-2">Requiere la nota registrada y el informe final aprobado.</p>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
