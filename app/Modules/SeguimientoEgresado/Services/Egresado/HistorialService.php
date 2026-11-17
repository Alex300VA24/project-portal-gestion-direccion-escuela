<?php

namespace App\Modules\SeguimientoEgresado\Services\Egresado;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lectura del historial de seguimiento de un egresado. Siempre se filtra por el egresado indicado:
 * quien llama debe pasar el id de su propio expediente (o autorizar a Secretaría) antes de usarlo.
 */
final class HistorialService
{
    /**
     * Una fila por campaña a la que fue invitado, de la más reciente a la más antigua.
     * «No respondió» no es una situación laboral: solo indica ausencia de medición.
     *
     * @return list<array<string, mixed>>
     */
    public function campanias(int $egresadoId): array
    {
        $hoy = CarbonImmutable::now()->toDateString();

        return DB::table('seguimiento_egresado.vw_historial_seguimiento')
            ->where('id_egresado', $egresadoId)
            ->orderByDesc('fecha_inicio')->orderByDesc('id_campania')
            ->get()
            ->map(function (object $fila) use ($hoy): array {
                $datos = (array) $fila;
                $datos['estado_respuesta'] = match (true) {
                    $fila->id_medicion !== null => 'RESPONDIDA',
                    in_array($fila->estado_invitacion, ['EXPIRADA', 'ANULADA'], true) || $fila->fecha_fin < $hoy => 'NO_RESPONDIO',
                    default => 'PENDIENTE',
                };

                return $datos;
            })->all();
    }

    /**
     * Detalle de una medición, solo si pertenece al egresado indicado (si no, null).
     *
     * @return array<string, mixed>|null
     */
    public function medicion(int $egresadoId, int $medicionId): ?array
    {
        $m = DB::table('seguimiento_egresado.medicion_laboral as m')
            ->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->join('seguimiento_egresado.cat_situacion_laboral as sl', 'sl.id', '=', 'm.id_situacion_laboral')
            ->join('seguimiento_egresado.cat_pais as pr', 'pr.id', '=', 'm.id_pais_residencia')
            ->leftJoin('seguimiento_egresado.cat_modalidad_laboral as ml', 'ml.id', '=', 'm.id_modalidad_laboral')
            ->leftJoin('seguimiento_egresado.cat_modalidad_trabajo as mt', 'mt.id', '=', 'm.id_modalidad_trabajo')
            ->leftJoin('seguimiento_egresado.cat_sector_laboral as sc', 'sc.id', '=', 'm.id_sector_laboral')
            ->leftJoin('seguimiento_egresado.cat_area_profesional as ap', 'ap.id', '=', 'm.id_area_profesional')
            ->leftJoin('seguimiento_egresado.cat_pais as pe', 'pe.id', '=', 'm.id_pais_empleador')
            ->leftJoin('seguimiento_egresado.satisfaccion_egresado as se', 'se.id_medicion', '=', 'm.id')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 'se.id_nivel_satisfaccion')
            ->where('m.id', $medicionId)->where('i.id_egresado', $egresadoId)
            ->first([
                'm.id', 'm.fecha_respuesta', 'c.nombre as campania', 'c.periodo',
                'sl.codigo as situacion_codigo', 'sl.nombre as situacion',
                'm.trabajo_relacionado', 'ml.nombre as modalidad_laboral', 'mt.nombre as modalidad_trabajo',
                'sc.nombre as sector', 'ap.nombre as area_profesional', 'm.nombre_empleador_cliente',
                'pe.nombre as pais_empleador', 'm.region_empleo', 'pr.nombre as pais_residencia', 'm.ciudad_residencia',
                'm.nivel_ingles', 'm.otros_idiomas', 'm.autoriza_contacto_empresa', 'm.contacto_jefe',
                'ns.codigo as satisfaccion_codigo', 'ns.nombre as satisfaccion', 'se.comentario',
            ]);

        if ($m === null) {
            return null;
        }

        $habilidades = DB::table('seguimiento_egresado.medicion_habilidad as mh')
            ->join('seguimiento_egresado.cat_habilidad as h', 'h.id', '=', 'mh.id_habilidad')
            ->where('mh.id_medicion', $medicionId)
            ->orderBy('h.nombre')
            ->get(['mh.tipo', 'h.codigo', 'h.nombre', 'mh.detalle_otro']);

        return (array) $m + [
            'brechas' => $habilidades->where('tipo', 'BRECHA')->map(fn ($h) => (array) $h)->values()->all(),
            'fortalezas' => $habilidades->where('tipo', 'FORTALEZA')->map(fn ($h) => (array) $h)->values()->all(),
            'formaciones' => $this->formaciones($egresadoId, $medicionId),
        ];
    }

    /**
     * Versiones de contacto, de la más reciente a la más antigua (la vigente tiene vigente_hasta nulo).
     *
     * @return list<array<string, mixed>>
     */
    public function contactos(int $egresadoId): array
    {
        return DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresadoId)
            ->orderByDesc('vigente_desde')
            ->get(['id', 'correo_personal', 'celular_whatsapp', 'telefono_fijo', 'direccion', 'vigente_desde', 'vigente_hasta', 'origen_registro'])
            ->map(fn ($c) => (array) $c)->all();
    }

    /**
     * Formación posterior del egresado; con $medicionId, solo la declarada en esa medición.
     *
     * @return list<array<string, mixed>>
     */
    public function formaciones(int $egresadoId, ?int $medicionId = null): array
    {
        return DB::table('seguimiento_egresado.formacion_posterior as f')
            ->join('seguimiento_egresado.cat_tipo_formacion as t', 't.id', '=', 'f.id_tipo_formacion')
            ->join('seguimiento_egresado.cat_estado_formacion as e', 'e.id', '=', 'f.id_estado_formacion')
            ->where('f.id_egresado', $egresadoId)
            ->when($medicionId !== null, fn ($q) => $q->where('f.id_medicion_origen', $medicionId))
            ->orderByDesc('f.fecha_inicio')->orderByDesc('f.id')
            ->get(['f.id', 'f.id_medicion_origen', 't.nombre as tipo', 'e.codigo as estado_codigo', 'e.nombre as estado', 'f.area_formacion', 'f.institucion', 'f.descripcion', 'f.fecha_inicio', 'f.fecha_fin'])
            ->map(fn ($f) => (array) $f)->all();
    }
}
