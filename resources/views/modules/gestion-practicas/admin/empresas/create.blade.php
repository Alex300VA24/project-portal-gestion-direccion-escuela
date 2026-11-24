@extends('modules.gestion-practicas.layout')

@section('title', 'Registrar empresa')
@section('page-label', 'Registrar empresa')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Registrar empresa</h1><p>Se crea la empresa y la cuenta con la que su representante inicia sesión.</p></div>
    </header>

    <form method="POST" action="{{ route('gestion-practicas.empresas.store') }}" class="gp-stack">
        @csrf

        <section class="quality-panel" aria-labelledby="datos-empresa">
            <header class="quality-panel-heading"><div><h2 id="datos-empresa">Datos de la empresa</h2></div></header>
            <div class="gp-form is-two">
                @include('modules.gestion-practicas.partials.campo', ['name' => 'ruc', 'label' => 'RUC', 'extra' => ['inputmode' => 'numeric', 'maxlength' => 11]])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'razon_social', 'label' => 'Razón social'])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'nombre_comercial', 'label' => 'Nombre comercial', 'requerido' => false])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'telefono', 'label' => 'Teléfono', 'requerido' => false])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'direccion', 'label' => 'Dirección', 'requerido' => false, 'span' => true])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'pagina_web', 'label' => 'Página web', 'tipo' => 'url', 'requerido' => false, 'span' => true, 'extra' => ['placeholder' => 'https://']])
            </div>
        </section>

        <section class="quality-panel" aria-labelledby="datos-acceso">
            <header class="quality-panel-heading"><div><h2 id="datos-acceso">Representante y acceso</h2><p>La persona que gestionará las convocatorias de la empresa.</p></div></header>
            <div class="gp-form is-two">
                @include('modules.gestion-practicas.partials.campo', ['name' => 'contacto_dni', 'label' => 'DNI del representante', 'extra' => ['inputmode' => 'numeric', 'maxlength' => 8]])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'contacto_nombres', 'label' => 'Nombres'])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'contacto_apellido_paterno', 'label' => 'Apellido paterno'])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'correo_acceso', 'label' => 'Correo de acceso', 'tipo' => 'email'])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'password', 'label' => 'Contraseña inicial', 'tipo' => 'password', 'span' => true, 'ayuda' => 'Mínimo 8 caracteres. El representante podrá cambiarla en «Perfil de la empresa → Cambiar contraseña».', 'extra' => ['autocomplete' => 'new-password']])
            </div>
        </section>

        <div class="gp-actions">
            <button class="institutional-primary">Registrar empresa</button>
            <a class="institutional-secondary" href="{{ route('gestion-practicas.empresas.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
