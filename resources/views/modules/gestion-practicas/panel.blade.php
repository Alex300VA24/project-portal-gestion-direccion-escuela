<x-app-layout>
    <x-slot name="header">
        <h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Gestión de Prácticas</h2>
    </x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            @unless ($vinculada)
                <div class="rounded-[10px] bg-[#fcefdf] border border-[#e4b84f] text-[#7b3906] px-4 py-3 text-sm">
                    Su cuenta de acceso ({{ $actor->user->email }}) aún no está vinculada a un usuario institucional.
                    Pida al administrador que registre ese mismo correo para poder operar en el módulo.
                </div>
            @endunless

            <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                <h3 class="text-lg font-bold text-[color:var(--portal-ink)]">Hola, {{ $actor->user->name }}</h3>
                <p class="text-sm text-[color:var(--portal-muted)] mt-1">
                    Rol: {{ $actor->user->getRoleNames()->map(fn ($r) => str_replace('practicas_', '', $r))->implode(', ') ?: 'sin rol asignado' }}
                </p>
            </div>

            @if ($resumen !== [])
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($resumen as $titulo => $valor)
                        <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6">
                            <p class="text-sm text-[color:var(--portal-muted)]">{{ $titulo }}</p>
                            <p class="mt-1 text-3xl font-bold text-[color:var(--portal-ink)]">{{ $valor }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 text-sm text-[color:var(--portal-muted)]">
                    Su usuario no tiene un rol de prácticas asignado. Contacte al administrador del portal.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
