<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class StoredDatabaseFunctionTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }

        $this->createPrematriculaAcademicData();
    }

    public function test_matricula_function_reads_credits_from_core(): void
    {
        DB::table('gestion_matriculas.cronograma_matricula')->insert([
            'id_cronograma' => 1,
            'id_periodo' => 202602,
            'tipo' => 'Regular',
            'fecha_inicio' => '2026-07-01 08:00:00-05',
            'fecha_fin' => '2026-07-15 18:00:00-05',
        ]);
        DB::table('gestion_matriculas.matricula')->insert([
            'id_matricula' => 1,
            'id_estudiante' => 1001,
            'id_periodo' => 202602,
            'id_cronograma' => 1,
            'estado' => 'Activa',
            'registrado_por' => 801,
        ]);
        DB::table('gestion_matriculas.matricula_detalle')->insert([
            'id_detalle' => 1,
            'id_matricula' => 1,
            'id_seccion' => 901,
            'estado' => 'Matriculado',
        ]);

        $result = DB::selectOne('SELECT gestion_matriculas.calcular_creditos_matriculados(?, ?) AS valor', [1001, 202602]);

        $this->assertEquals(3, $result->valor);
    }

    public function test_calidad_function_handles_ratio_and_zero_denominator(): void
    {
        $process = DB::table('calidad.proceso')->insertGetId([
            'codigo' => 'M01',
            'nombre' => 'Proceso de prueba',
            'nivel' => 1,
            'id_responsable' => 801,
        ], 'id_proceso');
        $indicator = DB::table('calidad.indicador')->insertGetId([
            'id_proceso' => $process,
            'codigo' => 'IND-01',
            'nombre' => 'Indicador de prueba',
            'objetivo' => 'Validar función.',
            'formula' => 'numerador / denominador',
            'unidad_medida' => 'ratio',
            'frecuencia' => 'Semestral',
            'sentido' => 'Ascendente',
            'id_responsable' => 801,
        ], 'id_indicador');
        $measurement = DB::table('calidad.medicion_indicador')->insertGetId([
            'id_indicador' => $indicator,
            'id_periodo' => 202602,
            'numerador' => 25,
            'denominador' => 100,
            'registrado_por' => 801,
        ], 'id_medicion');

        $ratio = DB::selectOne('SELECT calidad.calcular_valor_indicador(?) AS valor', [$measurement]);

        $this->assertEquals('0.2500', $ratio->valor);
        $this->assertNull(DB::selectOne('SELECT calidad.calcular_valor_indicador(?) AS valor', [999999])->valor);
    }

    public function test_practicas_function_calculates_weighted_grade(): void
    {
        $sector = DB::table('comun.sector_economico')->insertGetId([
            'codigo' => 'TI',
            'nombre' => 'Tecnología',
        ], 'id_sector');
        $company = DB::table('comun.empresa')->insertGetId([
            'ruc' => '20123456789',
            'razon_social' => 'Empresa de prueba SAC',
            'id_sector' => $sector,
        ], 'id_empresa');
        $practice = DB::table('gestion_practicas.practica')->insertGetId([
            'id_estudiante' => 1001,
            'id_empresa' => $company,
            'id_periodo' => 202602,
            'area' => 'Desarrollo',
            'funciones' => 'Pruebas de integración.',
            'tipo_practica' => 'preprofesional',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-12-01',
            'horas_totales' => 320,
        ], 'id_practica');
        $criterion = DB::table('gestion_practicas.criterio_evaluacion')->insertGetId([
            'nombre' => 'Desempeño técnico',
            'evaluador' => 'empresa',
            'peso' => 100,
        ], 'id_criterio');
        $evaluation = DB::table('gestion_practicas.evaluacion')->insertGetId([
            'id_practica' => $practice,
            'evaluador' => 'empresa',
            'evaluado_por' => 801,
        ], 'id_evaluacion');
        DB::table('gestion_practicas.evaluacion_criterio')->insert([
            'id_evaluacion' => $evaluation,
            'id_criterio' => $criterion,
            'nota' => 16,
        ]);

        $result = DB::selectOne('SELECT gestion_practicas.calcular_nota_final(?) AS valor', [$practice]);

        $this->assertEquals('16.00', $result->valor);
    }

    public function test_tesis_function_averages_jury_results(): void
    {
        $teacher = DB::table('core.docente')->insertGetId([
            'id_persona' => 1002,
            'codigo_docente' => 'DOC-001',
            'id_departamento' => 30,
            'estado' => 'Activo',
        ], 'id_docente');
        $line = DB::table('core.linea_investigacion')->insertGetId([
            'id_escuela' => 20,
            'codigo' => 'LI-01',
            'nombre' => 'Ingeniería de software',
        ], 'id_linea');
        $project = DB::table('tesis.proyecto_tesis')->insertGetId([
            'codigo' => 'TES-001',
            'titulo' => 'Proyecto de prueba',
            'resumen' => 'Resumen de prueba.',
            'id_linea' => $line,
            'id_periodo_registro' => 202602,
            'etapa' => 'Tesis',
            'estado' => 'Registrada',
        ], 'id_proyecto');
        $juror = DB::table('tesis.jurado_proyecto')->insertGetId([
            'id_proyecto' => $project,
            'id_docente' => $teacher,
            'cargo' => 'Presidente',
            'estado' => 'Activo',
        ], 'id_jurado');
        $defense = DB::table('tesis.sustentacion')->insertGetId([
            'id_proyecto' => $project,
            'fecha_hora' => '2026-12-10 10:00:00-05',
            'modalidad' => 'Virtual',
            'enlace_virtual' => 'https://example.test/sustentacion',
            'estado' => 'Programada',
        ], 'id_sustentacion');
        DB::table('tesis.resultado_jurado')->insert([
            'id_sustentacion' => $defense,
            'id_jurado' => $juror,
            'nota' => 17,
            'dictamen' => 'Aprobada',
        ]);

        $result = DB::selectOne('SELECT tesis.calcular_nota_sustentacion(?) AS valor', [$defense]);

        $this->assertEquals('17.00', $result->valor);
    }
}
