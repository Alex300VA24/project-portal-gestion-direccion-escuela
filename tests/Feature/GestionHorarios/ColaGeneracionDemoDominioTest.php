<?php
namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\ColaGeneracionDemo as Cola;
use PHPUnit\Framework\TestCase;

final class ColaGeneracionDemoDominioTest extends TestCase
{
    public function test_reconoce_fases_reales(): void
    {
        foreach (Cola::FASES as $fase) { self::assertSame($fase, Cola::fase("CP-SAT: $fase\r\n")); }
        self::assertNull(Cola::fase('CP-SAT: 100%'));
        self::assertNull(Cola::fase('texto inesperado'));
    }

    public function test_prevalidacion_no_implica_solucion(): void
    {
        self::assertSame([['estado'=>'REQUIERE_ATENCION','advertencias'=>12],null], Cola::interpretar("{\"estado\":\"REQUIERE_ATENCION\",\"advertencias\":12}\r\nSin cambios en PostgreSQL.\r\n", 'prevalidar'));
        self::assertSame([['estado'=>'NO_SE_PUEDE_GENERAR'],null], Cola::interpretar('{"estado":"NO_SE_PUEDE_GENERAR"}', 'generar'));
    }

    public function test_resultado_factible_exige_identificador_guardado(): void
    {
        $id = '71bb4725-e5bc-4403-a57c-fafbe286ccc0';
        self::assertSame([['estado'=>'FEASIBLE'],$id], Cola::interpretar("{\"estado\":\"FEASIBLE\"}\nPropuesta QA guardada (solo consulta): $id\n", 'generar'));
        $this->expectException(\RuntimeException::class);
        Cola::interpretar('{"estado":"FEASIBLE"}', 'generar');
    }

    public function test_inviabilidad_y_timeout_son_distintos(): void
    {
        foreach (['INFEASIBLE','UNKNOWN'] as $estado) { self::assertSame([['estado'=>$estado],null], Cola::interpretar(json_encode(['estado'=>$estado]), 'generar')); }
    }

    public function test_rechaza_propuesta_inviable(): void
    {
        $this->expectException(\RuntimeException::class);
        Cola::interpretar("{\"estado\":\"INFEASIBLE\"}\nPropuesta QA guardada (solo consulta): 71bb4725-e5bc-4403-a57c-fafbe286ccc0\n", 'generar');
    }
}
