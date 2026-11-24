<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Modules\GestionPracticas\Support\Catalogos;
use App\Shared\Requests\ModuleRequest;
use Illuminate\Validation\Rule;

final class DocumentoRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(array_keys(Catalogos::TIPOS_DOCUMENTO))],
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'tipo_documento.in' => 'Seleccione el tipo de documento de la lista.',
            'archivo.required' => 'Seleccione el archivo que desea subir.',
            'archivo.mimes' => 'El archivo debe ser un PDF, un documento de Word (DOC o DOCX) o una imagen (JPG o PNG).',
            'archivo.max' => 'El archivo no puede pesar más de 10 MB.',
            'archivo.uploaded' => 'No se pudo subir el archivo. Verifique que pese menos de 10 MB.',
        ];
    }
}
