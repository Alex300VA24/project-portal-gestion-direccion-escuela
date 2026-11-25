@extends('modules.pre-matricula.layout')

@section('title', 'Mi prematrícula')

@section('content')
    <section class="quality-panel">
        <div class="quality-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/><path d="m17 3 4 4M21 3l-4 4"/></svg>
            <h2>Tu cuenta no está vinculada a un estudiante</h2>
            <p>Para prematricularte, tu correo institucional ({{ auth()->user()->email }}) debe estar registrado en el sistema académico y asociado a tu código universitario. Comunícate con la secretaría de la escuela.</p>
        </div>
    </section>
@endsection
