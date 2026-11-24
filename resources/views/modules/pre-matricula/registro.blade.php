<x-guest-layout>
    <div class="auth-heading">
        <h1>Registro de estudiantes.</h1>
        <p>Crea tu cuenta para la prematrícula. Tu contraseña inicial será tu código universitario.</p>
    </div>

    @if (session('error') || $errors->any())
        <div class="auth-error-summary" role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 3h.01M10.3 3.7 2.7 17a2 2 0 0 0 1.8 3h15a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
            <div><strong>No pudimos completar el registro.</strong><p>{{ session('error') ?? 'Revisa los datos marcados e inténtalo otra vez.' }}</p></div>
        </div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('pre-matricula.registro.store') }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
        @csrf

        @foreach ([
            ['codigo_universitario', 'Código universitario (10 dígitos)', 'text', 'Ej.: 1023456789', 'inputmode="numeric" maxlength="10" pattern="[0-9]{10}"', '<path d="M4 5h16v14H4z"/><path d="M8 9h3M8 13h8"/>'],
            ['nombres', 'Nombres', 'text', 'Tus nombres', 'maxlength="100" autocomplete="given-name"', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
            ['apellidos', 'Apellidos', 'text', 'Tus apellidos', 'maxlength="150" autocomplete="family-name"', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
            ['correo_institucional', 'Correo institucional', 'email', 'usuario@unitru.edu.pe', 'maxlength="255" inputmode="email" autocomplete="email"', '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>'],
        ] as [$campo, $etiqueta, $tipo, $ejemplo, $atributos, $icono])
            <div class="auth-field">
                <label for="{{ $campo }}">{{ $etiqueta }}</label>
                <div class="auth-input-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icono !!}</svg>
                    <input id="{{ $campo }}" type="{{ $tipo }}" name="{{ $campo }}" value="{{ old($campo) }}" required placeholder="{{ $ejemplo }}" {!! $atributos !!} @error($campo) aria-invalid="true" aria-describedby="{{ $campo }}-error" @enderror>
                </div>
                @error($campo)<p id="{{ $campo }}-error" class="auth-field-error">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <button class="auth-submit" type="submit" x-bind:disabled="submitting">
            <span x-show="!submitting">Registrarme</span>
            <span x-cloak x-show="submitting" class="auth-submit-loading"><i aria-hidden="true"></i>Creando tu cuenta…</span>
            <svg x-show="!submitting" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </button>
    </form>

    <p class="auth-help">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>.</p>
</x-guest-layout>
