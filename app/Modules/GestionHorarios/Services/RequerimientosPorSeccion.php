<?php

namespace App\Modules\GestionHorarios\Services;

use InvalidArgumentException;

/** Obligaciones sin fecha ni hora. No constituyen matrícula ni sesiones programadas. */
final class RequerimientosPorSeccion
{
    public static function construir(array $curso, int $idSeccion, int $poblacion, array $gruposLab): array
    {
        if ($idSeccion < 1 || $poblacion < 1) {
            throw new InvalidArgumentException('Sección o población inválida.');
        }
        $horas = [];
        foreach (['TEORIA' => 'horas_teoria', 'PRACTICA' => 'horas_practica', 'LABORATORIO' => 'horas_laboratorio'] as $tipo => $campo) {
            $valor = $curso[$campo] ?? null;
            if (!is_numeric($valor) || !is_finite((float) $valor) || (float) $valor < 0 || (float) $valor > 168) {
                throw new InvalidArgumentException('Horas curriculares inválidas: '.$campo);
            }
            $minutos = (float) $valor * 60;
            if (abs($minutos - round($minutos)) > 0.000001) {
                throw new InvalidArgumentException('Duración no representable en minutos enteros.');
            }
            $horas[$tipo] = (int) round($minutos);
        }
        if (!array_is_list($gruposLab) || count(array_filter($gruposLab, fn ($n) => is_int($n) && $n > 0)) !== count($gruposLab)
            || ($horas['LABORATORIO'] > 0 ? array_sum($gruposLab) !== $poblacion : $gruposLab !== [])) {
            throw new InvalidArgumentException('Los subgrupos no cubren exactamente la población prevista.');
        }
        $resultado = [];
        foreach ($horas as $tipo => $minutos) {
            if ($minutos === 0) { continue; }
            $grupos = $tipo === 'LABORATORIO' ? $gruposLab : [$poblacion];
            foreach ($grupos as $i => $n) {
                $grupo = $tipo === 'LABORATORIO' ? $i + 1 : null;
                $resultado[] = [
                    'clave' => $idSeccion.':'.$tipo.':'.($grupo ?? 0),
                    'id_seccion' => $idSeccion,
                    'tipo_actividad' => $tipo,
                    'subgrupo' => $grupo,
                    'poblacion_estimada' => $n,
                    'minutos_semanales' => $minutos,
                    'tipo_ambiente' => $tipo === 'LABORATORIO' ? 'LABORATORIO' : 'AULA',
                ];
            }
        }
        if (!$resultado) { throw new InvalidArgumentException('Curso sin carga curricular.'); }
        return $resultado;
    }
}
