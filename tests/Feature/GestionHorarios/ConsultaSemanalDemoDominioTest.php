<?php
namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\ConsultaSemanalDemo;
use PHPUnit\Framework\TestCase;

final class ConsultaSemanalDemoDominioTest extends TestCase
{
    public function test_orden_natural_por_nombre_y_busqueda_con_tildes(): void
    {
        $opciones = ['x'=>'INFO 10','y'=>'INFO 2','z'=>'LAB A','a'=>'Áula 1'];
        self::assertSame(['a','y','x','z'], array_keys(ConsultaSemanalDemo::opciones($opciones)));
        self::assertSame(['a'], array_keys(ConsultaSemanalDemo::opciones($opciones,'aula')));
        self::assertSame(['y','x'], array_keys(ConsultaSemanalDemo::opciones($opciones,'INFO')));
        self::assertSame(['a','z'], array_keys(ConsultaSemanalDemo::opciones($opciones,'aula','z')));
    }
    public function test_consultas_independientes_sin_mezclar_filtros(): void
    {
        $a = [['id'=>1,'seccion_id'=>1,'docentes'=>[['codigo_docente'=>'D1']]],['id'=>2,'seccion_id'=>2,'docentes'=>[['codigo_docente'=>'D1']]],['id'=>3,'seccion_id'=>2,'docentes'=>[['codigo_docente'=>'D2']]]];
        $s = [1=>['ciclo'=>2,'seccion'=>'A','curso'=>'13034'],2=>['ciclo'=>6,'seccion'=>'B','curso'=>'14069']];
        $h = [1=>['ambiente'=>'LAB A'],3=>['ambiente'=>'LAB A']];
        $f = fn ($vista,$ciclo,$sec,$doc,$amb,$curso) => array_column(ConsultaSemanalDemo::filtrar($a,$s,$h,$vista,$ciclo,$sec,$doc,$amb,$curso),'id');
        self::assertSame([1],$f('ciclo','2','A','D2','otro',''));
        self::assertSame([1,2],$f('docente','6','B','D1','otro','13034'));
        self::assertSame([1,3],$f('ambiente','6','B','D2','LAB A','13034'));
        self::assertSame([],$f('docente','','','','',''));
        self::assertSame([],$f('ambiente','','','','',''));
        self::assertSame([],$f('incorrecta','','','','',''));
    }
}
