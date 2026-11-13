@can('cotecu.silabo.list')
    <a href="{{ route('cotecu.silabos.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.silabos.index')]) @if(request()->routeIs('cotecu.silabos.index')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg><span>Sílabos</span>
    </a>
@endcan
