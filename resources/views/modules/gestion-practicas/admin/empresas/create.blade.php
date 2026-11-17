<x-app-layout>
    <x-slot name="header"><h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">Registrar empresa</h2></x-slot>

    @php
        $campo = 'mt-1 block w-full rounded-[10px] border-[#bbc2ce] focus:border-[color:var(--portal-green)] focus:ring-[3px] focus:ring-[rgba(13,122,80,.15)]';
    @endphp

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <form method="POST" action="{{ route('gestion-practicas.empresas.store') }}" class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 space-y-6">
                @csrf

                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <legend class="font-bold text-[color:var(--portal-ink)] mb-2">Datos de la empresa</legend>
                    <div><x-input-label for="ruc" value="RUC" /><input id="ruc" name="ruc" value="{{ old('ruc') }}" inputmode="numeric" maxlength="11" required class="{{ $campo }}"></div>
                    <div><x-input-label for="razon_social" value="Razón social" /><input id="razon_social" name="razon_social" value="{{ old('razon_social') }}" required class="{{ $campo }}"></div>
                    <div><x-input-label for="nombre_comercial" value="Nombre comercial" /><input id="nombre_comercial" name="nombre_comercial" value="{{ old('nombre_comercial') }}" class="{{ $campo }}"></div>
                    <div><x-input-label for="telefono" value="Teléfono" /><input id="telefono" name="telefono" value="{{ old('telefono') }}" class="{{ $campo }}"></div>
                    <div class="sm:col-span-2"><x-input-label for="direccion" value="Dirección" /><input id="direccion" name="direccion" value="{{ old('direccion') }}" class="{{ $campo }}"></div>
                    <div class="sm:col-span-2"><x-input-label for="pagina_web" value="Página web" /><input id="pagina_web" name="pagina_web" type="url" value="{{ old('pagina_web') }}" placeholder="https://" class="{{ $campo }}"></div>
                </fieldset>

                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <legend class="font-bold text-[color:var(--portal-ink)] mb-2">Representante y acceso</legend>
                    <div><x-input-label for="contacto_dni" value="DNI del representante" /><input id="contacto_dni" name="contacto_dni" value="{{ old('contacto_dni') }}" inputmode="numeric" maxlength="8" required class="{{ $campo }}"></div>
                    <div><x-input-label for="contacto_nombres" value="Nombres" /><input id="contacto_nombres" name="contacto_nombres" value="{{ old('contacto_nombres') }}" required class="{{ $campo }}"></div>
                    <div><x-input-label for="contacto_apellido_paterno" value="Apellido paterno" /><input id="contacto_apellido_paterno" name="contacto_apellido_paterno" value="{{ old('contacto_apellido_paterno') }}" required class="{{ $campo }}"></div>
                    <div><x-input-label for="correo_acceso" value="Correo de acceso" /><input id="correo_acceso" name="correo_acceso" type="email" value="{{ old('correo_acceso') }}" required class="{{ $campo }}"></div>
                    <div class="sm:col-span-2"><x-input-label for="password" value="Contraseña inicial (mínimo 8 caracteres)" /><input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $campo }}"></div>
                </fieldset>

                <div class="flex items-center gap-3">
                    <x-primary-button>Registrar empresa</x-primary-button>
                    <a href="{{ route('gestion-practicas.empresas.index') }}" class="text-sm text-[color:var(--portal-muted)] hover:underline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
