<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Demand\SincronizarDemandaLocal;
use App\Modules\PreMatricula\Services\FuenteDemandaAgregada;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SincronizacionDemandaLocalTest extends TestCase
{
    public function test_captura_completa_con_ceros_y_turnos(): void
    {
        $cursos = FuenteDemandaAgregada::completar([5, 6], [(object) ['id_curso_plan' => 5, 'estudiantes' => 3, 'manana' => 2, 'tarde' => 1]]);
        self::assertSame([['id_curso_plan' => 5, 'total' => 3, 'manana' => 2, 'tarde' => 1], ['id_curso_plan' => 6, 'total' => 0, 'manana' => 0, 'tarde' => 0]], $cursos);
    }

    public function test_version_no_cambia_si_contenido_identico(): void
    {
        $cursos = FuenteDemandaAgregada::completar([5], []);
        $primera = SincronizarDemandaLocal::decidir(2, $cursos, null);
        self::assertSame(1, $primera['captura']->version);
        $ultima = ['version' => 1, 'hash' => $primera['captura']->hash()];
        $segunda = SincronizarDemandaLocal::decidir(2, $cursos, $ultima);
        self::assertTrue($segunda['igual']);
        self::assertSame(1, $segunda['captura']->version);
    }

    public function test_cambios_incluso_retiros_crean_version_nueva(): void
    {
        $cursos = FuenteDemandaAgregada::completar([5], [(object) ['id_curso_plan' => 5, 'estudiantes' => 2, 'manana' => 1, 'tarde' => 1]]);
        $previa = SincronizarDemandaLocal::decidir(2, $cursos, null)['captura'];
        $nueva = SincronizarDemandaLocal::decidir(2, FuenteDemandaAgregada::completar([5], []), ['version' => 1, 'hash' => $previa->hash()]);
        self::assertFalse($nueva['igual']);
        self::assertSame(2, $nueva['captura']->version);
        self::assertSame(0, $nueva['captura']->cursos[0]['total']);
    }

    public function test_rechaza_curso_desconocido(): void
    {
        $this->expectException(RuntimeException::class);
        FuenteDemandaAgregada::completar([5], [(object) ['id_curso_plan' => 9, 'estudiantes' => 2, 'manana' => 1, 'tarde' => 1]]);
    }
}
