<?php

namespace App\Modules\GestionHorarios\Engine;

/** Valida exclusivamente datos recibidos; no crea ni confirma asociaciones institucionales. */
final class AnalizarAsociacionesOfertaSeccion
{
    public function evaluar(array $ofertas, array $secciones, array $asociaciones): array
    {
        $indiceOfertas = [];
        $indiceSecciones = [];
        foreach ($ofertas as $oferta) {
            $indiceOfertas[(string) $oferta['id_oferta']] = $oferta;
        }
        foreach ($secciones as $seccion) {
            $indiceSecciones[(string) $seccion['id_seccion']] = $seccion;
        }
        $errores = [];
        $vigentes = [];
        foreach ($asociaciones as $indice => $asociacion) {
            $oferta = $indiceOfertas[(string) ($asociacion['id_oferta'] ?? '')] ?? null;
            $seccion = $indiceSecciones[(string) ($asociacion['id_seccion'] ?? '')] ?? null;
            $estado = $asociacion['estado'] ?? null;
            if (! $oferta || ! $seccion || ! in_array($estado, ['VIGENTE', 'CERRADA'], true)) {
                $errores[] = ['indice' => $indice, 'codigo' => 'REFERENCIA_O_ESTADO_INVALIDO'];

                continue;
            }
            if ((string) $oferta['id_periodo'] !== (string) $seccion['id_periodo']) {
                $errores[] = ['indice' => $indice, 'codigo' => 'PERIODO_DISTINTO'];
            }
            if ((string) $oferta['id_curso_plan'] !== (string) $seccion['id_curso_plan']) {
                $errores[] = ['indice' => $indice, 'codigo' => 'CURSO_PLAN_DISTINTO'];
            }
            if ($estado === 'VIGENTE') {
                $id = (string) $seccion['id_seccion'];
                if (isset($vigentes[$id])) {
                    $errores[] = ['indice' => $indice, 'codigo' => 'SECCION_CON_VARIAS_VIGENTES'];
                }
                $vigentes[$id] = true;
            }
        }

        return $errores;
    }
}
