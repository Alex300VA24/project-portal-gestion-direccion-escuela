<?php
namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\HorarioUnicoDemo;
use PHPUnit\Framework\TestCase;

final class EdicionHorarioUnicoDominioTest extends TestCase
{
    private function datos(): array
    {
        return ['plan'=>1,'periodo'=>1,'secciones'=>[['id_seccion'=>2],['id_seccion'=>1]],
            'actividades'=>[['id'=>2,'docentes'=>[['id_docente'=>2],['id_docente'=>1]]],['id'=>1,'docentes'=>[]]],
            'bloques'=>[[480,540],[420,480]],'sesiones'=>[1=>['dia'=>'LUNES','inicio'=>420,'fin'=>480,'ambiente'=>'A'],2=>['dia'=>'MARTES','inicio'=>480,'fin'=>540,'ambiente'=>'B']],
            'cpsat_qa'=>['estado'=>'FEASIBLE','huella_recursos'=>'x','huella_fuente'=>'y']];
    }
    public function test_mismo_horario_no_genera_version_por_estado_u_orden(): void
    {
        $a=$this->datos(); $b=$a; $b['cpsat_qa']['estado']='OPTIMAL';
        $b['actividades'][0]['docentes']=array_reverse($b['actividades'][0]['docentes']);
        foreach (['actividades','secciones','bloques'] as $k) { $b[$k]=array_reverse($b[$k]); }
        $b['sesiones']=array_reverse($b['sesiones'],true);
        self::assertTrue(HorarioUnicoDemo::mismoContenido($a,$b));
    }
    public function test_cambia_dia_hora_ambiente_o_asignacion(): void
    {
        $a=$this->datos();
        foreach (['dia'=>'MIERCOLES','inicio'=>540,'ambiente'=>'C'] as $k=>$v) {
            $b=$a; $b['sesiones'][1][$k]=$v; self::assertFalse(HorarioUnicoDemo::mismoContenido($a,$b));
        }
        $b=$a; $b['actividades'][0]['docentes'][0]['id_docente']=7;
        self::assertFalse(HorarioUnicoDemo::mismoContenido($a,$b));
    }
    public function test_cambio_de_recursos_o_fuente_no_se_oculta(): void
    {
        $a=$this->datos(); $b=$a; $b['cpsat_qa']['huella_recursos']='z';
        self::assertFalse(HorarioUnicoDemo::mismoContenido($a,$b));
        $b=$a; $b['cpsat_qa']['huella_fuente']='z';
        self::assertFalse(HorarioUnicoDemo::mismoContenido($a,$b));
    }
    public function test_revision_antigua_sin_huellas_igual_si_contenido_igual(): void
    {
        $a=$this->datos(); $b=$a; unset($b['cpsat_qa']['huella_recursos'],$b['cpsat_qa']['huella_fuente']);
        self::assertTrue(HorarioUnicoDemo::mismoContenido($a,$b));
    }
}
