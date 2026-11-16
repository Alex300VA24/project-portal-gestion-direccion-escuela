@if (session('status'))
    <div role="status" class="rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
        <p class="font-semibold">No se pudo completar la acción:</p>
        <ul class="list-disc ms-5 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
