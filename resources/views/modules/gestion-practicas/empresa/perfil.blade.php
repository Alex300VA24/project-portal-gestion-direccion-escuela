<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Perfil de la empresa</h2></x-slot>

    @php
        $campo = 'mt-1 block w-full rounded-[10px] border-[#bbc2ce] focus:border-[color:var(--portal-green)] focus:ring-[3px] focus:ring-[rgba(13,122,80,.15)]';
    @endphp

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <form method="POST" action="{{ route('gestion-practicas.empresa.perfil.update') }}" class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 grid gap-4 sm:grid-cols-2">
                @csrf @method('PUT')

                <div><x-input-label value="RUC" /><p class="mt-2 text-sm text-[color:var(--portal-muted)]">{{ $empresa->ruc }}</p></div>
                <div><x-input-label value="Razón social" /><p class="mt-2 text-sm text-[color:var(--portal-muted)]">{{ $empresa->razon_social }}</p></div>

                <div class="sm:col-span-2"><x-input-label for="nombre_comercial" value="Nombre comercial" /><input id="nombre_comercial" name="nombre_comercial" value="{{ old('nombre_comercial', $empresa->nombre_comercial) }}" class="{{ $campo }}"></div>
                <div class="sm:col-span-2"><x-input-label for="direccion" value="Dirección" /><input id="direccion" name="direccion" value="{{ old('direccion', $empresa->direccion) }}" required class="{{ $campo }}"></div>
                <div><x-input-label for="telefono" value="Teléfono" /><input id="telefono" name="telefono" value="{{ old('telefono', $empresa->telefono) }}" required class="{{ $campo }}"></div>
                <div><x-input-label for="correo" value="Correo de contacto" /><input id="correo" name="correo" type="email" value="{{ old('correo', $empresa->correo) }}" required class="{{ $campo }}"></div>
                <div class="sm:col-span-2"><x-input-label for="pagina_web" value="Página web" /><input id="pagina_web" name="pagina_web" type="url" value="{{ old('pagina_web', $empresa->pagina_web) }}" placeholder="https://" class="{{ $campo }}"></div>

                <div class="sm:col-span-2"><x-primary-button>Guardar perfil</x-primary-button></div>
            </form>
        </div>
    </div>
</x-app-layout>
