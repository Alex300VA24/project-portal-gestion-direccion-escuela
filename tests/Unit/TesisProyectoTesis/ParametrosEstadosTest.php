<?php

namespace Tests\Unit\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use PHPUnit\Framework\TestCase;

final class ParametrosEstadosTest extends TestCase
{
    public function test_parametros_del_proceso(): void
    {
        $this->assertSame(21, Parametros::SIMILITUD_MAXIMA);
        $this->assertSame(2, Parametros::INTEGRANTES_MAXIMOS);
        $this->assertSame(8, Parametros::SEMANA_REVISION_INTERMEDIA);
        $this->assertSame(2, Parametros::APROBATORIOS_MINIMOS);
        $this->assertSame(['Presidente', 'Secretario', 'Vocal'], Parametros::CARGOS_JURADO);
    }

    public function test_cada_estado_tiene_etiqueta_y_variante(): void
    {
        foreach (array_keys(Estados::ETIQUETAS) as $estado) {
            $this->assertNotSame('', Estados::etiqueta($estado));
            $this->assertContains(Estados::variante($estado), ['success', 'warning', 'danger', 'neutral', 'info']);
        }

        $this->assertSame('neutral', Estados::variante('Desconocido'));
        $this->assertSame('Tesis aprobada', Estados::etiqueta(Estados::TESIS_APROBADA));
    }
}
