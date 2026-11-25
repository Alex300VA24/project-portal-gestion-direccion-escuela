<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Services\ConsolidacionVacantesLocal as Consolidacion;
use PHPUnit\Framework\TestCase;

final class ConsolidacionVacantesLocalTest extends TestCase
{
    private function propuesta(): array
    {
        return ['periodo'=>2, 'plan'=>1, 'version'=>3, 'ambientes'=>[['codigo'=>'R01', 'capacidad'=>63]], 'cursos'=>[
            ['id_curso_plan'=>6, 'total'=>85, 'original'=>80, 'propuesta'=>[
                ['codigo'=>'A','capacidad'=>63,'poblacion_estimada'=>43,'grupos_lab'=>[15,14,14],'accion'=>'crear','id_seccion'=>null],
                ['codigo'=>'B','capacidad'=>63,'poblacion_estimada'=>42,'grupos_lab'=>[14,14,14],'accion'=>'crear','id_seccion'=>null],
            ]],
        ]];
    }

    public function test_compuertas_del_flujo(): void
    {
        self::assertSame('prematricula_abierta', Consolidacion::estado(null, null, null, false, true));
        self::assertSame('revision', Consolidacion::estado('cierre', null, null, true, true));
        self::assertSame('sin_consolidar', Consolidacion::estado('cierre', 'nueva', null, false, true));
        self::assertSame('sin_consolidar', Consolidacion::estado('cierre', 'nueva', 'anterior', false, true));
        self::assertSame('consolidada', Consolidacion::estado('cierre', 'igual', 'igual', false, true));
        self::assertSame('secciones_verificadas', Consolidacion::estado('cierre', 'igual', 'igual', false, false));
    }

    public function test_crear_secciones_no_cambia_vacantes_confirmadas(): void
    {
        $a = $this->propuesta();
        $b = $a;
        $b['cursos'][0]['propuesta'][0]['accion'] = 'conservar';
        $b['cursos'][0]['propuesta'][0]['id_seccion'] = 20;
        self::assertSame(Consolidacion::huella($a, 'cierre1', [3]), Consolidacion::huella($b, 'cierre1', [3]));
    }

    public function test_cambios_invalidan_consolidacion(): void
    {
        $a = $this->propuesta();
        $hash = Consolidacion::huella($a, 'cierre1', [3]);
        self::assertNotSame($hash, Consolidacion::huella($a, 'cierre2', [3]));
        self::assertNotSame($hash, Consolidacion::huella($a, 'cierre1', [4]));
        $b = $a; $b['version']++;
        self::assertNotSame($hash, Consolidacion::huella($b, 'cierre1', [3]));
        $b = $a; $b['ambientes'][0]['capacidad'] = 62;
        self::assertNotSame($hash, Consolidacion::huella($b, 'cierre1', [3]));
        $b = $a; $b['cursos'][0]['total'] = 86;
        self::assertNotSame($hash, Consolidacion::huella($b, 'cierre1', [3]));
        $b = $a; $b['cursos'][0]['propuesta'][0]['capacidad'] = 62;
        self::assertNotSame($hash, Consolidacion::huella($b, 'cierre1', [3]));
        $b = $a; $b['cursos'][0]['propuesta'][0]['grupos_lab'] = [20,20,3];
        self::assertNotSame($hash, Consolidacion::huella($b, 'cierre1', [3]));
    }
}
