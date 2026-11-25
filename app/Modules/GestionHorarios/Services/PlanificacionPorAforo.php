<?php

namespace App\Modules\GestionHorarios\Services;

use InvalidArgumentException;

/** Propuesta agregada: no equivale a matrícula ni acredita factibilidad del horario. */
final class PlanificacionPorAforo
{
    /** Los ambientes recibidos deben pertenecer al conjunto previamente autorizado. */
    public static function calcular(int $demanda, bool $requiereAula, bool $requiereLab, array $ambientes, int $existentes = 0, ?int $limiteLab = null): array
    {
        if ($demanda < 0 || $existentes < 0 || ($limiteLab !== null && $limiteLab < 1)) {
            throw new InvalidArgumentException('Demanda, secciones o límite inválido.');
        }
        $aulas = $labs = [];
        foreach ($ambientes as $a) {
            if (! in_array(strtoupper(trim((string) $a['estado'])), ['ACTIVO', 'DISPONIBLE'], true) || (int) $a['capacidad'] <= 0) {
                continue;
            }
            $tipo = strtoupper(trim((string) $a['tipo']));
            if ($tipo === 'AULA') { $aulas[] = (int) $a['capacidad']; }
            if (in_array($tipo, ['LAB', 'LABORATORIO'], true)) { $labs[] = (int) $a['capacidad']; }
        }
        $aforoAula = $aulas ? max($aulas) : null;
        $aforoLab = $labs ? max($labs) : null;
        if ($aforoLab !== null && $limiteLab !== null) { $aforoLab = min($aforoLab, $limiteLab); }
        if ($demanda === 0) {
            if ($existentes !== 0) { throw new InvalidArgumentException('Demanda cero con secciones existentes: requiere revisión.'); }
            return ['aforo_seccion' => null, 'aforo_lab' => $aforoLab, 'minimo' => 0, 'secciones' => []];
        }
        if ((! $requiereAula && ! $requiereLab) || ($requiereAula && $aforoAula === null) || ($requiereLab && $aforoLab === null)) {
            throw new InvalidArgumentException('Faltan ambientes compatibles o el curso requiere planificación específica.');
        }
        // En cursos exclusivamente de laboratorio se usa una sección por grupo de aforo efectivo.
        // Es una política preliminar explícita, no una asignación de ambiente.
        $aforo = $requiereAula ? $aforoAula : $aforoLab;
        $minimo = intdiv($demanda, $aforo) + (int) ($demanda % $aforo !== 0);
        $cantidad = max($minimo, $existentes);
        if ($cantidad > min(26, $demanda)) {
            throw new InvalidArgumentException('Secciones sobrantes o más de 26 códigos: requiere revisión manual.');
        }
        $secciones = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $poblacion = intdiv($demanda, $cantidad) + (int) ($i < $demanda % $cantidad);
            $grupos = [];
            if ($requiereLab) {
                for ($resto = $poblacion; $resto > 0; $resto -= $aforoLab) { $grupos[] = min($resto, $aforoLab); }
            }
            $secciones[] = ['poblacion_estimada' => $poblacion, 'capacidad' => $aforo, 'grupos_lab' => $grupos];
        }
        return ['aforo_seccion' => $aforo, 'aforo_lab' => $aforoLab, 'minimo' => $minimo, 'secciones' => $secciones];
    }
}
