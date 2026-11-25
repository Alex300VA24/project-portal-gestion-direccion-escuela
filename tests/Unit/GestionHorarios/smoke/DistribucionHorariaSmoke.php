<?php

use App\Modules\GestionHorarios\Services\DistribucionHoraria;

// Ejecutar desde la raíz del portal: php tests/Unit/GestionHorarios/smoke/DistribucionHorariaSmoke.php
// No necesita conexión a base de datos ni dependencias adicionales.
require_once dirname(__DIR__, 4).'/app/Modules/GestionHorarios/Services/DistribucionHoraria.php';

function actividad(int $id, string $dia, string $inicio, string $fin, string $seccion = 'A', string $ciclo = '6'): object
{
    return (object) [
        'id_detalle' => $id, 'id_actividad' => $id, 'id_plan' => 1,
        'ciclo' => $ciclo, 'seccion' => $seccion, 'dia_semana' => $dia,
        'hora_inicio' => $inicio, 'hora_fin' => $fin,
        'curso_codigo' => 'C'.$id, 'curso_nombre' => 'Curso '.$id,
        'tipo_actividad' => 'LABORATORIO', 'ambiente' => 'LAB 2',
        'facultad_nombre' => 'Facultad de prueba', 'escuela_nombre' => 'Escuela de prueba',
    ];
}

function verificar(bool $condicion, string $mensaje): void
{
    if (! $condicion) {
        throw new RuntimeException($mensaje);
    }
}

$distribuidor = new DistribucionHoraria;
$grupos = $distribuidor->preparar([
    actividad(1, 'LUNES', '07:00:00', '09:00:00'),
    actividad(2, 'LUNES', '07:00:00', '09:00:00'),
    actividad(3, 'LUNES', '08:00:00', '10:00:00'),
    actividad(4, 'LUNES', '10:00:00', '11:00:00'),
], ['1' => ['Profesora Uno'], '2' => ['Profesor Dos']]);
verificar(count($grupos) === 1, 'Debe agrupar las cuatro sesiones.');
verificar(count($grupos[0]['sesiones']) === 4, 'No puede perder sesiones.');
$tarjetas = $grupos[0]['tarjetas']['LUNES'];
verificar(count($tarjetas) === 3, 'Las coincidencias exactas deben apilarse.');
verificar(count($tarjetas[0]['items']) === 2, 'Las LAB simultáneas deben conservar ambos grupos.');
verificar($tarjetas[0]['slots'] === 2 && $tarjetas[1]['slots'] === 2, 'Los intervalos parciales deben usar carriles.');
verificar($tarjetas[2]['slots'] === 1, 'Los intervalos contiguos no son simultáneos.');
verificar($tarjetas[0]['items'][0]['docente'] === 'Profesora Uno', 'Debe conservar docente.');

$tesis = $distribuidor->preparar([
    actividad(5, 'MARTES', '09:00:00', '11:00:00', 'G1', '10'),
    actividad(6, 'MARTES', '09:00:00', '11:00:00', 'G2', '10'),
], []);
verificar(count($tesis) === 1 && $tesis[0]['seccion'] === 'A', 'G1 y G2 son grupos del X ciclo, no secciones.');
verificar($tesis[0]['sesiones'][0]['grupo'] === 'G1', 'Debe conservar la etiqueta de tesis.');

$parciales = $distribuidor->preparar([
    actividad(7, 'VIERNES', '07:30:00', '09:30:00'),
], [], [(object) ['hora_inicio' => '07:00:00', 'hora_fin' => '20:00:00']]);
verificar($parciales[0]['desde'] === 420 && $parciales[0]['hasta'] === 1200, 'Debe usar el eje de horas del periodo.');
verificar(abs($parciales[0]['tarjetas']['VIERNES'][0]['top'] - 100 * 30 / 780) < 0.01, 'Debe respetar inicios parciales.');
verificar(DistribucionHoraria::color('Álgebra') === DistribucionHoraria::color('Álgebra'), 'El color debe ser estable.');

echo "DistribucionHoraria: pruebas de humo correctas.\n";
