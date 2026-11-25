<?php

namespace App\Modules\GestionHorarios\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Ámbito único: base QA con un solo plan y periodo. No usar como contrato institucional. */
final class HorarioUnicoDemo
{
    public const TABLA = 'gestion_horarios.qa_horario_trabajo';
    public const REVISIONES = 'gestion_horarios.qa_horario_revision';

    public static function id(): ?string
    {
        if (! DB::connection()->getSchemaBuilder()->hasTable(self::TABLA)) { return null; }
        $id = DB::table(self::TABLA)->where('singleton', 1)->value('propuesta');
        return $id === null ? null : (string) $id;
    }

    /** Solo dentro de una transacción que ya bloqueó las revisiones. */
    public static function siguienteVersion(int $esperada, int $vigente): int
    {
        if ($esperada < 1 || $esperada !== $vigente) {
            throw new \RuntimeException('El horario cambió durante la operación. Abra la última versión y vuelva a intentarlo.');
        }
        if ($vigente === PHP_INT_MAX) { throw new \RuntimeException('Límite de versiones alcanzado.'); }
        return $vigente + 1;
    }

    /** Compara contenido académico, no el estado del solver ni el orden de serialización. */
    public static function mismoContenido(array $a, array $b): bool
    {
        $contenido = static function (array $datos): array {
            unset($datos['cpsat_qa']);
            foreach (['actividades'=>'id', 'secciones'=>'id_seccion'] as $campo => $clave) {
                if (isset($datos[$campo])) {
                    usort($datos[$campo], fn ($x, $y) => $x[$clave] <=> $y[$clave]);
                    if ($campo === 'actividades') {
                        foreach ($datos[$campo] as &$actividad) {
                            usort($actividad['docentes'], fn ($x, $y) => $x['id_docente'] <=> $y['id_docente']);
                        }
                        unset($actividad);
                    }
                }
            }
            if (isset($datos['bloques'])) { sort($datos['bloques']); }
            return $datos;
        };
        // Una huella nueva de recursos sí es un cambio académico (si ambas existen).
        // Las revisiones antiguas pueden no tener huella: comparar su contenido disponible.
        foreach (['huella_recursos', 'huella_fuente'] as $clave) {
            if (isset($a['cpsat_qa'][$clave], $b['cpsat_qa'][$clave]) && $a['cpsat_qa'][$clave] !== $b['cpsat_qa'][$clave]) { return false; }
        }
        return $contenido($a) == $contenido($b);
    }

    public static function nombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if (preg_match('/\A[^\r\n]{1,100}\z/u', $nombre) !== 1) {
            throw ValidationException::withMessages(['nombre'=>'Ingrese un nombre de hasta 100 caracteres.']);
        }
        return $nombre;
    }
}
