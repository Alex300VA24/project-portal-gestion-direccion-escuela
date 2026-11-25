@can('cotecu.silabo.upload')
    <a href="{{ route('cotecu.mis-silabos') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.mis-silabos')]) @if(request()->routeIs('cotecu.mis-silabos')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/><path d="M12 19v-7"/><path d="m9 15 3-3 3 3"/></svg><span>Mis sílabos</span>
    </a>
@endcan
