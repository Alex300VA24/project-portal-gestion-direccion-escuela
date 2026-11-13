@can('cotecu.view')
    <a href="{{ route('cotecu.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.index')]) @if(request()->routeIs('cotecu.index')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg><span>Inicio</span>
    </a>
@endcan
