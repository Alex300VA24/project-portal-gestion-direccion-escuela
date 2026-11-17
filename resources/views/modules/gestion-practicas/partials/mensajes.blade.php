{{-- Avisos con los colores de estado de docs/DESIGN.md §2: correcto #e3f3ea/#0a5f3f · error #fde9e7/#721c16, radio 10 px. --}}
@if (session('status'))
    <div role="status" class="rounded-[10px] bg-[#e3f3ea] px-4 py-3 text-sm font-medium text-[color:var(--portal-success)]">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="rounded-[10px] bg-[#fde9e7] px-4 py-3 text-sm text-[#721c16]">
        <p class="font-bold">No se pudo completar la acción:</p>
        <ul class="list-disc ms-5 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
