@extends('modules.gestion-practicas.layout')

@section('title', 'Perfil de la empresa')
@section('page-label', 'Perfil de la empresa')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Perfil de la empresa</h1><p>Mantenga actualizados los datos con los que los estudiantes y el administrador pueden contactarla.</p></div>
    </header>

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
@endsection
