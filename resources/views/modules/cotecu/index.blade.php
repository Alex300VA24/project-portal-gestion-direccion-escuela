@extends('layouts.cotecu')

@section('title', 'Inicio')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>Inicio</h1>
                <p>Coordinación técnica curricular. Desde aquí se administrarán las listas de cotejo, los planes curriculares, los sílabos y sus revisiones.</p>
            </div>
        </header>

        <section class="quality-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v3H9z"/><path d="M15 4.5h2.5A1.5 1.5 0 0 1 19 6v13.5A1.5 1.5 0 0 1 17.5 21h-11A1.5 1.5 0 0 1 5 19.5V6a1.5 1.5 0 0 1 1.5-1.5H9"/><path d="M9 12l2 2 4-4"/></svg>
            <h2>Aún no hay secciones disponibles</h2>
            <p>Las listas de cotejo, los planes curriculares, los sílabos y las revisiones se irán incorporando de a poco. El contenido aparecerá aquí cuando cada sección esté habilitada.</p>
        </section>
    </div>
@endsection
