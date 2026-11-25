@can('cotecu.plan.list')
    <a href="{{ route('cotecu.planes.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.planes.index')]) @if(request()->routeIs('cotecu.planes.index')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/></svg><span>Planes curriculares</span>
    </a>
@endcan
