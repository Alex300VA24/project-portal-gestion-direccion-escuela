<?php

namespace App\Modules\GestionHorarios\Services;

/** Resume una lectura autorizada; no sustituye la validación del motor. */
final class ResumenPreparacionAcademica
{
    public function analizar(array $fuentes): array
    {
        $resultados = [];
        $agregar = static function (string $nivel, string $mensaje) use (&$resultados): void {
            $resultados[] = compact('nivel', 'mensaje');
        };

        if ($fuentes['ofertas'] === []) {
            $agregar('ERROR', 'No hay ofertas registradas para el plan y periodo seleccionados.');
        } elseif (! array_filter($fuentes['ofertas'], fn ($o) => $o['estado'] === 'OFERTADA')) {
            $agregar('ERROR', 'No hay ofertas en estado ofertado para el plan y periodo seleccionados.');
        }
        if ($fuentes['secciones'] === []) {
            $agregar('ERROR', 'No hay secciones registradas para el plan y periodo seleccionados.');
        } elseif (! array_filter($fuentes['secciones'], fn ($s) => $s['estado'] === 'ACTIVA')) {
            $agregar('ERROR', 'No hay secciones activas para el plan y periodo seleccionados.');
        }
        if ($fuentes['oferta_seccion'] === null) {
            $agregar('ERROR', 'No se pueden comprobar las asociaciones entre ofertas y secciones.');
        } else {
            $vigentes = array_filter($fuentes['oferta_seccion'], fn ($r) => $r['estado'] === 'VIGENTE');
            $secciones = array_map('strval', array_column($vigentes, 'id_seccion'));
            $ofertas = array_map('strval', array_column($vigentes, 'id_oferta'));
            if (count($secciones) !== count(array_unique($secciones))) {
                $agregar('ERROR', 'Hay secciones con más de una asociación vigente.');
            }
            $ofertasCerradas = array_map('strval', array_column(array_filter($fuentes['ofertas'], fn ($o) => $o['estado'] === 'CERRADA'), 'id_oferta'));
            if (array_intersect($ofertas, $ofertasCerradas)) {
                $agregar('ERROR', 'Hay asociaciones vigentes con ofertas cerradas; revise las asociaciones.');
            }
            $sinOferta = array_filter($fuentes['secciones'], fn ($s) => $s['estado'] === 'ACTIVA' && ! in_array((string) $s['id_seccion'], $secciones, true));
            $sinSeccion = array_filter($fuentes['ofertas'], fn ($o) => $o['estado'] === 'OFERTADA' && ! in_array((string) $o['id_oferta'], $ofertas, true));
            if ($sinOferta) {
                $agregar('ERROR', 'Hay secciones activas sin asociación vigente y compatible con una oferta.');
            }
            if ($sinSeccion) {
                $agregar('ERROR', 'Hay ofertas ofertadas sin secciones asociadas válidas.');
            }
        }

        foreach ($fuentes['secciones'] as $seccion) {
            if ($seccion['estado'] === 'ACTIVA' && (! is_numeric($seccion['capacidad']) || (int) $seccion['capacidad'] <= 0)) {
                $agregar('ERROR', 'Hay secciones activas sin capacidad válida.');
                break;
            }
        }
        foreach ($fuentes['actividades'] as $actividad) {
            if (! in_array($actividad['tipo_actividad'], ['TEORIA', 'PRACTICA', 'LABORATORIO', 'TALLER'], true) || ! is_numeric($actividad['minutos_sesion']) || (int) $actividad['minutos_sesion'] <= 0) {
                $agregar('ERROR', 'Hay actividades con tipo o duración pendientes de revisión.');
                break;
            }
        }
        if (in_array('TALLER', array_column($fuentes['actividades'], 'tipo_actividad'), true)) {
            $agregar('ADVERTENCIA', 'Hay talleres cuya correspondencia con el motor requiere revisión.');
        }
        $codigos = array_column($fuentes['diagnosticos'], 'codigo');
        if (in_array('SECCION_SIN_DOCENTE', $codigos, true)) {
            $agregar('ERROR', 'Hay secciones sin actividades o sin asignación docente confirmada.');
        }
        if (in_array('ACTIVIDAD_SIN_DOCENTE', $codigos, true)) {
            $agregar('ERROR', 'Hay actividades sin asignación docente confirmada.');
        }
        $agregar('ADVERTENCIA', 'Aún faltan contratos para comprobar disponibilidad, ambientes, población, calendario y restricciones.');
        $agregar('INFORMACION', 'La comprobación es parcial y no demuestra factibilidad ni habilita la generación.');

        return $resultados;
    }
}
