<?php

namespace App\Modules\SeguimientoEgresado\Services\Egresado;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Guarda una respuesta de seguimiento como una medición nueva e inmutable, en una sola transacción:
 * medicion_laboral + satisfaccion_egresado + medicion_habilidad + formacion_posterior + versión de contacto,
 * y marca la invitación como completada. Una invitación admite una sola respuesta.
 * Los datos llegan validados por EncuestaSeguimientoRequest (catálogos por código).
 */
final class EncuestaService
{
    public function __construct(private readonly ContactoService $contacto) {}

    /**
     * @param  array<string, mixed>  $d  datos validados
     * @param  array<string, mixed>|null  $contacto  datos validados de contacto, si el egresado los actualizó
     * @return int id de la medición creada
     *
     * @throws InvitacionNoDisponible
     */
    public function registrar(int $egresadoId, int $invitacionId, array $d, ?array $contacto = null): int
    {
        try {
            return DB::transaction(function () use ($egresadoId, $invitacionId, $d, $contacto) {
                $ahora = CarbonImmutable::now();
                $this->exigirInvitacionDisponible($egresadoId, $invitacionId, $ahora);

                $idMedicion = DB::table('seguimiento_egresado.medicion_laboral')->insertGetId($this->filaDeMedicion($invitacionId, $d, $ahora));

                DB::table('seguimiento_egresado.satisfaccion_egresado')->insert([
                    'id_medicion' => $idMedicion,
                    'id_nivel_satisfaccion' => $this->idDe('cat_nivel_satisfaccion', $d['satisfaccion']),
                    'comentario' => self::opcional($d['comentario'] ?? null),
                ]);

                $this->guardarHabilidades($idMedicion, 'BRECHA', (array) ($d['brechas'] ?? []), $d['detalle_brecha_otra'] ?? null);
                $this->guardarHabilidades($idMedicion, 'FORTALEZA', (array) ($d['fortalezas'] ?? []), $d['detalle_fortaleza_otra'] ?? null);
                $this->guardarFormaciones($egresadoId, $idMedicion, (array) ($d['formaciones'] ?? []));

                if ($contacto !== null) {
                    $this->contacto->guardar($egresadoId, $contacto, 'FORMULARIO');
                }

                DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacionId)
                    ->update(['estado' => 'COMPLETADA', 'respondido_en' => self::ts($ahora)]);

                return $idMedicion;
            });
        } catch (UniqueConstraintViolationException) {
            // Dos envíos simultáneos de la misma invitación: la restricción única de id_invitacion deja pasar solo uno.
            throw new InvitacionNoDisponible(MotivoInvitacion::YaRespondida);
        }
    }

    private function exigirInvitacionDisponible(int $egresadoId, int $invitacionId, CarbonImmutable $ahora): void
    {
        $invitacion = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->where('i.id', $invitacionId)->where('i.id_egresado', $egresadoId)->where('i.proposito', 'SEGUIMIENTO')
            ->lockForUpdate()
            ->first(['i.estado', 'i.expira_en', 'c.estado as estado_campania']);

        $motivo = match (true) {
            $invitacion === null => MotivoInvitacion::NoEncontrada,
            $invitacion->estado === 'COMPLETADA' => MotivoInvitacion::YaRespondida,
            $invitacion->estado === 'ANULADA' => MotivoInvitacion::Anulada,
            $invitacion->estado === 'EXPIRADA' => MotivoInvitacion::Expirada,
            CarbonImmutable::parse($invitacion->expira_en)->lessThanOrEqualTo($ahora) => MotivoInvitacion::Vencida,
            $invitacion->estado_campania !== 'ABIERTA' => MotivoInvitacion::CampaniaCerrada,
            default => null,
        };

        if ($motivo !== null) {
            throw new InvitacionNoDisponible($motivo);
        }
    }

    private function filaDeMedicion(int $invitacionId, array $d, CarbonImmutable $ahora): array
    {
        $trabaja = $d['situacion'] === 'TRABAJA';

        // Quien no trabaja no almacena datos de empleo: todas esas columnas quedan en NULL.
        $empleo = fn (string $clave, ?string $tabla = null) => ! $trabaja || ! isset($d[$clave])
            ? null
            : ($tabla === null ? $d[$clave] : $this->idDe($tabla, $d[$clave]));

        return [
            'id_invitacion' => $invitacionId,
            'fecha_respuesta' => self::ts($ahora),
            'id_situacion_laboral' => $this->idDe('cat_situacion_laboral', $d['situacion']),
            'trabajo_relacionado' => $trabaja ? filter_var($d['trabajo_relacionado'], FILTER_VALIDATE_BOOLEAN) : null,
            'id_modalidad_laboral' => $empleo('modalidad_laboral', 'cat_modalidad_laboral'),
            'id_modalidad_trabajo' => $empleo('modalidad_trabajo', 'cat_modalidad_trabajo'),
            'id_sector_laboral' => $empleo('sector_laboral', 'cat_sector_laboral'),
            'id_area_profesional' => $this->idDe('cat_area_profesional', $d['area_profesional']),
            'id_pais_residencia' => $this->idDe('cat_pais', $d['pais_residencia']),
            'ciudad_residencia' => self::opcional($d['ciudad_residencia'] ?? null),
            'nombre_empleador_cliente' => $empleo('nombre_empleador_cliente'),
            'id_pais_empleador' => $empleo('pais_empleador', 'cat_pais'),
            'region_empleo' => $empleo('region_empleo'),
            'nivel_ingles' => $d['nivel_ingles'],
            'otros_idiomas' => self::opcional($d['otros_idiomas'] ?? null),
            'autoriza_contacto_empresa' => $trabaja ? filter_var($d['autoriza_contacto_empresa'], FILTER_VALIDATE_BOOLEAN) : null,
            'contacto_jefe' => $empleo('contacto_jefe'),
        ];
    }

    private function guardarHabilidades(int $idMedicion, string $tipo, array $codigos, ?string $detalleOtra): void
    {
        if ($codigos === []) {
            return;
        }

        $ids = $this->idsDe('cat_habilidad', $codigos);
        foreach ($codigos as $codigo) {
            DB::table('seguimiento_egresado.medicion_habilidad')->insert([
                'id_medicion' => $idMedicion,
                'id_habilidad' => $ids[$codigo],
                'tipo' => $tipo,
                'detalle_otro' => $codigo === 'OTRA' ? self::opcional($detalleOtra) : null,
            ]);
        }
    }

    private function guardarFormaciones(int $egresadoId, int $idMedicion, array $formaciones): void
    {
        foreach ($formaciones as $f) {
            DB::table('seguimiento_egresado.formacion_posterior')->insert([
                'id_egresado' => $egresadoId,
                'id_medicion_origen' => $idMedicion,
                'id_tipo_formacion' => $this->idDe('cat_tipo_formacion', $f['tipo']),
                'id_estado_formacion' => $this->idDe('cat_estado_formacion', $f['estado']),
                'area_formacion' => self::opcional($f['area_formacion'] ?? null),
                'institucion' => self::opcional($f['institucion'] ?? null),
                'descripcion' => self::opcional($f['descripcion'] ?? null),
                'fecha_inicio' => self::opcional($f['fecha_inicio'] ?? null),
                'fecha_fin' => self::opcional($f['fecha_fin'] ?? null),
            ]);
        }
    }

    private function idDe(string $tabla, string $codigo): int
    {
        return $this->idsDe($tabla, [$codigo])[$codigo];
    }

    /** @return array<string, int> */
    private function idsDe(string $tabla, array $codigos): array
    {
        $codigos = array_values(array_unique($codigos));
        $ids = DB::table('seguimiento_egresado.'.$tabla)->whereIn('codigo', $codigos)->where('activo', true)->pluck('id', 'codigo')->all();

        foreach ($codigos as $codigo) {
            if (! isset($ids[$codigo])) {
                throw new \InvalidArgumentException("El código «{$codigo}» no existe o está inactivo en {$tabla}.");
            }
        }

        return array_map('intval', $ids);
    }

    private static function opcional(?string $valor): ?string
    {
        $valor = $valor === null ? '' : trim($valor);

        return $valor === '' ? null : $valor;
    }

    private static function ts(CarbonImmutable $momento): string
    {
        return $momento->format('Y-m-d H:i:s.uP');
    }
}
