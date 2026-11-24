@can('cotecu.silabo.review')
    <a href="{{ route('cotecu.silabos.revisiones') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.silabos.revisiones')]) @if(request()->routeIs('cotecu.silabos.revisiones')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/><path d="m9 14.5 2 2 4-4"/></svg><span>Revisar sílabos</span>
    </a>
@endcan
