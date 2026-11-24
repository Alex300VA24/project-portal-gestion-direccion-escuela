<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ReporteSimilitud;
use App\Modules\TesisProyectoTesis\Models\Resolucion;
use App\Modules\TesisProyectoTesis\Models\Solicitud;

/**
 * Ubica el archivo de un registro del expediente a partir de su uuid público.
 */
final class ExpedienteService
{
    public const TIPOS = ['oficio', 'proyecto', 'entrega', 'resolucion', 'reporte', 'acta-firmada'];

    /** @return array{0: ?Proyecto, 1: ?int} */
    public function ubicarArchivo(string $tipo, string $uuid): array
    {
        if (! in_array($tipo, self::TIPOS, true) || ! Proyecto::esUuid($uuid)) {
            return [null, null];
        }

        return match ($tipo) {
            'proyecto' => (function () use ($uuid) {
                $p = Proyecto::query()->where('uuid', $uuid)->first();

                return [$p, $p?->id_archivo_documento];
            })(),
            'entrega' => (function () use ($uuid) {
                $e = Entrega::query()->where('uuid', $uuid)->first();

                return [$e?->proyecto, $e?->id_archivo];
            })(),
            'oficio' => (function () use ($uuid) {
                $s = Solicitud::query()->where('uuid', $uuid)->first();

                return [$s?->proyecto, $s?->id_archivo];
            })(),
            'resolucion' => (function () use ($uuid) {
                $r = Resolucion::query()->where('uuid', $uuid)->first();

                return [$r?->solicitud?->proyecto, $r?->id_archivo];
            })(),
            'reporte' => (function () use ($uuid) {
                $r = ReporteSimilitud::query()->where('uuid', $uuid)->first();

                return [$r?->entrega?->proyecto, $r?->id_archivo];
            })(),
            'acta-firmada' => (function () use ($uuid) {
                $a = Acta::query()->where('uuid', $uuid)->first();

                return [$a?->proyecto, $a?->id_archivo_firmado];
            })(),
        };
    }
}
