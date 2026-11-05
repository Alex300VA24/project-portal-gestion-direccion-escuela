<x-guest-layout>
    <div class="auth-heading">
        <h1>Bienvenido de nuevo.</h1>
        <p>Usa las credenciales asignadas a tu rol institucional.</p>
    </div>

    <x-auth-session-status class="auth-status" :status="session('status')" />

    @if ($errors->any())
        <div class="auth-error-summary" role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 3h.01M10.3 3.7 2.7 17a2 2 0 0 0 1.8 3h15a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
            <div><strong>No pudimos iniciar la sesión.</strong><p>Revisa tu correo y contraseña, e inténtalo otra vez.</p></div>
        </div>
    @endif

    <form class="auth-form" method="POST" action="{{ route('login') }}" x-data="{ showPassword: false, submitting: false }" x-on:submit="submitting = true">
        @csrf

        <div class="auth-field">
            <label for="email">Correo institucional</label>
            <div class="auth-input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" placeholder="nombre@portal.local" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')<p id="email-error" class="auth-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <div class="auth-label-row">
                <label for="password">Contraseña</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                @endif
            </div>
            <div class="auth-input-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input id="password" x-bind:type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button class="auth-password-toggle" type="button" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" x-bind:aria-pressed="showPassword">
                    <svg x-show="!showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    <svg x-cloak x-show="showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.1A10.5 10.5 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8M6.2 6.2C3.5 8 2 12 2 12s3.5 6 10 6c1.3 0 2.5-.2 3.5-.6"/></svg>
                </button>
            </div>
            @error('password')<p id="password-error" class="auth-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="auth-options">
            <label for="remember_me">
                <input id="remember_me" type="checkbox" name="remember" value="1">
                <span>Recordar mi sesión</span>
            </label>
        </div>

        <button class="auth-submit" type="submit" x-bind:disabled="submitting">
            <span x-show="!submitting">Ingresar al portal</span>
            <span x-cloak x-show="submitting" class="auth-submit-loading"><i aria-hidden="true"></i>Validando acceso…</span>
            <svg x-show="!submitting" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </button>
    </form>

    <p class="auth-help">¿Necesitas acceso? Contacta a la administración de tu escuela.</p>
</x-guest-layout>
