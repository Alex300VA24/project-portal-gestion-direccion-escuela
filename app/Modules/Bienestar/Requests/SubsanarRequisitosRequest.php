<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\TipoSolicitudRequisito;
use Illuminate\Foundation\Http\FormRequest;

final class SubsanarRequisitosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'archivos' => ['required', 'array'],
        ];

        /** @var SolicitudApoyo|null $solicitud */
        $solicitud = $this->route('solicitud');
        if ($solicitud) {
            $solicitud->loadMissing('documentosRequisitos.requisito');
            $docsObservados = $solicitud->documentosRequisitos->where('estado_revision', 'observado');

            foreach ($docsObservados as $doc) {
                $req = $doc->requisito;
                if (! $req) {
                    continue;
                }

                $field = "archivos.{$req->id_requisito}";
                $mimes = strtolower(str_replace(' ', '', $req->formatos_permitidos ?: 'pdf,jpg,png'));
                $maxKb = ($req->tamano_maximo_mb ?: 5) * 1024;

                $rules[$field] = ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'archivos.required' => 'Debe adjuntar los documentos observados para subsanar.',
            'archivos.*.required' => 'Falta adjuntar «:attribute».',
            'archivos.*.mimes' => '«:attribute» solo admite :values.',
            'archivos.*.max' => '«:attribute» supera el tamaño máximo.',
        ];
    }

    public function attributes(): array
    {
        $ids = array_map('intval', array_map(fn ($c) => substr($c, 9), preg_grep('/^archivos\.\d+$/', array_keys($this->rules()))));

        return TipoSolicitudRequisito::whereIn('id_requisito', $ids)->pluck('nombre', 'id_requisito')
            ->mapWithKeys(fn ($nombre, $id) => ["archivos.{$id}" => $nombre])->all();
    }
}
