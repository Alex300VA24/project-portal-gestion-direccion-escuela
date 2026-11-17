<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Validar prácticas</h2></x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                <form method="GET" class="flex items-center gap-3 mb-4 text-sm">
                    <label for="estado" class="text-[color:var(--portal-muted)]">Estado</label>
                    <select id="estado" name="estado" onchange="this.form.submit()" class="rounded-[10px] border-[#bbc2ce] text-sm">
                        @foreach (['en_revision' => 'En revisión', 'observada' => 'Observadas', 'en_curso' => 'En curso', 'finalizada' => 'Finalizadas', 'todas' => 'Todas'] as $valor => $texto)
                            <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[color:var(--portal-border)] text-sm">
                        <thead class="text-left text-[color:var(--portal-muted)]">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Estudiante</th>
                                <th class="py-2 pe-4 font-medium">Empresa</th>
                                <th class="py-2 pe-4 font-medium">Área</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[color:var(--portal-border)]">
                            @forelse ($practicas as $practica)
                                <tr>
                                    <td class="py-2 pe-4">{{ $estudiantes[$practica->id_estudiante] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $empresas[$practica->id_empresa] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $practica->area }}</td>
                                    <td class="py-2 pe-4">@include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])</td>
                                    <td class="py-2 text-end"><a class="text-[color:var(--portal-green-dark)] hover:underline" href="{{ route('gestion-practicas.revision.practicas.show', $practica) }}">Revisar</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-[color:var(--portal-muted)]">No hay prácticas en este estado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
