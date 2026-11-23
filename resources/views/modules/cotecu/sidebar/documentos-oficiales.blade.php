@can('cotecu.view')
    <a href="{{ route('cotecu.documentos-oficiales') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('cotecu.documentos-oficiales')]) @if(request()->routeIs('cotecu.documentos-oficiales')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 11h18"/></svg><span>Documentos oficiales</span>
    </a>
@endcan
