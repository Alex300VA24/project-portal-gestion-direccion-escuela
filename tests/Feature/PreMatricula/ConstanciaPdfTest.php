<?php

namespace Tests\Feature\PreMatricula;

use App\Modules\PreMatricula\Services\ConstanciaPdf;
use Tests\TestCase;

/** La constancia usa el formato de la rama master y siempre ocupa una sola página A4. */
final class ConstanciaPdfTest extends TestCase
{
    public function test_la_constancia_tiene_el_formato_de_master(): void
    {
        $html = app(ConstanciaPdf::class)->html($this->snapshot(6));

        foreach ([
            'Universidad Nacional de Trujillo', 'OFICINA DE SISTEMAS', 'CONSTANCIA DE PREMATRÍCULA',
            'PREMATRÍCULA - 2026-II', 'Siendo válido y parte de los requisitos para la matrícula',
            'APELLIDOS:', 'PÉREZ GÓMEZ', 'NOMBRES:', 'ANA MARÍA', 'CIENCIAS FISICAS Y MATEMATICAS', 'INFORMATICA', 'TRUJILLO',
            'FECHA Y HORA DE EMISIÓN:', '2026-11-24 15:15:00', 'CURSO / ASIGNATURA', 'Créditos totales:',
            'ESCUELA INFORMATICA', 'PÉREZ GÓMEZ ANA MARÍA', 'TRUJILLO, Martes 24 de Noviembre del 2026',
            'Esta constancia no constituye matrícula definitiva.', 'AV. JUAN PABLO II S/N URB. SAN ANDRES',
            'data:image/png;base64,',
        ] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
    }

    public function test_la_constancia_ocupa_una_sola_pagina_con_muchos_cursos(): void
    {
        foreach ([1, 6, 15, 30, 50] as $cursos) {
            $salida = app(ConstanciaPdf::class)->generar($this->snapshot($cursos));
            $this->assertStringStartsWith('%PDF', $salida);
            $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $salida), "{$cursos} cursos");
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(int $cursos): array
    {
        return [
            'codigo_constancia' => 'PRE-PRUEBA',
            'enviada_en' => '2026-11-24T20:15:00+00:00',
            'zona_horaria' => 'America/Lima',
            'periodo' => ['codigo' => '2026-II'],
            'estudiante' => ['codigo_universitario' => '1000000001', 'apellidos' => 'Pérez Gómez', 'nombres' => 'Ana María'],
            'creditos_totales' => 3 * $cursos,
            'detalles' => array_map(fn (int $i): array => ['codigo' => 'INF'.$i, 'nombre' => 'Curso '.$i, 'creditos' => 3], range(1, $cursos)),
        ];
    }
}
