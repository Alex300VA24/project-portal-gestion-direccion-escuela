<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class GuardarCronogramaRequest extends FormRequest
{
    private const ZONA_HORARIA = 'America/Lima';

    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('configurar', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'fechas' => ['required', 'array', 'size:3'],
            'fechas.*' => ['required', 'string', 'date_format:Y-m-d\TH:i'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fechas = $this->input('fechas');

            if (! is_array($fechas) || count($fechas) !== 3) {
                return;
            }

            $zona = new DateTimeZone(self::ZONA_HORARIA);
            $instantes = [];

            foreach ($fechas as $fecha) {
                $local = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) $fecha, $zona);

                if ($local === false) {
                    return;
                }

                $instantes[] = $local->getTimestamp();
            }

            if (! ($instantes[0] < $instantes[1] && $instantes[1] < $instantes[2])) {
                $validator->errors()->add(
                    'fechas',
                    'Las fechas deben respetar el orden de las tres unidades.',
                );
            }
        });
    }
}
