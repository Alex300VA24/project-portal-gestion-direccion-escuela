<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\DistribucionHoraria;
use App\Modules\GestionHorarios\Services\ExportacionHorarioTrabajoDemo;
use PHPUnit\Framework\TestCase;

final class ExportacionHorarioTrabajoDemoDominioTest extends TestCase
{
    private function datos(): array
    {
        return ['plan'=>1, 'periodo'=>1, 'secciones'=>[
            ['id_seccion'=>1,'ciclo'=>4,'seccion'=>'A','curso'=>'13639','nombre'=>'Electrónica'],
            ['id_seccion'=>2,'ciclo'=>10,'seccion'=>'G1','curso'=>'INF43','nombre'=>'Tesis'],
        ], 'actividades'=>[
            ['id'=>1,'seccion_id'=>1,'tipo'=>'LABORATORIO','duracion'=>120,'grupo'=>'LAB 2','docentes'=>[['codigo_docente'=>'QA-a']]],
            ['id'=>2,'seccion_id'=>2,'tipo'=>'PRACTICA','duracion'=>120,'docentes'=>[['codigo_docente'=>'QA-b']]],
            ['id'=>3,'seccion_id'=>1,'tipo'=>'TEORIA','duracion'=>120,'docentes'=>[]],
        ], 'sesiones'=>[1=>['dia'=>'MIERCOLES','inicio'=>840,'fin'=>960,'ambiente'=>'R01'], 2=>['dia'=>'VIERNES','inicio'=>540,'fin'=>660,'ambiente'=>'R02']], 'bloques'=>[[420,480],[1140,1200]]];
    }

    public function test_exporta_solo_sesiones_de_la_revision_con_sus_docentes_y_ambientes(): void
    {
        $r=ExportacionHorarioTrabajoDemo::adaptar($this->datos(), ['QA-a'=>'Nombre completo de prueba','QA-b'=>'Otro docente'], ['R01'=>'LAB A','R02'=>'INFO 2']);
        self::assertCount(2,$r['sesiones']);
        self::assertSame('14:00',$r['sesiones'][0]->hora_inicio);
        self::assertSame('16:00',$r['sesiones'][0]->hora_fin);
        self::assertSame('LAB A',$r['sesiones'][0]->ambiente);
        self::assertSame(['Nombre completo de prueba'],$r['docentes'][1]);
        self::assertArrayNotHasKey(3,$r['docentes']);
    }

    public function test_conserva_subgrupos_y_agrupa_tesis_en_seccion_a(): void
    {
        $r=ExportacionHorarioTrabajoDemo::adaptar($this->datos(), [], []);
        $grupos=(new DistribucionHoraria)->preparar($r['sesiones'],$r['docentes'],$r['bloques']);
        self::assertSame('LAB 2',$grupos[0]['sesiones'][0]['grupo']);
        self::assertSame('A',$grupos[1]['seccion']);
        self::assertSame('G1',$grupos[1]['sesiones'][0]['grupo']);
        self::assertSame(420,$grupos[0]['desde']);
        self::assertSame(1200,$grupos[0]['hasta']);
        self::assertSame('Docente sin nombre conciliado',$grupos[0]['sesiones'][0]['docente']);
    }

    public function test_rechaza_sesion_sin_actividad(): void
    {
        $d=$this->datos();$d['sesiones'][99]=$d['sesiones'][1];
        $this->expectException(\InvalidArgumentException::class);
        ExportacionHorarioTrabajoDemo::adaptar($d, [], []);
    }

    public function test_rechaza_duracion_incoherente(): void
    {
        $d=$this->datos();$d['sesiones'][1]['fin']=900;
        $this->expectException(\InvalidArgumentException::class);
        ExportacionHorarioTrabajoDemo::adaptar($d, [], []);
    }
}
