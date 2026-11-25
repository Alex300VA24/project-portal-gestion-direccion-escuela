<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Services\ResumenPreparacionAcademica;
use PHPUnit\Framework\TestCase;

final class ResumenPreparacionAcademicaTest extends TestCase
{
    public function test_detecta_faltantes_sin_revelar_identidades_ni_prometer_factibilidad(): void
    {
        $fuentes = [
            'ofertas' => [['id_oferta' => 1, 'estado' => 'OFERTADA']],
            'secciones' => [['id_seccion' => 2, 'estado' => 'ACTIVA', 'capacidad' => 0]],
            'oferta_seccion' => [],
            'actividades' => [['tipo_actividad' => 'LABORATORIO', 'minutos_sesion' => 0]],
            'diagnosticos' => [['codigo' => 'ACTIVIDAD_SIN_DOCENTE'], ['codigo' => 'SECCION_SIN_DOCENTE']],
        ];
        $resultado = (new ResumenPreparacionAcademica)->analizar($fuentes);
        $mensajes = implode(' ', array_column($resultado, 'mensaje'));
        self::assertCount(6, array_filter($resultado, fn ($d) => $d['nivel'] === 'ERROR'));
        self::assertStringContainsString('sin asociación', $mensajes);
        self::assertStringContainsString('sin secciones', $mensajes);
        self::assertStringContainsString('capacidad válida', $mensajes);
        self::assertStringContainsString('duración', $mensajes);
        self::assertStringContainsString('sin asignación docente confirmada', $mensajes);
        self::assertStringContainsString('no demuestra factibilidad', $mensajes);
    }

    public function test_distingue_asociacion_no_disponible_y_taller_de_datos_invalidos(): void
    {
        $fuentes = [
            'ofertas' => [], 'secciones' => [], 'oferta_seccion' => null,
            'actividades' => [['tipo_actividad' => 'TALLER', 'minutos_sesion' => 60]],
            'diagnosticos' => [],
        ];
        $resultado = (new ResumenPreparacionAcademica)->analizar($fuentes);
        self::assertCount(3, array_filter($resultado, fn ($d) => $d['nivel'] === 'ERROR'));
        self::assertStringContainsString('talleres', implode(' ', array_column($resultado, 'mensaje')));
    }

    public function test_no_considera_valida_una_asociacion_duplicada_o_con_oferta_cerrada(): void
    {
        $fuentes = [
            'ofertas' => [['id_oferta' => 1, 'estado' => 'CERRADA']],
            'secciones' => [['id_seccion' => 2, 'estado' => 'ACTIVA', 'capacidad' => 30]],
            'oferta_seccion' => [
                ['id_oferta' => 1, 'id_seccion' => 2, 'estado' => 'VIGENTE'],
                ['id_oferta' => 1, 'id_seccion' => 2, 'estado' => 'VIGENTE'],
            ],
            'actividades' => [], 'diagnosticos' => [],
        ];
        $resultado = (new ResumenPreparacionAcademica)->analizar($fuentes);
        $mensajes = implode(' ', array_column($resultado, 'mensaje'));
        self::assertCount(3, array_filter($resultado, fn ($d) => $d['nivel'] === 'ERROR'));
        self::assertStringContainsString('No hay ofertas en estado ofertado', $mensajes);
        self::assertStringContainsString('más de una asociación vigente', $mensajes);
        self::assertStringContainsString('ofertas cerradas', $mensajes);
    }
}
