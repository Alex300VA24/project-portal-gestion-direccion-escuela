<?php

namespace App\Modules\GestionHorarios\Demand;

use InvalidArgumentException;

final class InstantaneaDemanda
{
    /** @param list<array{id_curso_plan: int, total: int, manana: int, tarde: int}> $cursos */
    private function __construct(public readonly int $periodo, public readonly int $version, public readonly array $cursos) {}

    public static function desdeArray(array $datos): self
    {
        if (! is_int($datos['periodo'] ?? null) || $datos['periodo'] < 1 || ! is_int($datos['version'] ?? null) || $datos['version'] < 1 || ! is_array($datos['cursos'] ?? null) || ! array_is_list($datos['cursos'])) {
            throw new InvalidArgumentException('Periodo, versión o lista de cursos inválidos.');
        }

        $cursos = [];
        foreach ($datos['cursos'] as $curso) {
            if (! is_array($curso) || ! is_int($curso['id_curso_plan'] ?? null) || $curso['id_curso_plan'] < 1) {
                throw new InvalidArgumentException('Identificador de curso-plan inválido.');
            }
            foreach (['total', 'manana', 'tarde'] as $campo) {
                if (! is_int($curso[$campo] ?? null) || $curso[$campo] < 0) {
                    throw new InvalidArgumentException('Los totales deben ser enteros no negativos.');
                }
            }
            if ($curso['manana'] > $curso['total'] || $curso['tarde'] > $curso['total'] - $curso['manana'] || isset($cursos[$curso['id_curso_plan']])) {
                throw new InvalidArgumentException('Preferencias inconsistentes o curso duplicado.');
            }
            $cursos[$curso['id_curso_plan']] = ['id_curso_plan' => $curso['id_curso_plan'], 'total' => $curso['total'], 'manana' => $curso['manana'], 'tarde' => $curso['tarde']];
        }
        ksort($cursos, SORT_NUMERIC);

        return new self($datos['periodo'], $datos['version'], array_values($cursos));
    }

    public function hash(): string
    {
        return hash('sha256', json_encode([$this->periodo, $this->version, $this->cursos], JSON_THROW_ON_ERROR));
    }
}
