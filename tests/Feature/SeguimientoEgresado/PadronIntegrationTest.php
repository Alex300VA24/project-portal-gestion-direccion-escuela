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
use Illuminate\Http\UploadedFile;
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

    private function importar(string $csv): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($ruta, $csv);
        $resultado = (new PadronImportService)->importarArchivo($ruta);
        @unlink($ruta);

        return $resultado;
    }

    private const CABECERA = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo_institucional\n";

    public function test_la_importacion_deja_alumnos_aptos_sin_inventar_datos_y_detecta_duplicados(): void
    {
        $res = $this->importar(self::CABECERA."1021400998,DNI,79991111,Alumno Unitario,Perez,Gomez,2018,unitario@correo.test\n");

        $this->assertSame(1, $res['aceptadas']);
        $this->assertSame(0, $res['rechazadas']);
        $this->assertSame(1, ImportacionPadron::find($res['importacion_id'])->filas()->count());

        $alumno = Egresado::where('codigo_matricula', '1021400998')->first();
        $this->assertNotNull($alumno);
        // Apto para egresar, todavía no egresado.
        $this->assertSame('PROXIMO', $alumno->estadoVigente->estadoAcademico->codigo);
        $this->assertTrue((bool) $alumno->estadoVigente->apto_graduarse);
        // Solo lo que trae el archivo: nada de fechas, créditos, resoluciones ni contacto inventados.
        $this->assertSame('unitario@correo.test', $alumno->correo_institucional);
        $this->assertNull($alumno->fecha_egreso);
        $this->assertNull($alumno->fecha_ingreso);
        $this->assertNull($alumno->periodo_egreso);
        $this->assertNull($alumno->estadoVigente->creditos_aprobados);
        $this->assertNull($alumno->estadoVigente->resolucion_bachiller);
        $this->assertNull($alumno->contactoVigente, 'el contacto lo registra el alumno con su enlace');

        $res = $this->importar(self::CABECERA."1021400998,DNI,79991111,Alumno Duplicado,Perez,Gomez,2018,otro@correo.test\n");
        $this->assertSame(0, $res['aceptadas']);
        $this->assertSame(1, $res['rechazadas']);
        $fila = ImportacionFila::where('id_importacion', $res['importacion_id'])->where('estado', 'RECHAZADA')->first();
        $this->assertStringContainsString('ya está registrada en el padrón', $fila->detalle_error);
    }

    public function test_el_correo_institucional_es_obligatorio_y_valido(): void
    {
        $sin = $this->importar(self::CABECERA."1021400001,DNI,79990001,Sin Correo,Perez,Gomez,2018,\n");
        $malo = $this->importar(self::CABECERA."1021400002,DNI,79990002,Correo Malo,Perez,Gomez,2018,no-es-un-correo\n");

        foreach ([$sin, $malo] as $resultado) {
            $this->assertSame(0, $resultado['aceptadas']);
            $this->assertSame(1, $resultado['rechazadas']);
        }
        $this->assertFalse(Egresado::whereIn('codigo_matricula', ['1021400001', '1021400002'])->exists());
        $this->assertStringContainsString('correo institucional', ImportacionFila::where('id_importacion', $sin['importacion_id'])->value('detalle_error'));
    }

    public function test_una_fecha_futura_o_invalida_en_el_archivo_se_rechaza(): void
    {
        $res = $this->importar("matricula,tipo_doc,numero_documento,nombres,apellido_paterno,cohorte,correo_institucional,fecha_egreso\n1021400003,DNI,79990003,Fecha Futura,Perez,2018,f@correo.test,2099-01-01\n");

        $this->assertSame(1, $res['rechazadas']);
    }

    public function test_el_contacto_solo_se_guarda_si_el_archivo_trae_correo_personal_y_celular(): void
    {
        $this->importar("matricula,tipo_doc,numero_documento,nombres,apellido_paterno,cohorte,correo_institucional,correo_personal,celular\n"
            ."1021400004,DNI,79990004,Con Contacto,Perez,2018,c@correo.test,personal@correo.test,987654321\n");

        $alumno = Egresado::where('codigo_matricula', '1021400004')->first();
        $this->assertSame('personal@correo.test', $alumno->contactoVigente->correo_personal);
        $this->assertSame('987654321', $alumno->contactoVigente->celular_whatsapp);
    }

    public function test_la_plantilla_no_trae_correos_personales_ni_datos_academicos_inventados(): void
    {
        $contenido = $this->get(route('seguimiento-egresado.padron.plantilla'))->streamedContent();

        $this->assertStringContainsString('correo_institucional', $contenido);
        $this->assertStringNotContainsString('gmail', $contenido);
        $this->assertStringNotContainsString('TITULADO', $contenido);
    }

    private function alumnoApto(array $cambios = []): Egresado
    {
        $this->importar(self::CABECERA."1021400555,DNI,79995555,Alumno Apto,Quispe,Rojas,2018,apto@correo.test\n");
        $alumno = Egresado::where('codigo_matricula', '1021400555')->first();
        $alumno->update($cambios);

        return $alumno;
    }

    public function test_confirmar_el_egreso_guarda_la_fecha_real_y_abre_la_etapa_de_egresado_conservando_el_contacto(): void
    {
        $alumno = $this->alumnoApto();
        $alumno->contactos()->create(['correo_personal' => 'p@correo.test', 'celular_whatsapp' => '900111222', 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO']);

        $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $alumno->id), ['fecha_egreso' => '2025-07-18'])
            ->assertOk()->assertJsonPath('success', true);

        $alumno->refresh();
        $this->assertSame('2025-07-18', substr((string) $alumno->fecha_egreso, 0, 10));
        $this->assertSame('2025-I', $alumno->periodo_egreso);
        $this->assertSame('EGRESADO', $alumno->estadoVigente->estadoAcademico->codigo);
        $this->assertSame(['PROXIMO', 'EGRESADO'], $alumno->historialEstados()->reorder('vigente_desde')->get()->map(fn ($h) => $h->estadoAcademico->codigo)->all(), 'se conserva el historial');
        $this->assertSame('p@correo.test', $alumno->contactoVigente->correo_personal);
    }

    public function test_sin_contacto_inicial_no_se_puede_confirmar_el_egreso(): void
    {
        $alumno = $this->alumnoApto();

        $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $alumno->id), ['fecha_egreso' => '2025-07-18'])
            ->assertStatus(422)->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Este alumno todavía no completó su contacto inicial, que es obligatorio para egresar. Envíale el enlace desde «Contacto inicial» y vuelve a intentarlo cuando lo complete.']);

        $alumno->refresh();
        $this->assertNull($alumno->fecha_egreso);
        $this->assertSame('PROXIMO', $alumno->estadoVigente->estadoAcademico->codigo);
    }

    public function test_la_carga_une_a_todos_los_alumnos_a_la_promocion_elegida_aunque_el_archivo_no_la_traiga(): void
    {
        $promocion = Cohorte::firstOrFail();
        $csv = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,correo_institucional\n"
             ."1021477001,DNI,78880001,Ana,Lopez,Diaz,ana.lopez@correo.test\n"
             ."1021477002,DNI,78880002,Beto,Rios,Paz,beto.rios@correo.test\n";

        $this->post(route('seguimiento-egresado.padron.importar'), [
            'promocion' => $promocion->id, 'archivo_padron' => UploadedFile::fake()->createWithContent('aptos.csv', $csv),
        ])->assertRedirect();

        $this->assertSame(
            [$promocion->id, $promocion->id],
            Egresado::whereIn('codigo_matricula', ['1021477001', '1021477002'])->pluck('id_cohorte')->all(),
        );
        $this->assertStringContainsString('Se cargaron 2 alumnos en la Promoción '.$promocion->anio_ingreso, session('success'));
    }

    public function test_la_carga_rechaza_la_fila_que_indica_otra_promocion_y_explica_cual(): void
    {
        $promocion = Cohorte::orderBy('id')->firstOrFail();
        $otra = Cohorte::where('id', '!=', $promocion->id)->where('anio_ingreso', '!=', $promocion->anio_ingreso)->firstOrFail();
        $csv = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo_institucional\n"
             ."1021477011,DNI,78880011,Ana,Lopez,Diaz,{$promocion->anio_ingreso},ana.lopez@correo.test\n"
             ."1021477012,DNI,78880012,Beto,Rios,Paz,{$otra->anio_ingreso},beto.rios@correo.test\n";

        $this->post(route('seguimiento-egresado.padron.importar'), [
            'promocion' => $promocion->id, 'archivo_padron' => UploadedFile::fake()->createWithContent('aptos.csv', $csv),
        ])->assertRedirect();

        $this->assertFalse(Egresado::whereIn('codigo_matricula', ['1021477011', '1021477012'])->exists(), 'todo o nada');
        $mensaje = session('error');
        $this->assertStringContainsString('No se cargó ningún alumno', $mensaje);
        $this->assertStringContainsString("Fila 2: La fila indica la promoción '{$otra->anio_ingreso}', pero elegiste la promoción {$promocion->anio_ingreso}", $mensaje);
        $this->assertStringNotContainsString('Fila 1:', $mensaje, 'la fila correcta no se cuenta como problema');
    }

    public function test_la_carga_exige_elegir_la_promocion(): void
    {
        $this->post(route('seguimiento-egresado.padron.importar'), [
            'archivo_padron' => UploadedFile::fake()->createWithContent('aptos.csv', "matricula\n1\n"),
        ])->assertSessionHasErrors(['promocion' => 'Elige la promoción a la que pertenecen los alumnos del archivo.']);
    }

    public function test_el_periodo_de_egreso_se_calcula_de_la_fecha(): void
    {
        $alumno = $this->alumnoApto();
        $alumno->contactos()->create(['correo_personal' => 'p2@correo.test', 'celular_whatsapp' => '900111222', 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO']);

        $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $alumno->id), ['fecha_egreso' => '2025-12-12'])->assertOk();

        $this->assertSame('2025-II', $alumno->refresh()->periodo_egreso);
    }

    public function test_confirmar_el_egreso_exige_una_fecha_real(): void
    {
        $alumno = $this->alumnoApto();
        $ruta = route('seguimiento-egresado.padron.confirmar-egreso', $alumno->id);

        $this->postJson($ruta, [])->assertStatus(422)->assertJsonValidationErrors('fecha_egreso');
        $this->postJson($ruta, ['fecha_egreso' => '2999-01-01'])->assertStatus(422)->assertJsonValidationErrors('fecha_egreso');
        $this->assertSame('PROXIMO', $alumno->refresh()->estadoVigente->estadoAcademico->codigo);
        $this->assertNull($alumno->fecha_egreso);
    }

    public function test_la_fecha_de_egreso_no_puede_ser_anterior_al_ingreso(): void
    {
        $alumno = $this->alumnoApto(['fecha_ingreso' => '2020-03-16']);

        $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $alumno->id), ['fecha_egreso' => '2019-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_egreso');
    }

    public function test_solo_se_confirma_el_egreso_de_un_alumno_apto(): void
    {
        $yaEgresado = Egresado::whereHas('estadoVigente.estadoAcademico', fn ($q) => $q->where('codigo', 'EGRESADO'))->firstOrFail();

        $this->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $yaEgresado->id), ['fecha_egreso' => '2025-07-18'])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_solo_la_secretaria_confirma_egresos(): void
    {
        $alumno = $this->alumnoApto();

        $this->actingAs($this->director())->postJson(route('seguimiento-egresado.padron.confirmar-egreso', $alumno->id), ['fecha_egreso' => '2025-07-18'])->assertForbidden();
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

        $csv = "matricula,tipo_doc,numero_documento,nombres,apellido_paterno,apellido_materno,cohorte,correo_institucional\n"
             ."1021499991,DNI,78881111,Alumno Prueba,Flores,Ruiz,1975,flores@correo.test\n";

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
        $this->assertStringContainsString('no está registrada', $filaRechazada->detalle_error);
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
            'matricula', 'tipo_doc', 'numero_documento', 'nombres', 'apellido_paterno', 'apellido_materno', 'cohorte', 'correo_institucional', 'telefono', 'estado',
            '1021477777', 'DNI', '76665555', 'Alumno', 'Excel', 'Unt', '2018', 'excel@correo.test', '955444333', 'PROXIMO',
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
