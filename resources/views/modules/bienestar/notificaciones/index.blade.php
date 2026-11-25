@extends('modules.bienestar.layout')
@section('title', 'Notificaciones · Bienestar')
@section('page-label', 'Notificaciones')
@section('content')
<div class="space-y-6 max-w-3xl">
    <div class="encabezado">
        <div><h1>Notificaciones</h1><p>Avisos de tus trámites y bandejas. No incluyen datos sensibles.</p></div>
        @if ($avisosSinLeer)
            <form method="POST" action="{{ route('bienestar.notificaciones.leer-todas') }}">@csrf<button class="btn-unt-outline">Marcar todas como leídas</button></form>
        @endif
    </div>
    <div class="card-unt lista-avisos">
        @forelse ($notificaciones as $n)
            <a href="{{ route('bienestar.notificaciones.abrir', $n) }}" @class(['sin-leer' => ! $n->leida_en])>
                <b>{{ $n->titulo }}</b><span>{{ $n->mensaje }}</span><small>{{ $n->creada_en->setTimezone('America/Lima')->format('d/m/Y H:i') }}</small>
            </a>
        @empty
            <p class="vacio">Aún no tienes notificaciones.</p>
        @endforelse
    </div>
    {{ $notificaciones->links() }}
</div>
@endsection
