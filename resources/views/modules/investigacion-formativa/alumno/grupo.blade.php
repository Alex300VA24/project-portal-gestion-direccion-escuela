@extends('layouts.app')
@section('title', 'Mi grupo · Investigación Formativa')
@section('page-label', 'Mi grupo')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Estudiante</span>
            <h1>Mi grupo</h1>
            <p>
                Revisa el título propuesto por el grupo y el historial de revisiones.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="if-flash-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($conflicto !== null)
        <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
            {{ $conflicto }} Recarga la página antes de volver a intentar.
        </div>
    @endif

    @if ($errors->any())
        <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 17h.01"/></svg>
            <span>
                <strong>Revisa los siguientes errores</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </span>
        </div>
    @endif

    @if ($detalle === null)
        <div class="if-empty" style="margin:16px 0;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3>No se encontró información del grupo</h3>
            <p>Contacta a tu docente o director para obtener el acceso.</p>
        </div>
    @else
        @if (! $participa)
            <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
                Tu participación está retirada. Puedes consultar el grupo en modo lectura.
            </div>
        @endif

        <section class="if-panel" style="margin:16px 0;">
            <div class="if-panel-heading" style="border-bottom:1px solid var(--portal-border);padding:20px 22px;">
                <div>
                    <h1 id="titulo-grupo" style="margin:0 0 6px 0;font-size:1.5rem;font-weight:700;">{{ $detalle->grupo->nombre }}</h1>
                    <p style="margin:0;color:var(--portal-muted);font-size:13px;">
                        Sección {{ $seccion->codigo }} · {{ $detalle->entregados }} de 3 avances entregados
                    </p>
                </div>

                @if ($esCoordinador)
                    <span class="if-status is-neutral">Eres coordinador</span>
                @endif
            </div>

            @if ($detalle->proyecto !== null)
                <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin:0;padding:20px 22px;">
                    <div>
                        <dt style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--portal-muted);">Línea de investigación</dt>
                        <dd style="margin:4px 0 0;font-size:13px;font-weight:600;">{{ $detalle->proyecto->linea }}</dd>
                    </div>
                    <div>
                        <dt style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--portal-muted);">Tipo de producto</dt>
                        <dd style="margin:4px 0 0;font-size:13px;font-weight:600;">{{ $detalle->proyecto->tipo_producto }}</dd>
                    </div>
                </dl>
            @endif

            <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--portal-muted);margin:0;padding:20px 22px 0;">Integrantes</h2>
            <ul style="display:grid;gap:2px;margin:0;padding:20px 22px;" role="list">
                @foreach ($detalle->miembros as $miembro)
                    <li style="display:flex;align-items:center;justify-content:space-between;gap:2px;padding:2px;border-bottom:1px solid var(--portal-border);">
                        <span style="display:flex;align-items:center;gap:2px;">
                            <strong>{{ $miembro->nombre }}</strong>
                            <span style="font-size:11px;color:var(--portal-muted);">{{ $miembro->codigo }}</span>
                        </span>
                        @if ($miembro->es_coordinador)
                            <span class="if-status is-neutral" style="font-size:11px;">Coordinador</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="if-panel" style="margin:16px 0;">
            <div class="if-panel-heading" style="border-bottom:1px solid var(--portal-border);padding:20px 22px;">
                <h2 id="titulo-propuesta" style="margin:0;font-size:18px;">Título propuesto</h2>
                @if ($detalle->proyecto !== null)
                    <span class="if-status {{ $detalle->proyecto->estado === 'Aceptado' ? 'is-success' : ($detalle->proyecto->estado === 'Cambio solicitado' ? 'is-danger' : 'is-warning') }}">
                        {{ $detalle->proyecto->estado }} · versión {{ $detalle->proyecto->version }}
                    </span>
                @endif
            </div>

            @if ($detalle->proyecto === null)
                <p style="margin:0;padding:20px 22px;color:var(--portal-muted);">El grupo todavía no tiene un proyecto registrado.</p>
            @else
                <p style="margin:0;padding:20px 22px 0;white-space:pre-line;">{{ $detalle->proyecto->titulo }}</p>

                @if ($esCoordinador && $motivoBloqueo !== null)
                    <p style="margin:0;padding:20px 22px 0;color:var(--portal-warning);font-size:13px;">
                        {{ $motivoBloqueo }}
                    </p>
                @elseif ($esCoordinador)
                    <form method="POST" action="{{ route('investigacion-formativa.alumno.titulos.update', $seccion) }}" style="padding:20px 22px;">
                        @csrf
                        @method('PATCH')
                        <input name="version" type="hidden" value="{{ $detalle->proyecto->version }}">

                        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;">
                            <div style="flex:1;min-width:16rem;">
                                <label class="if-field" style="display:flex;flex-direction:column;gap:2px;" for="titulo-propuesto">
                                    <strong>Editar título</strong>
                                    <input
                                        style="min-height:44px;padding:9px 12px;border:1px solid var(--portal-border);border-radius:9px;background:var(--portal-surface);color:var(--portal-ink);"
                                        id="titulo-propuesto"
                                        maxlength="250"
                                        name="titulo"
                                        required
                                        type="text"
                                        value="{{ old('titulo', $detalle->proyecto->titulo) }}"
                                    >
                                </label>
                            </div>

                            <button
                                class="if-button"
                                style="margin:0;align-self:flex-end;"
                                type="submit"
                            >
                                Guardar título
                            </button>
                        </div>
                    </form>
                @endif
            @endif

            @if ($detalle->historial !== [])
                <h3 style="font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--portal-muted);margin:0;padding:20px 22px 0;">Historial de revisiones</h3>
                <div style="margin:0;padding:20px 22px;overflow-x:auto;">
                    <table class="if-table">
                        <thead>
                            <tr>
                                <th scope="col">Título</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Motivo</th>
                                <th scope="col">Autor</th>
                                <th scope="col">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detalle->historial as $revision)
                                <tr>
                                    <td data-label="Título">{{ $revision->titulo }}</td>
                                    <td data-label="Estado">{{ $revision->estado }}</td>
                                    <td data-label="Motivo">{{ $revision->motivo ?? '—' }}</td>
                                    <td data-label="Autor">{{ $revision->autor }}</td>
                                    <td data-label="Fecha" style="white-space:nowrap;">
                                        {{ \Carbon\CarbonImmutable::parse($revision->registrado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection