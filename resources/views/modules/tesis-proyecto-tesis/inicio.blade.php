@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-eyebrow">Gestión de Tesis</p>
            <h1>Bienvenido, {{ $nombre }}</h1>
            @if ($roles !== [])
                <p>Tu cuenta tiene más de un rol en este módulo. Elige con cuál quieres trabajar; puedes cambiarlo luego desde la barra superior.</p>
            @endif
        </div>
    </div>

    @if (! $vinculado)
        <div class="tz-alert is-warning" role="alert">
            Tu cuenta del portal no está vinculada a un usuario académico (correo institucional). Comunícate con la Secretaría de la Escuela para habilitar tu acceso al módulo.
        </div>
    @elseif ($roles === [])
        <div class="tz-panel">
            @include('modules.tesis-proyecto-tesis.partials.vacio', [
                'titulo' => $esProfesor ? 'Aún no tienes proyectos asignados' : 'No hay tareas para tu rol en este módulo',
                'texto' => $esProfesor
                    ? 'Cuando la Secretaría registre tu designación como asesor o el Director te asigne como jurado, verás aquí tus proyectos.'
                    : 'Si crees que es un error, comunícate con la Secretaría de la Escuela.',
            ])
        </div>
    @else
        <div class="tz-roles">
            @foreach ($roles as $rol)
                <a class="tz-role" href="{{ $rol['url'] }}">
                    <span class="tz-eyebrow">{{ $rol['etiqueta'] }}</span>
                    <h2>Entrar como {{ mb_strtolower($rol['etiqueta']) }}</h2>
                    @foreach ($rol['resumen'] as $linea)
                        <span class="tz-muted">{{ $linea }}</span>
                    @endforeach
                    <b>Entrar →</b>
                </a>
            @endforeach
        </div>
    @endif
@endsection
