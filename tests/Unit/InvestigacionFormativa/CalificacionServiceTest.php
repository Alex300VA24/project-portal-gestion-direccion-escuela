<?php

namespace Tests\Unit\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Services\CalificacionService;
use PHPUnit\Framework\TestCase;

final class CalificacionServiceTest extends TestCase
{
    public function test_normaliza_el_fixture_independiente_a_doce_puntos(): void
    {
        $servicio = new CalificacionService;

        $this->assertSame(1200, $servicio->normalizar(600, 1000));
        $this->assertSame('12.00', $servicio->formatear(1200));
    }

    public function test_el_promedio_final_usa_la_media_de_las_tres_notas(): void
    {
        $servicio = new CalificacionService;

        $this->assertSame(1400, $servicio->promedioFinal([1200, 1600, 1400]));
        $this->assertSame('14.00', $servicio->formatear(1400));
    }

    public function test_no_promedia_una_evaluacion_ausente(): void
    {
        $servicio = new CalificacionService;

        $this->assertNull($servicio->promedioFinal([1200, 1600, null]));
        $this->assertNull($servicio->promedioFinal([1200, 1600]));
    }

    public function test_el_redondeo_de_las_notas_es_medio_arriba(): void
    {
        $servicio = new CalificacionService;

        $this->assertSame(1, $servicio->normalizar(1, 4000));
    }
}
