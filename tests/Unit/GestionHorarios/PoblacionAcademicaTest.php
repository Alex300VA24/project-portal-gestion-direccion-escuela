<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Services\PoblacionAcademica;
use PHPUnit\Framework\TestCase;

final class PoblacionAcademicaTest extends TestCase
{
    private function secciones(): array
    {
        return [['id_seccion' => 1, 'id_periodo' => 10, 'id_curso_plan' => 20, 'capacidad' => 30], ['id_seccion' => 2, 'id_periodo' => 10, 'id_curso_plan' => 20, 'capacidad' => 25]];
    }

    private function registro(int $id, int $cantidad): array
    {
        return ['id_seccion' => $id, 'id_periodo' => 10, 'id_curso_plan' => 20, 'cantidad' => $cantidad, 'estado' => 'CONOCIDA', 'fuente' => 'proveedor de prueba', 'validacion' => 'CONFIRMADA', 'fecha_referencia' => '2026-11-17', 'version_fuente' => 1];
    }

    public function test_demanda_agregada_no_asigna_estudiantes_a_secciones(): void
    {
        $resultado = (new PoblacionAcademica)->analizar(10, $this->secciones(), ['version' => 3, 'cursos' => [['id_curso_plan' => 20, 'total' => 50]]]);
        self::assertSame(['DESCONOCIDA', 'DESCONOCIDA'], array_column($resultado['secciones'], 'estado'));
        self::assertSame([null, null], array_column($resultado['secciones'], 'cantidad'));
        self::assertSame(3, $resultado['demanda_version']);
        self::assertSame('NO_IDENTIFICADAS', $resultado['cohortes']);
        self::assertSame('NO_IDENTIFICADOS', $resultado['subgrupos']);
    }

    public function test_contrato_ficticio_distingue_cero_conocido_estimado_y_capacidad(): void
    {
        $a = $this->registro(1, 0);
        $b = $this->registro(2, 28);
        $resultado = (new PoblacionAcademica)->analizar(10, $this->secciones(), null, [$a, $b]);
        self::assertSame(0, $resultado['secciones'][0]['cantidad']);
        self::assertSame('CONOCIDA', $resultado['secciones'][0]['estado']);
        self::assertSame('INCONSISTENTE', $resultado['secciones'][1]['estado']);
        self::assertContains('CAPACIDAD_INSUFICIENTE', array_column($resultado['diagnosticos'], 'codigo'));
        $b['estado'] = 'ESTIMADA';
        $resultado = (new PoblacionAcademica)->analizar(10, $this->secciones(), null, [$b]);
        self::assertSame('ESTIMADA', $resultado['secciones'][1]['estado']);
        self::assertNotContains('CAPACIDAD_INSUFICIENTE', array_column($resultado['diagnosticos'], 'codigo'));
    }

    public function test_rechaza_referencias_y_duplicados_sin_asumir_cero(): void
    {
        $registro = $this->registro(1, 10);
        $invalido = $this->registro(2, 5);
        $invalido['id_periodo'] = 11;
        $r = (new PoblacionAcademica)->analizar(10, $this->secciones(), null, [$registro, $registro, $invalido]);
        self::assertContains('POBLACION_DUPLICADA', array_column($r['diagnosticos'], 'codigo'));
        self::assertContains('REGISTRO_INVALIDO', array_column($r['diagnosticos'], 'codigo'));
        self::assertSame('INCONSISTENTE', $r['secciones'][0]['estado']);
        self::assertNull($r['secciones'][1]['cantidad']);
        $invalido['id_periodo'] = 10;
        $invalido['fecha_referencia'] = '2026-02-30';
        $r = (new PoblacionAcademica)->analizar(10, $this->secciones(), null, [$invalido]);
        self::assertContains('REGISTRO_INVALIDO', array_column($r['diagnosticos'], 'codigo'));
    }

    public function test_variass_secciones_usan_solo_asociaciones_validas(): void
    {
        $ofertas = [['id_oferta' => 3, 'id_periodo' => 10, 'id_curso_plan' => 20, 'tipo_oferta' => 'REGULAR']];
        $asociaciones = [['id_oferta' => 3, 'id_seccion' => 1, 'estado' => 'VIGENTE'], ['id_oferta' => 3, 'id_seccion' => 2, 'estado' => 'VIGENTE']];
        $r = (new PoblacionAcademica)->analizar(10, $this->secciones(), null, null, $ofertas, $asociaciones);
        self::assertSame(['3', '3'], array_column($r['secciones'], 'id_oferta'));
        $ofertas[0]['id_periodo'] = 11;
        $r = (new PoblacionAcademica)->analizar(10, $this->secciones(), null, null, $ofertas, $asociaciones);
        self::assertSame([null, null], array_column($r['secciones'], 'id_oferta'));
    }
}
