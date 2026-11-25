<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\HorariosVersionadosDemo as H;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class HorariosVersionadosDemoDominioTest extends TestCase
{
    private function datos(): array
    {
        return ['bloques' => [[420,480],[480,540],[840,900],[900,960]], 'actividades' => [
            ['id'=>1,'seccion_id'=>1,'duracion'=>120,'docentes'=>[['id_docente'=>10]]],
            ['id'=>2,'seccion_id'=>2,'duracion'=>60,'docentes'=>[['id_docente'=>10]]],
            ['id'=>3,'seccion_id'=>1,'duracion'=>60,'docentes'=>[['id_docente'=>11]]],
            ['id'=>4,'seccion_id'=>2,'duracion'=>60,'docentes'=>[['id_docente'=>12]]],
        ], 'sesiones'=>[]];
    }
    public function test_programar_y_retirar_sin_mutar_origen(): void
    {
        $a=$this->datos(); $b=H::colocar($a,1,'LUNES','07:00');
        self::assertSame([], $a['sesiones']); self::assertSame(540,$b['sesiones'][1]['fin']);
        self::assertSame([],H::colocar($b,1,null,null)['sesiones']);
        self::assertCount(1,H::comparar($a['sesiones'],$b['sesiones']));
    }
    public function test_no_permite_saltar_receso(): void
    {
        $this->expectException(InvalidArgumentException::class); H::colocar($this->datos(),1,'LUNES','08:00');
    }
    public function test_no_permite_cruce_docente(): void
    {
        $a=H::colocar($this->datos(),1,'LUNES','07:00');
        $this->expectException(InvalidArgumentException::class); H::colocar($a,2,'LUNES','08:00');
    }
    public function test_no_permite_cruce_seccion(): void
    {
        $a=H::colocar($this->datos(),1,'LUNES','07:00');
        $this->expectException(InvalidArgumentException::class); H::colocar($a,3,'LUNES','08:00');
    }
    public function test_permite_actividades_independientes(): void
    {
        $a=H::colocar($this->datos(),1,'LUNES','07:00'); $b=H::colocar($a,4,'LUNES','07:00');
        self::assertCount(2,$b['sesiones']);
        self::assertSame([],H::comparar($b['sesiones'],$b['sesiones']));
    }
    public function test_comparacion_no_depende_del_orden_jsonb(): void
    {
        self::assertSame([],H::comparar([1=>['dia'=>'LUNES','inicio'=>420,'fin'=>480]],[1=>['fin'=>480,'inicio'=>420,'dia'=>'LUNES']]));
    }
}
