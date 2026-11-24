<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Requests\ModuleRequest;

/** Datos de la práctica que registra el estudiante (la carta de aceptación es obligatoria solo al registrar). */
final class PracticaRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'area' => ['required', 'string', 'max:150'],
            'funciones' => ['required', 'string', 'max:5000'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'horas_totales' => ['required', 'integer', 'min:1', 'max:3000'],
            'id_periodo' => ['nullable', 'integer', function (string $atributo, mixed $valor, \Closure $fallar): void {
                if ($valor !== null && ! PeriodoAcademico::query()->whereKey($valor)->exists()) {
                    $fallar('El período académico no existe.');
                }
            }],
            'carta' => [$this->isMethod('POST') ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'horas_totales.integer' => 'Las horas planificadas deben ser un número entero.',
            'horas_totales.min' => 'Las horas planificadas deben ser al menos :min.',
            'horas_totales.max' => 'Las horas planificadas no pueden superar :max.',
            'carta.required' => 'Adjunte la carta de aceptación de la empresa.',
            'carta.file' => 'Seleccione un archivo válido como carta de aceptación.',
            'carta.mimes' => 'La carta de aceptación debe ser un PDF o una imagen (JPG o PNG).',
            'carta.max' => 'La carta de aceptación no puede pesar más de 5 MB.',
            'carta.uploaded' => 'No se pudo subir la carta de aceptación. Verifique que pese menos de 5 MB.',
        ];
    }
}
