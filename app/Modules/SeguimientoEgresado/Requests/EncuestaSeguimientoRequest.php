<?php

namespace App\Modules\SeguimientoEgresado\Requests;

use App\Modules\SeguimientoEgresado\Support\Esquema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encuesta de seguimiento profesional. Las reglas reproducen las restricciones del esquema vigente:
 * - país de residencia obligatorio, aunque no trabaje (medicion_laboral.id_pais_residencia NOT NULL);
 * - si no trabaja, los campos de empleo se descartan (no se almacenan);
 * - contacto_jefe solo con autorización de contacto a la empresa;
 * - region_empleo solo si el empleo es en Perú;
 * - fecha_fin de una formación no puede ser anterior a su inicio.
 * Los catálogos viajan por código y se resuelven a id al guardar.
 */
class EncuestaSeguimientoRequest extends FormRequest
{
    use MensajesDeValidacion;

    public const SITUACIONES = ['TRABAJA', 'NO_TRABAJA', 'OTRA'];

    public const NIVELES_INGLES = ['NINGUNO', 'BASICO', 'INTERMEDIO', 'AVANZADO'];

    public const REGIONES = ['LA_LIBERTAD', 'OTRA_REGION'];

    public const MAX_FORMACIONES = 10;

    public function rules(): array
    {
        return self::reglasPara($this->all());
    }

    /**
     * @param  array<string, mixed>  $d  datos enviados; las reglas condicionales dependen de ellos
     * @param  bool  $verificarCatalogos  comprueba que los códigos existan y estén activos en la BD
     */
    public static function reglasPara(array $d, bool $verificarCatalogos = true): array
    {
        $catalogo = fn (string $tabla): array => $verificarCatalogos
            ? [Rule::exists(Esquema::validar($tabla), 'codigo')->where('activo', true)]
            : [];

        $trabaja = ($d['situacion'] ?? null) === 'TRABAJA';
        $empleoEnPeru = $trabaja && ($d['pais_empleador'] ?? null) === 'PE';
        $autoriza = $trabaja && filter_var($d['autoriza_contacto_empresa'] ?? null, FILTER_VALIDATE_BOOLEAN);
        $brechaOtra = in_array('OTRA', (array) ($d['brechas'] ?? []), true);
        $fortalezaOtra = in_array('OTRA', (array) ($d['fortalezas'] ?? []), true);

        // Los campos de empleo solo existen para quien trabaja; en otro caso se excluyen del resultado.
        $soloSiTrabaja = fn (array $reglas): array => $trabaja ? $reglas : ['exclude'];

        $reglas = [
            'situacion' => ['required', Rule::in(self::SITUACIONES)],
            'pais_residencia' => ['required', 'string', ...$catalogo('cat_pais')],
            'ciudad_residencia' => ['nullable', 'string', 'min:2', 'max:120', Formatos::lugar()],
            'area_profesional' => ['required', 'string', ...$catalogo('cat_area_profesional')],
            'nivel_ingles' => ['required', Rule::in(self::NIVELES_INGLES)],
            'otros_idiomas' => ['nullable', 'string', 'min:2', 'max:160', Formatos::texto()],

            'trabajo_relacionado' => $soloSiTrabaja(['required', 'boolean']),
            'modalidad_laboral' => $soloSiTrabaja(['required', 'string', ...$catalogo('cat_modalidad_laboral')]),
            'modalidad_trabajo' => $soloSiTrabaja(['required', 'string', ...$catalogo('cat_modalidad_trabajo')]),
            'sector_laboral' => $soloSiTrabaja(['required', 'string', ...$catalogo('cat_sector_laboral')]),
            'nombre_empleador_cliente' => $soloSiTrabaja(['required', 'string', 'min:2', 'max:160', Formatos::texto()]),
            'pais_empleador' => $soloSiTrabaja(['required', 'string', ...$catalogo('cat_pais')]),
            'region_empleo' => $empleoEnPeru ? ['required', Rule::in(self::REGIONES)] : ['exclude'],
            'autoriza_contacto_empresa' => $soloSiTrabaja(['required', 'boolean']),
            'contacto_jefe' => $autoriza
                ? ['required', 'string', 'max:180', Formatos::correoOCelular()]
                : ['exclude'],

            'satisfaccion' => ['required', 'string', ...$catalogo('cat_nivel_satisfaccion')],
            'comentario' => ['nullable', 'string', 'max:1000', Formatos::texto()],

            'brechas' => ['nullable', 'array'],
            'brechas.*' => ['string', 'distinct', ...$catalogo('cat_habilidad')],
            'detalle_brecha_otra' => $brechaOtra ? ['required', 'string', 'min:2', 'max:180', Formatos::texto()] : ['exclude'],
            'fortalezas' => ['nullable', 'array'],
            'fortalezas.*' => ['string', 'distinct', ...$catalogo('cat_habilidad')],
            'detalle_fortaleza_otra' => $fortalezaOtra ? ['required', 'string', 'min:2', 'max:180', Formatos::texto()] : ['exclude'],

            'formaciones' => ['nullable', 'array', 'max:'.self::MAX_FORMACIONES],
        ];

        foreach (array_keys((array) ($d['formaciones'] ?? [])) as $i) {
            $reglas += self::reglasDeFormacion($i, $d['formaciones'][$i] ?? [], $catalogo);
        }

        return $reglas;
    }

    private static function reglasDeFormacion(int|string $i, mixed $item, \Closure $catalogo): array
    {
        $item = is_array($item) ? $item : [];
        $estado = $item['estado'] ?? null;
        $conInicio = ! empty($item['fecha_inicio']);

        $fin = ['date', 'date_format:Y-m-d', 'before_or_equal:today'];
        if ($conInicio) {
            $fin[] = "after_or_equal:formaciones.$i.fecha_inicio";
        }

        return [
            "formaciones.$i.tipo" => ['required', 'string', ...$catalogo('cat_tipo_formacion')],
            "formaciones.$i.estado" => ['required', 'string', ...$catalogo('cat_estado_formacion')],
            "formaciones.$i.area_formacion" => ['required', 'string', 'min:2', 'max:140', Formatos::texto()],
            "formaciones.$i.institucion" => ['required', 'string', 'min:2', 'max:160', Formatos::texto()],
            "formaciones.$i.descripcion" => ['nullable', 'string', 'max:500', Formatos::texto()],
            "formaciones.$i.fecha_inicio" => ['nullable', 'date', 'date_format:Y-m-d', 'after:1949-12-31', 'before_or_equal:today'],
            // En curso no tiene fecha de fin; culminada la exige.
            "formaciones.$i.fecha_fin" => match ($estado) {
                'EN_CURSO' => ['exclude'],
                'CULMINADA' => ['required', ...$fin],
                default => ['nullable', ...$fin],
            },
        ];
    }

    public function messages(): array
    {
        return self::mensajesBase();
    }

    public function attributes(): array
    {
        return [
            'situacion' => 'situación laboral',
            'pais_residencia' => 'país de residencia',
            'ciudad_residencia' => 'ciudad de residencia',
            'area_profesional' => 'área profesional',
            'nivel_ingles' => 'nivel de inglés',
            'otros_idiomas' => 'otros idiomas',
            'trabajo_relacionado' => 'relación del trabajo con la carrera',
            'modalidad_laboral' => 'tipo de vínculo',
            'modalidad_trabajo' => 'modalidad de trabajo',
            'sector_laboral' => 'sector',
            'nombre_empleador_cliente' => 'empleador o cliente',
            'pais_empleador' => 'país del empleo',
            'region_empleo' => 'ubicación del empleo en el Perú',
            'autoriza_contacto_empresa' => 'autorización de contacto a la empresa',
            'contacto_jefe' => 'contacto del jefe directo',
            'satisfaccion' => 'satisfacción',
            'comentario' => 'comentario',
            'brechas' => 'habilidades a reforzar',
            'fortalezas' => 'fortalezas',
            'detalle_brecha_otra' => 'detalle de la otra habilidad a reforzar',
            'detalle_fortaleza_otra' => 'detalle de la otra fortaleza',
            'formaciones' => 'formación posterior',
            'formaciones.*.tipo' => 'tipo de formación',
            'formaciones.*.estado' => 'estado de la formación',
            'formaciones.*.area_formacion' => 'área de formación',
            'formaciones.*.institucion' => 'institución',
            'formaciones.*.descripcion' => 'descripción de la formación',
            'formaciones.*.fecha_inicio' => 'fecha de inicio',
            'formaciones.*.fecha_fin' => 'fecha de fin',
        ];
    }
}
