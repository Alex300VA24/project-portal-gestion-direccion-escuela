<?php

namespace Tests\Support\GestionHorarios\Engine;

use DomainException;

/** Únicamente escenarios fijos incluidos en el módulo; nunca recibe rutas ni datos académicos. */
final class CatalogoEscenariosPrueba
{
    public function entrada(string $escenario): array
    {
        if (! in_array($escenario, ['academico_factible_v1', 'academico_inviable_v1'], true)) {
            throw new DomainException('Escenario de prueba desconocido.');
        }
        $motor = json_decode(file_get_contents(__DIR__.'/Escenarios/academico.json'), true, 512, JSON_THROW_ON_ERROR);
        $motor['secciones'][1]['etiqueta'] = 'B';
        $actividades = [];
        foreach ($motor['actividades'] as $actividad) {
            $actividad['tipo_academico'] = ['T' => 'TEORIA', 'P' => 'PRACTICA', 'LAB' => 'LABORATORIO'][$actividad['tipo']];
            $actividad['duraciones_minutos'] = array_map(fn ($h) => $h * 60, $actividad['duraciones']);
            unset($actividad['tipo'], $actividad['duraciones'], $actividad['horas']);
            $actividades[] = $actividad;
        }
        if ($escenario === 'academico_inviable_v1') {
            $motor['disponibilidades'][0]['franjas'] = [];
        }
        $fuente = ['contrato' => 'HORARIOS_DATOS_FICTICIOS_V1', 'origen' => 'PRUEBA_FICTICIA', 'periodo' => 'FICTICIO', 'politica' => 'SECCION_Y_COMPARTIDOS_V4', 'tiempo_limite' => 5, 'configuracion' => $motor['configuracion'], 'secciones' => $motor['secciones'], 'actividades' => $actividades,
            'docentes' => [['id' => 'D1', 'nombre' => 'Docente 1'], ['id' => 'D2', 'nombre' => 'Docente 2']], 'ambientes' => $motor['ambientes'], 'disponibilidades' => $motor['disponibilidades'], 'bloqueos' => [], 'sesiones' => [],
            'ofertas' => [['id' => 'OF1', 'periodo' => 'FICTICIO', 'curso_id' => '901', 'tipo' => 'REGULAR'], ['id' => 'OF2', 'periodo' => 'FICTICIO', 'curso_id' => '902', 'tipo' => 'REGULAR']],
            'asociaciones' => [['seccion_id' => 'S1', 'oferta_id' => 'OF1'], ['seccion_id' => 'S2', 'oferta_id' => 'OF2']]];
        $resultado = (new AdaptadorMotorFicticio)->convertir($fuente);
        if (! $resultado['compatible']) {
            throw new DomainException('Escenario de prueba inválido.');
        }

        return $resultado;
    }
}
