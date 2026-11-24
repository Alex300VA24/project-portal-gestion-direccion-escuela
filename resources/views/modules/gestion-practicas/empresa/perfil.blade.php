@extends('modules.gestion-practicas.layout')

@section('title', 'Perfil de la empresa')
@section('page-label', 'Perfil de la empresa')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Perfil de la empresa</h1><p>Mantenga actualizados los datos de contacto y la contraseña de su cuenta.</p></div>
    </header>

    @if ($debeCambiarPassword)
        <div class="quality-alert gp-aviso" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
            <div>
                <strong>Cambie la contraseña inicial</strong>
                <span>El administrador creó su cuenta con una contraseña temporal. Defina la suya en «Cambiar contraseña», más abajo.</span>
            </div>
        </div>
    @endif

    <div class="gp-stack">
        <form method="POST" action="{{ route('gestion-practicas.empresa.perfil.update') }}">
            @csrf @method('PUT')

            <section class="quality-panel" aria-labelledby="perfil-titulo">
                <header class="quality-panel-heading">
                    <div><h2 id="perfil-titulo">{{ $empresa->razon_social }}</h2><p>RUC <span class="quality-code">{{ $empresa->ruc }}</span> · el RUC y la razón social los gestiona el administrador.</p></div>
                </header>
                <div class="gp-form is-two">
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'nombre_comercial', 'label' => 'Nombre comercial', 'valor' => $empresa->nombre_comercial, 'requerido' => false, 'span' => true])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'direccion', 'label' => 'Dirección', 'valor' => $empresa->direccion, 'span' => true])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'telefono', 'label' => 'Teléfono', 'valor' => $empresa->telefono])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'correo', 'label' => 'Correo de contacto', 'tipo' => 'email', 'valor' => $empresa->correo])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'pagina_web', 'label' => 'Página web', 'tipo' => 'url', 'valor' => $empresa->pagina_web, 'requerido' => false, 'span' => true, 'extra' => ['placeholder' => 'https://']])
                    <div class="gp-span"><button class="institutional-primary">Guardar perfil</button></div>
                </div>
            </section>
        </form>

        <form method="POST" action="{{ route('gestion-practicas.empresa.perfil.password') }}">
            @csrf @method('PUT')

            <section class="quality-panel" aria-labelledby="password-titulo">
                <header class="quality-panel-heading">
                    <div><h2 id="password-titulo">Cambiar contraseña</h2><p>Es la contraseña con la que inicia sesión en el portal.</p></div>
                </header>
                <div class="gp-form is-two">
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'current_password', 'label' => 'Contraseña actual', 'tipo' => 'password', 'span' => true, 'extra' => ['autocomplete' => 'current-password']])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'password', 'label' => 'Nueva contraseña', 'tipo' => 'password', 'ayuda' => 'Mínimo 8 caracteres.', 'extra' => ['autocomplete' => 'new-password']])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'password_confirmation', 'label' => 'Repita la nueva contraseña', 'tipo' => 'password', 'extra' => ['autocomplete' => 'new-password']])
                    <div class="gp-span"><button class="institutional-primary">Cambiar contraseña</button></div>
                </div>
            </section>
        </form>
    </div>
@endsection
