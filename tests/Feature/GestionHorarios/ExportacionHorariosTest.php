<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\ExportacionHorarios;
use Illuminate\Database\ConnectionInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

final class ExportacionHorariosTest extends TestCase
{
    public function test_genera_pdf_y_excel_reales_con_actividades_separadas(): void
    {
        $sesiones = collect([
            (object) ['id_actividad' => 1, 'id_plan' => 1, 'ciclo' => 1, 'seccion' => 'A', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'curso_codigo' => 'C1', 'curso_nombre' => 'Curso uno', 'tipo_actividad' => 'TEORIA', 'ambiente' => 'Aula'],
            (object) ['id_actividad' => 2, 'id_plan' => 1, 'ciclo' => 1, 'seccion' => 'B', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'curso_codigo' => '=C2', 'curso_nombre' => 'Curso dos', 'tipo_actividad' => 'LABORATORIO', 'ambiente' => 'Laboratorio'],
            (object) ['id_actividad' => 3, 'id_plan' => 1, 'ciclo' => 1, 'seccion' => 'A', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'curso_codigo' => 'C3', 'curso_nombre' => 'Curso tres', 'tipo_actividad' => 'PRACTICA', 'ambiente' => 'Aula'],
            (object) ['id_actividad' => 4, 'id_plan' => 2, 'ciclo' => 1, 'seccion' => 'A', 'dia_semana' => 'LUNES', 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'curso_codigo' => 'P2', 'curso_nombre' => 'Curso del otro plan', 'tipo_actividad' => 'TEORIA', 'ambiente' => 'Aula'],
            (object) ['id_actividad' => 5, 'id_plan' => 1, 'ciclo' => 1, 'seccion' => 'A', 'dia_semana' => 'LUNES', 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'curso_codigo' => 'C5', 'curso_nombre' => 'Superposición parcial', 'tipo_actividad' => 'LABORATORIO', 'ambiente' => 'Lab'],
            (object) ['id_actividad' => 6, 'id_plan' => 1, 'ciclo' => 1, 'seccion' => 'A', 'dia_semana' => 'LUNES', 'hora_inicio' => '11:00', 'hora_fin' => '12:00', 'curso_codigo' => 'C6', 'curso_nombre' => 'Sesión consecutiva', 'tipo_actividad' => 'TEORIA', 'ambiente' => 'Aula'],
        ]);
        $datos = ['sesiones' => $sesiones, 'docentes' => collect(), 'periodoSeleccionado' => (object) ['codigo' => '2026-I']];
        $servicio = new ExportacionHorarios($this->createStub(ConnectionInterface::class));
        self::assertStringStartsWith('%PDF-', $servicio->pdf($datos));
        $archivo = tempnam(sys_get_temp_dir(), 'horarios-xlsx-');
        try {
            file_put_contents($archivo, $servicio->xlsx($datos));
            $libro = IOFactory::createReader('Xlsx')->load($archivo);
            self::assertSame(3, $libro->getSheetCount());
            self::assertSame('LUNES', $libro->getSheet(0)->getCell('B7')->getValue());
            $contenido = [];
            foreach ($libro->getAllSheets() as $hoja) {
                $celdas = [];
                for ($fila = 8; $fila <= $hoja->getHighestRow(); $fila++) {
                    $celda = $hoja->getCell('B'.$fila);
                    if (is_string($celda->getValue()) && $celda->getValue() !== '') {
                        $celdas[] = $celda->getValue();
                        self::assertSame('s', $celda->getDataType());
                    }
                }
                $contenido[] = implode("\n", $celdas);
                self::assertSame('landscape', $hoja->getPageSetup()->getOrientation());
                self::assertStringStartsWith('A1:B', $hoja->getPageSetup()->getPrintArea());
            }
            self::assertStringContainsString('C1 · Curso uno', $contenido[0]);
            self::assertStringContainsString('C3 · Curso tres', $contenido[0]);
            self::assertStringContainsString('Teoría', $contenido[0]);
            self::assertStringContainsString('Práctica', $contenido[0]);
            self::assertStringContainsString('C5 · Superposición parcial', $contenido[0]);
            self::assertStringContainsString('C6 · Sesión consecutiva', $contenido[0]);
            self::assertStringContainsString('=C2 · Curso dos', $contenido[1]);
            self::assertStringContainsString('Laboratorio', $contenido[1]);
            self::assertStringNotContainsString('P2', $contenido[0]);
            self::assertStringContainsString('P2 · Curso del otro plan', $contenido[2]);
        } finally {
            unlink($archivo);
        }
    }
}
