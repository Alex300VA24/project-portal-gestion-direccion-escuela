{{-- Avisos de DESIGN.md §4: .auth-status (confirmación) y .auth-error-summary (resumen de errores, icono de 21 px, título en negrita). --}}
@if (session('status'))
    <div class="auth-status gp-aviso" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="auth-error-summary gp-aviso" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
        <div>
            <strong>No se pudo completar la acción</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
