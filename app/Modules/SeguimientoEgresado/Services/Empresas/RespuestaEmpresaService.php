<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use App\Modules\SeguimientoEgresado\Support\Esquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Guarda una respuesta empresarial en una sola transacción y marca la invitación como completada.
 * MERCADO nunca guarda satisfacción; solo DESEMPENO con egresado alimenta I5.
 */
final class RespuestaEmpresaService
{
    public const CODIGO_OTRA = 'OTRA';

    public const MAXIMO_HABILIDADES = 5;

    public function __construct(private readonly EnlaceEmpresa $enlaces) {}

    /**
     * Reglas de servidor según el tipo de encuesta.
     *
     * @return array<string, mixed>
     */
    public static function reglas(string $contexto): array
    {
        $habilidad = Rule::exists(Esquema::validar('cat_habilidad'), 'codigo')->where('activo', true);

        return [
            'organizacion' => ['required', 'string', 'max:180'],
            'sector' => ['required', 'string', Rule::exists(Esquema::validar('cat_sector_empresa'), 'codigo')->where('activo', true)],
            'cargo' => ['nullable', 'string', 'max:120'],
            'demandas' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_HABILIDADES],
            'demandas.*' => ['string', 'distinct', $habilidad],
            'detalle_demanda_otra' => ['nullable', 'string', 'max:180', 'required_if:demanda_otra,1'],
            'brechas' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO_HABILIDADES],
            'brechas.*' => ['string', 'distinct', $habilidad],
            'detalle_brecha_otra' => ['nullable', 'string', 'max:180', 'required_if:brecha_otra,1'],
            'satisfaccion' => $contexto === 'DESEMPENO'
                ? ['required', 'string', Rule::exists(Esquema::validar('cat_nivel_satisfaccion'), 'codigo')->where('activo', true)]
                : ['prohibited'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'organizacion.required' => 'Indica el nombre de la organización.',
            'organizacion.max' => 'El nombre de la organización admite hasta 180 caracteres.',
            'sector.required' => 'Selecciona el sector de la organización.',
            'sector.exists' => 'Selecciona un sector de la lista.',
            'cargo.max' => 'El cargo admite hasta 120 caracteres.',
            'demandas.required' => 'Selecciona al menos una habilidad demandada.',
            'demandas.min' => 'Selecciona al menos una habilidad demandada.',
            'demandas.max' => 'Selecciona hasta '.self::MAXIMO_HABILIDADES.' habilidades demandadas.',
            'demandas.*.exists' => 'Selecciona habilidades de la lista.',
            'demandas.*.distinct' => 'No repitas una habilidad.',
            'detalle_demanda_otra.required_if' => 'Describe la otra habilidad demandada.',
            'detalle_demanda_otra.max' => 'El detalle admite hasta 180 caracteres.',
            'brechas.required' => 'Selecciona al menos un aspecto por reforzar.',
            'brechas.min' => 'Selecciona al menos un aspecto por reforzar.',
            'brechas.max' => 'Selecciona hasta '.self::MAXIMO_HABILIDADES.' aspectos por reforzar.',
            'brechas.*.exists' => 'Selecciona aspectos de la lista.',
            'brechas.*.distinct' => 'No repitas un aspecto.',
            'detalle_brecha_otra.required_if' => 'Describe el otro aspecto por reforzar.',
            'detalle_brecha_otra.max' => 'El detalle admite hasta 180 caracteres.',
            'satisfaccion.required' => 'Selecciona tu nivel de satisfacción con el desempeño.',
            'satisfaccion.exists' => 'Selecciona un nivel de satisfacción de la lista.',
            'satisfaccion.prohibited' => 'Esta encuesta no evalúa a un egresado y no admite satisfacción.',
            'comentario.max' => 'El comentario admite hasta 1000 caracteres.',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  datos validados
     * @return int id de la respuesta creada
     *
     * @throws EnlaceRechazado
     */
    public function registrar(string $tokenPlano, array $datos): int
    {
        try {
            return DB::transaction(function () use ($tokenPlano, $datos) {
                $ahora = CarbonImmutable::now();
                $invitacion = $this->enlaces->buscar(EnlaceEmpresa::hashDe($tokenPlano), bloquear: true);
                $motivo = $this->enlaces->evaluar($invitacion, $ahora);

                if ($motivo !== MotivoEnlace::Vigente) {
                    throw new EnlaceRechazado($motivo);
                }

                $satisfaccion = $invitacion->contexto === 'DESEMPENO' && $invitacion->id_egresado !== null
                    ? DB::table('seguimiento_egresado.cat_nivel_satisfaccion')->where('codigo', $datos['satisfaccion'])->value('id')
                    : null;

                $idRespuesta = DB::table('seguimiento_egresado.respuesta_empresa')->insertGetId([
                    'id_invitacion_empresa' => $invitacion->id,
                    'fecha_respuesta' => $ahora->format('Y-m-d H:i:sP'),
                    'id_nivel_satisfaccion' => $satisfaccion,
                    'id_sector_empresa' => DB::table('seguimiento_egresado.cat_sector_empresa')->where('codigo', $datos['sector'])->value('id'),
                    'cargo_informante' => self::opcional($datos['cargo'] ?? null),
                    'organizacion_informada' => trim($datos['organizacion']),
                    'comentario' => self::opcional($datos['comentario'] ?? null),
                ]);

                $this->guardarHabilidades($idRespuesta, 'DEMANDA', $datos['demandas'], $datos['detalle_demanda_otra'] ?? null);
                $this->guardarHabilidades($idRespuesta, 'BRECHA', $datos['brechas'], $datos['detalle_brecha_otra'] ?? null);

                DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $invitacion->id)
                    ->update(['estado' => 'COMPLETADA', 'respondido_en' => $ahora->format('Y-m-d H:i:sP')]);

                return (int) $idRespuesta;
            });
        } catch (UniqueConstraintViolationException) {
            // Dos envíos simultáneos: la restricción única de la base de datos deja pasar solo uno.
            throw new EnlaceRechazado(MotivoEnlace::Usado);
        }
    }

    /** @param  list<string>  $codigos */
    private function guardarHabilidades(int $idRespuesta, string $tipo, array $codigos, ?string $detalleOtra): void
    {
        $ids = DB::table('seguimiento_egresado.cat_habilidad')->whereIn('codigo', $codigos)->pluck('id', 'codigo');

        foreach ($ids as $codigo => $id) {
            DB::table('seguimiento_egresado.respuesta_empresa_habilidad')->insert([
                'id_respuesta' => $idRespuesta,
                'id_habilidad' => $id,
                'tipo' => $tipo,
                'detalle_otro' => $codigo === self::CODIGO_OTRA ? self::opcional($detalleOtra) : null,
            ]);
        }
    }

    private static function opcional(?string $valor): ?string
    {
        $valor = $valor === null ? null : trim($valor);

        return $valor === '' ? null : $valor;
    }
}
