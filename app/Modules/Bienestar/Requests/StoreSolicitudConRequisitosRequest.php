<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Models\TipoSolicitudRequisito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSolicitudConRequisitosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'id_tipo_solicitud' => ['required', 'integer', Rule::exists(TipoSolicitud::class, 'id_tipo_solicitud')->where('activo', true)],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
            // Los obligatorios se exigen uno a uno más abajo
            'archivos' => ['nullable', 'array'],
        ];

        // Validar dinámicamente cada requisito obligatorio del tipo de solicitud
        $idTipo = $this->input('id_tipo_solicitud');
        if (is_numeric($idTipo)) {
            $tipo = TipoSolicitud::with('requisitosActivos')->find($idTipo);
            if ($tipo) {
                foreach ($tipo->requisitosActivos as $req) {
                    $field = "archivos.{$req->id_requisito}";
                    $fieldRules = [];

                    if ($req->es_obligatorio) {
                        $fieldRules[] = 'required';
                    } else {
                        $fieldRules[] = 'nullable';
                    }

                    $fieldRules[] = 'file';

                    // Convertir formatos permitidos ej: "pdf,jpg,png"
                    $mimes = strtolower(str_replace(' ', '', $req->formatos_permitidos ?: 'pdf,jpg,png'));
                    $fieldRules[] = "mimes:{$mimes}";

                    $maxKb = ($req->tamano_maximo_mb ?: 5) * 1024;
                    $fieldRules[] = "max:{$maxKb}";

                    $rules[$field] = $fieldRules;
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'id_tipo_solicitud.required' => 'Debe seleccionar un tipo de solicitud o trámite.',
            'id_tipo_solicitud.exists' => 'El tipo de solicitud seleccionado no es válido.',
            'motivo.required' => 'Debe ingresar una justificación o motivo claro.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'archivos.required' => 'Debe adjuntar la documentación requerida.',
            'archivos.*.required' => 'Falta adjuntar «:attribute».',
            'archivos.*.file' => 'El archivo adjunto no es válido.',
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
