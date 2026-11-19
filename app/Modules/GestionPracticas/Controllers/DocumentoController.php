<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\DocumentoPractica;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Requests\DocumentoRequest;
use App\Modules\GestionPracticas\Requests\RevisarDocumentoRequest;
use App\Modules\GestionPracticas\Services\DocumentoService;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Documentos de una práctica: el estudiante los sube; el administrador o el supervisor los revisan. */
final class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $servicio) {}

    public function store(DocumentoRequest $request, Practica $practica): RedirectResponse
    {
        Gate::authorize('gestionar', $practica);

        $this->servicio->subir($practica, Actor::actual(), $request->file('archivo'), $request->validated('tipo_documento'));

        return back()->with('status', 'Documento subido.');
    }

    public function descargar(DocumentoPractica $documento): StreamedResponse
    {
        Gate::authorize('view', Practica::findOrFail($documento->id_practica));

        $archivo = $this->servicio->ubicacion($documento);

        abort_if($archivo === null, 404);

        return Storage::disk($archivo['disco'])->download($archivo['ruta'], $archivo['nombre']);
    }

    public function revisar(RevisarDocumentoRequest $request, DocumentoPractica $documento): RedirectResponse
    {
        Gate::authorize('revisarDocumentos', Practica::findOrFail($documento->id_practica));

        $this->servicio->revisar($documento, Actor::actual(), $request->validated('estado'));

        return back()->with('status', 'Revisión del documento registrada.');
    }
}
