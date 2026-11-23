<?php

namespace App\Modules\Cotecu\Models;

use App\Shared\Models\ModuleModel;

final class DocumentoOficial extends ModuleModel
{
    protected $table = 'cotecu.documentos_oficiales';

    /**
     * URL de solo lectura para el lector embebido: la misma fuente
     * oficial con todo lo que sigue al id del documento (edit, usp,
     * preview…) reemplazado por /preview, que es la vista que Google
     * Docs/Sheets sí permite mostrar dentro de un iframe.
     */
    public function previewUrl(): string
    {
        $url = preg_replace('#(/d/[^/]+).*#', '$1', $this->url) ?? $this->url;

        return $url.'/preview';
    }
}
