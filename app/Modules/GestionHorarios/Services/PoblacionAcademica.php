<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Engine\AnalizarAsociacionesOfertaSeccion;

/** Datos agregados exclusivamente; una capacidad o demanda no es población. */
final class PoblacionAcademica
{
    /** $registros es un contrato de entrada para pruebas y futuros proveedores; no existe proveedor institucional conectado. */
    public function analizar(int $periodo, array $secciones, ?array $demanda, ?array $registros = null, array $ofertas = [], array $asociaciones = []): array
    {
        $diagnosticos = [];
        $erroresAsociacion = (new AnalizarAsociacionesOfertaSeccion)->evaluar($ofertas, $secciones, $asociaciones);
        $invalidos = array_fill_keys(array_column($erroresAsociacion, 'indice'), true);
        $vinculos = [];
        foreach ($asociaciones as $indice => $asociacion) {
            if (($asociacion['estado'] ?? null) === 'VIGENTE') {
                $idSeccion = (string) $asociacion['id_seccion'];
                $vinculos[$idSeccion][] = isset($invalidos[$indice]) ? null : (string) $asociacion['id_oferta'];
            }
        }
        $porSeccion = [];
        $ids = [];
        foreach ($secciones as $seccion) {
            $ids[(string) $seccion['id_seccion']] = $seccion;
        }
        foreach ($registros ?? [] as $registro) {
            $id = (string) ($registro['id_seccion'] ?? '');
            $cantidad = $registro['cantidad'] ?? null;
            $ofertaInvalida = isset($registro['id_oferta']) && (count($vinculos[$id] ?? []) !== 1 || (string) $registro['id_oferta'] !== ($vinculos[$id][0] ?? null));
            if (! isset($ids[$id]) || (int) ($registro['id_periodo'] ?? 0) !== $periodo || (int) ($ids[$id]['id_periodo'] ?? 0) !== $periodo || (string) ($registro['id_curso_plan'] ?? '') !== (string) ($ids[$id]['id_curso_plan'] ?? '') || $ofertaInvalida || ! is_int($cantidad) || $cantidad < 0 || ! in_array($registro['estado'] ?? null, ['CONOCIDA', 'ESTIMADA'], true) || ($registro['validacion'] ?? null) !== 'CONFIRMADA' || ! is_string($registro['fuente'] ?? null) || trim($registro['fuente']) === '' || ! $this->fechaValida($registro['fecha_referencia'] ?? null)) {
                $diagnosticos[] = ['codigo' => 'REGISTRO_INVALIDO', 'id_seccion' => $id];

                continue;
            }
            if (isset($porSeccion[$id])) {
                $diagnosticos[] = ['codigo' => 'POBLACION_DUPLICADA', 'id_seccion' => $id];
                $porSeccion[$id] = false;

                continue;
            }
            $porSeccion[$id] = $registro;
        }
        $filas = [];
        foreach ($secciones as $seccion) {
            $id = (string) $seccion['id_seccion'];
            $registro = $porSeccion[$id] ?? null;
            $inconsistente = $registro === false || (int) ($seccion['id_periodo'] ?? 0) !== $periodo;
            if ($inconsistente) {
                $diagnosticos[] = ['codigo' => 'SECCION_INCONSISTENTE', 'id_seccion' => $id];
            }
            $registro = is_array($registro) && ! $inconsistente ? $registro : null;
            $capacidad = $seccion['capacidad'] ?? null;
            if ($registro && $registro['estado'] === 'CONOCIDA' && is_numeric($capacidad) && (int) $capacidad > 0 && $registro['cantidad'] > (int) $capacidad) {
                $diagnosticos[] = ['codigo' => 'CAPACIDAD_INSUFICIENTE', 'id_seccion' => $id];
                $inconsistente = true;
            }
            $filas[] = ['id_seccion' => $id, 'id_periodo' => $seccion['id_periodo'] ?? null, 'id_curso_plan' => $seccion['id_curso_plan'] ?? null, 'id_oferta' => count($vinculos[$id] ?? []) === 1 ? $vinculos[$id][0] : null, 'capacidad' => $capacidad, 'estado' => $inconsistente ? 'INCONSISTENTE' : ($registro['estado'] ?? 'DESCONOCIDA'), 'cantidad' => $registro['cantidad'] ?? null, 'fuente' => $registro['fuente'] ?? null, 'validacion' => $registro['validacion'] ?? null, 'fecha_referencia' => $registro['fecha_referencia'] ?? null, 'version_fuente' => $registro['version_fuente'] ?? null];
        }

        return ['periodo' => $periodo, 'secciones' => $filas, 'demanda_version' => $demanda['version'] ?? null, 'demanda_recibida_en' => $demanda['recibida_en'] ?? null, 'cohortes' => 'NO_IDENTIFICADAS', 'subgrupos' => 'NO_IDENTIFICADOS', 'diagnosticos' => $diagnosticos];
    }

    private function fechaValida(mixed $fecha): bool
    {
        return is_string($fecha) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) === 1 && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }
}
