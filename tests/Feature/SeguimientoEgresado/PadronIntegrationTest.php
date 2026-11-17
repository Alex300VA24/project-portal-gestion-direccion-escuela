<?php

namespace Tests\Feature\SeguimientoEgresado;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\ImportacionFila;
use App\Modules\SeguimientoEgresado\Models\ImportacionPadron;
use App\Modules\SeguimientoEgresado\Services\PadronImportService;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class PadronIntegrationTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
    }

    public function test_padron_import_creates_records_and_detects_duplicates(): void
    {
        $service = new PadronImportService;

        // En modo atómico por defecto, un archivo limpio se importa completamente
        $csv = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo,telefono,estado\n"
             ."1021400998,DNI,79991111,Egresado Unitario,Perez,Gomez,2018,unitario@gmail.com,987654321,EGRESADO\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempPath, $csv);

        $res = $service->importarArchivo($tempPath);
        @unlink($tempPath);

        $this->assertSame(1, $res['total_procesadas']);
        $this->assertSame(1, $res['aceptadas']);
        $this->assertSame(0, $res['rechazadas']);

        $importacion = ImportacionPadron::find($res['importacion_id']);
        $this->assertNotNull($importacion);
        $this->assertSame(1, $importacion->filas()->count());

        $egresado = Egresado::where('codigo_matricula', '1021400998')->first();
        $this->assertNotNull($egresado);
        $this->assertNotNull($egresado->contactoVigente);
        $this->assertNotNull($egresado->estadoVigente);

        // Al intentar importar nuevamente con matrícula duplicada, se rechaza de forma atómica
        $csvDuplicado = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo,telefono,estado\n"
                      ."1021400998,DNI,79991111,Egresado Duplicado,Perez,Gomez,2018,unitario@gmail.com,987654321,EGRESADO\n";
        $tempDup = tempnam(sys_get_temp_dir(), 'csv_dup_');
        file_put_contents($tempDup, $csvDuplicado);

        $resDup = $service->importarArchivo($tempDup);
        @unlink($tempDup);

        $this->assertSame(1, $resDup['total_procesadas']);
        $this->assertSame(0, $resDup['aceptadas']);
        $this->assertSame(1, $resDup['rechazadas']);

        $filaRechazada = ImportacionFila::where('id_importacion', $resDup['importacion_id'])
            ->where('estado', 'RECHAZADA')
            ->first();
        $this->assertNotNull($filaRechazada);
        $this->assertStringContainsString('ya se encuentra registrada en el padrón', $filaRechazada->detalle_error);
    }

    public function test_cohorte_i1_calculation_denominador_oficial(): void
    {
        $cohorte = Cohorte::where('anio_ingreso', 2018)->first();
        $this->assertNotNull($cohorte);
        $this->assertSame(52, $cohorte->total_ingresantes);

        // El indicador debe calcularse sobre total_ingresantes
        $this->assertGreaterThan(0, $cohorte->porcentaje_i1);
    }

    public function test_ficha_egresado_api_returns_json(): void
    {
        $egresado = Egresado::first();
        $this->assertNotNull($egresado);

        $response = $this->get(route('seguimiento-egresado.padron.ficha', $egresado->id));
        $response->assertOk();
        $response->assertJsonStructure([
            'egresado',
            'nombre_completo',
            'contacto',
            'estado_actual',
            'historial_academico',
            'historial_seguimiento',
        ]);
    }

    public function test_descargar_plantilla_returns_csv_stream(): void
    {
        $response = $this->get(route('seguimiento-egresado.padron.plantilla'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_guardar_egresado_creates_or_updates_record(): void
    {
        $cohorte = Cohorte::first();

        $payload = [
            'codigo_matricula' => '1021999999',
            'numero_documento' => '78912345',
            'nombres' => 'Alejandro',
            'apellido_paterno' => 'Castillo',
            'apellido_materno' => 'Ramos',
            'id_cohorte' => $cohorte->id,
            'periodo_egreso' => '2023-II',
            'grado' => 'TITULADO',
            'apto_f1' => 'APTO',
            'correo_institucional' => 'acastillo@unitru.edu.pe',
            'telefono' => '944888777',
        ];

        $response = $this->postJson(route('seguimiento-egresado.padron.guardar-egresado'), $payload);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('seguimiento_egresado.egresado', [
            'codigo_matricula' => '1021999999',
            'numero_documento' => '78912345',
        ]);
    }

    public function test_guardar_cohorte_persists_in_database(): void
    {
        $payload = [
            'anio_ingreso' => 2024,
            'total_ingresantes' => 60,
            'codigo_promocion' => 'PROM-2024',
            'duracion_esperada_anios' => 5,
            'observacion' => 'Promoción 2024 — Alan Kay',
        ];

        $response = $this->postJson(route('seguimiento-egresado.padron.guardar-cohorte'), $payload);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('seguimiento_egresado.cohorte', [
            'anio_ingreso' => 2024,
            'total_ingresantes' => 60,
        ]);
    }

    public function test_guardar_estado_academico_registers_new_history_node(): void
    {
        $egresado = Egresado::first();

        $payload = [
            'id_egresado' => $egresado->id,
            'grado' => 'TITULADO',
            'apto_f1' => 'APTO',
            'creditos_aprobados' => 220.0,
            'resolucion_titulo' => 'Resolución Rectoral Nº 0999-2024-UNT',
            'fecha_titulo' => '2024-10-15',
        ];

        $response = $this->postJson(route('seguimiento-egresado.padron.guardar-estado'), $payload);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $egresadoActualizado = $egresado->fresh(['estadoVigente.estadoAcademico']);
        $this->assertSame('TITULADO', $egresadoActualizado->estadoVigente->estadoAcademico->codigo);
        $this->assertSame('Resolución Rectoral Nº 0999-2024-UNT', $egresadoActualizado->estadoVigente->resolucion_titulo);
    }

    public function test_guardar_plan_curricular_persists_in_database(): void
    {
        $payload = [
            'codigo_version' => 'Plan 2030',
            'vigencia_desde' => '2030-03-01',
            'vigencia_hasta' => null,
            'estado' => 'BORRADOR',
        ];

        $response = $this->postJson(route('seguimiento-egresado.padron.guardar-plan'), $payload);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('seguimiento_egresado.plan_curricular', [
            'codigo_version' => 'Plan 2030',
            'estado' => 'BORRADOR',
        ]);
    }

    public function test_planes_curriculares_1998_2017_2027_exist(): void
    {
        $this->assertDatabaseHas('seguimiento_egresado.plan_curricular', ['codigo_version' => 'Plan 1998']);
        $this->assertDatabaseHas('seguimiento_egresado.plan_curricular', ['codigo_version' => 'Plan 2017']);
        $this->assertDatabaseHas('seguimiento_egresado.plan_curricular', ['codigo_version' => 'Plan 2027']);
    }

    public function test_importacion_rechaza_cohorte_inexistente_sin_crear_50_ingresantes(): void
    {
        $service = new PadronImportService;

        // Cohorte 1975 no existe en la base de datos
        $this->assertFalse(Cohorte::where('anio_ingreso', 1975)->exists());

        $csv = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo,telefono,estado\n"
             ."1021499991,DNI,78881111,Egresado Prueba,Flores,Ruiz,1975,flores@gmail.com,987654321,EGRESADO\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'csv_cohorte_');
        file_put_contents($tempPath, $csv);

        $res = $service->importarArchivo($tempPath);
        @unlink($tempPath);

        $this->assertSame(1, $res['total_procesadas']);
        $this->assertSame(0, $res['aceptadas']);
        $this->assertSame(1, $res['rechazadas']);

        // Verificar que NO se haya creado la cohorte 1975 con 50 ingresantes
        $this->assertFalse(Cohorte::where('anio_ingreso', 1975)->exists());
        $this->assertFalse(Egresado::where('codigo_matricula', '1021499991')->exists());

        $filaRechazada = ImportacionFila::where('id_importacion', $res['importacion_id'])->first();
        $this->assertNotNull($filaRechazada);
        $this->assertStringContainsString('no existe en el catálogo institucional', $filaRechazada->detalle_error);
    }

    public function test_importacion_modo_atomico_cancela_todo_si_hay_errores(): void
    {
        $service = new PadronImportService;

        // Fila 1 es válida (cohorte 2018 existe), pero Fila 2 tiene matrícula vacía
        $csv = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo,telefono,estado\n"
             ."1021488881,DNI,77771111,Carlos,Mendoza,Paz,2018,carlos@gmail.com,987654321,EGRESADO\n"
             .",DNI,77772222,Invalido,SinMatricula,Paz,2018,inv@gmail.com,987654322,EGRESADO\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'csv_atomico_');
        file_put_contents($tempPath, $csv);

        // Se ejecuta en modo atómico ($atomico = true)
        $res = $service->importarArchivo($tempPath, null, true);
        @unlink($tempPath);

        $this->assertSame(2, $res['total_procesadas']);
        $this->assertSame(0, $res['aceptadas']);
        $this->assertSame(2, $res['rechazadas']);
        $this->assertSame('CON_ERRORES', $res['estado']);

        // Verificamos que no se guardó ningún registro parcial (ni siquiera la Fila 1 que era válida)
        $this->assertFalse(Egresado::where('codigo_matricula', '1021488881')->exists());
    }

    public function test_importacion_xlsx_reconoce_y_procesa_archivo_excel(): void
    {
        $service = new PadronImportService;

        $zip = new \ZipArchive;
        $tempXlsx = tempnam(sys_get_temp_dir(), 'xlsx_t_').'.xlsx';
        $zip->open($tempXlsx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Sheet1" sheetId="1" id="rId1"/></sheets></workbook>');

        $strings = [
            'matricula', 'tipo_doc', 'numero_documento', 'nombres', 'apellido_paterno', 'apellido_materno', 'cohorte', 'correo_personal', 'telefono', 'estado',
            '1021477777', 'DNI', '76665555', 'Egresado', 'Excel', 'Unt', '2018', 'excel@unt.pe', '955444333', 'EGRESADO',
        ];

        $sstXml = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach ($strings as $str) {
            $sstXml .= '<si><t>'.htmlspecialchars($str).'</t></si>';
        }
        $sstXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $sstXml);

        $sheetXml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'
            .'<c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c>'
            .'<c r="E1" t="s"><v>4</v></c><c r="F1" t="s"><v>5</v></c><c r="G1" t="s"><v>6</v></c><c r="H1" t="s"><v>7</v></c>'
            .'<c r="I1" t="s"><v>8</v></c><c r="J1" t="s"><v>9</v></c>'
            .'</row>'
            .'<row r="2">'
            .'<c r="A2" t="s"><v>10</v></c><c r="B2" t="s"><v>11</v></c><c r="C2" t="s"><v>12</v></c><c r="D2" t="s"><v>13</v></c>'
            .'<c r="E2" t="s"><v>14</v></c><c r="F2" t="s"><v>15</v></c><c r="G2" t="s"><v>16</v></c><c r="H2" t="s"><v>17</v></c>'
            .'<c r="I2" t="s"><v>18</v></c><c r="J2" t="s"><v>19</v></c>'
            .'</row>'
            .'</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $res = $service->importarArchivo($tempXlsx);
        @unlink($tempXlsx);

        $this->assertSame(1, $res['total_procesadas']);
        $this->assertSame(1, $res['aceptadas']);
        $this->assertSame(0, $res['rechazadas']);

        $egresado = Egresado::where('codigo_matricula', '1021477777')->first();
        $this->assertNotNull($egresado);
        $this->assertSame('76665555', $egresado->numero_documento);
    }
}
