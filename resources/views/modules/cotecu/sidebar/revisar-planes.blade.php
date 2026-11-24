@can('cotecu.plan.review')
    <a href="{{ route('cotecu.planes.revisiones') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.planes.revisiones*')]) @if(request()->routeIs('cotecu.planes.revisiones*')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/><path d="m9 14.5 2 2 4-4"/></svg><span>Revisar planes</span>
    </a>
@endcan
