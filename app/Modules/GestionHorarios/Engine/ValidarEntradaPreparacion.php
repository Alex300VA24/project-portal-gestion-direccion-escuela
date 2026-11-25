<?php

namespace App\Modules\GestionHorarios\Engine;

use DomainException;

final class ValidarEntradaPreparacion
{
    public function validar(array $entrada): void
    {
        if (($entrada['contrato'] ?? null) !== 'HORARIOS_PREPARACION_V1' || ! is_array($entrada['datos'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $entrada['huella_instantanea'] ?? '') || ($entrada['configuracion']['tiempo_limite_segundos'] ?? null) !== 150 || ($entrada['listo_para_motor'] ?? null) !== false || ! is_array($entrada['diagnosticos'] ?? null)) {
            throw new DomainException('Formato de preparación incompatible.');
        }
        $datos = $entrada['datos'];
        if (isset($datos['oferta_seccion'])) {
            if (! is_array($datos['oferta_seccion']) || ! array_is_list($datos['oferta_seccion'])) {
                throw new DomainException('Colección de asociaciones inválida.');
            }
            $asociaciones = [];
            foreach ($datos['oferta_seccion'] as $asociacion) {
                if (! is_array($asociacion) || ! is_scalar($asociacion['id_oferta_seccion'] ?? null) || ! is_scalar($asociacion['id_oferta'] ?? null) || ! is_scalar($asociacion['id_seccion'] ?? null) || ! preg_match('/^[1-9][0-9]*$/', (string) $asociacion['id_oferta_seccion']) || ! preg_match('/^[1-9][0-9]*$/', (string) $asociacion['id_oferta']) || ! preg_match('/^[1-9][0-9]*$/', (string) $asociacion['id_seccion']) || ! in_array($asociacion['estado'] ?? null, ['VIGENTE', 'CERRADA'], true) || isset($asociaciones[(string) $asociacion['id_oferta_seccion']])) {
                    throw new DomainException('Asociación con formato inválido o duplicada.');
                }
                $asociaciones[(string) $asociacion['id_oferta_seccion']] = true;
            }
        }
        if (! is_array($datos['periodo'] ?? null) || (int) ($datos['periodo']['id_periodo'] ?? 0) <= 0) {
            throw new DomainException('Periodo inválido.');
        }
        $claves = ['cursos' => 'id_curso_plan', 'ofertas' => 'id_oferta', 'secciones' => 'id_seccion', 'actividades' => 'id_actividad', 'asignaciones' => 'id_asignacion', 'docentes' => 'id_docente', 'declaraciones' => 'id_declaracion', 'disponibilidades' => 'id_disponibilidad', 'bloques' => 'id_bloque', 'ambientes' => 'id_ambiente', 'bloqueos' => 'id_bloqueo', 'horarios' => 'id_horario'];
        $ids = [];
        foreach ($claves as $coleccion => $clave) {
            $filas = $datos[$coleccion] ?? null;
            if (! is_array($filas) || ! array_is_list($filas)) {
                throw new DomainException('Colección inválida: '.$coleccion);
            }
            foreach ($filas as $fila) {
                if (! is_array($fila)) {
                    throw new DomainException('Registro inválido: '.$coleccion);
                }
                $id = $fila[$clave] ?? null;
                if ((! is_int($id) && ! is_string($id)) || ! preg_match('/^[1-9][0-9]*$/', (string) $id) || isset($ids[$coleccion][(string) $id])) {
                    throw new DomainException('Identificador inválido o duplicado: '.$coleccion);
                }
                $ids[$coleccion][(string) $id] = true;
            }
        }
        $existe = static fn (string $tabla, mixed $id): bool => $id !== null && isset($ids[$tabla][(string) $id]);
        foreach ($datos['secciones'] as $fila) {
            if (! $existe('cursos', $fila['id_curso_plan'] ?? null) || (int) ($fila['id_periodo'] ?? 0) !== (int) $datos['periodo']['id_periodo']) {
                throw new DomainException('Referencia de sección inválida.');
            }
        }
        foreach ($datos['ofertas'] as $fila) {
            if (! $existe('cursos', $fila['id_curso_plan'] ?? null) || (int) ($fila['id_periodo'] ?? 0) !== (int) $datos['periodo']['id_periodo'] || ! in_array($fila['tipo_oferta'] ?? null, ['REGULAR', 'REPETICION'], true)) {
                throw new DomainException('Referencia o tipo de oferta inválido.');
            }
        }
        foreach ($datos['actividades'] as $fila) {
            if (! $existe('secciones', $fila['id_seccion'] ?? null) || ! in_array($fila['tipo_actividad'] ?? null, ['TEORIA', 'PRACTICA', 'LABORATORIO', 'TALLER'], true) || ! is_int($fila['duracion_minutos'] ?? null) || $fila['duracion_minutos'] <= 0 || $this->duracion($fila['hora_inicio'] ?? null, $fila['hora_fin'] ?? null) !== $fila['duracion_minutos']) {
                throw new DomainException('Actividad inválida.');
            }
        }
        $confirmadas = [];
        foreach ($datos['asignaciones'] as $fila) {
            if (! $existe('actividades', $fila['id_actividad'] ?? null) || ! $existe('docentes', $fila['id_docente'] ?? null)) {
                throw new DomainException('Asignación con referencia inválida.');
            }
            if (($fila['estado'] ?? null) === 'CONFIRMADA') {
                $clave = $fila['id_actividad'].':'.$fila['id_docente'];
                if (isset($confirmadas[$clave])) {
                    throw new DomainException('Asignación confirmada duplicada.');
                }
                $confirmadas[$clave] = true;
            }
        }
        $periodo = (int) $datos['periodo']['id_periodo'];
        $declaraciones = [];
        foreach ($datos['declaraciones'] as $fila) {
            if (! $existe('docentes', $fila['id_docente'] ?? null) || (int) ($fila['id_periodo'] ?? 0) !== $periodo) {
                throw new DomainException('Declaración con referencia inválida.');
            }
            $declaraciones[(string) $fila['id_declaracion']] = (string) $fila['id_docente'];
        }
        foreach ($datos['bloques'] as $fila) {
            if ((int) ($fila['id_periodo'] ?? 0) !== $periodo || empty($fila['hora_inicio']) || empty($fila['hora_fin']) || $fila['hora_inicio'] >= $fila['hora_fin']) {
                throw new DomainException('Bloque horario inválido.');
            }
        }
        foreach ($datos['disponibilidades'] as $fila) {
            if (! $existe('docentes', $fila['id_docente'] ?? null) || ! $existe('bloques', $fila['id_bloque'] ?? null) || (int) ($fila['id_periodo'] ?? 0) !== $periodo || ! in_array($fila['dia_semana'] ?? null, ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'], true) || (($fila['id_declaracion'] ?? null) !== null && ($declaraciones[(string) $fila['id_declaracion']] ?? null) !== (string) $fila['id_docente'])) {
                throw new DomainException('Disponibilidad con referencia inválida.');
            }
        }
        foreach ($datos['bloqueos'] as $fila) {
            if (! $existe('ambientes', $fila['id_ambiente'] ?? null) || (int) ($fila['id_periodo'] ?? 0) !== $periodo) {
                throw new DomainException('Bloqueo con ambiente inexistente.');
            }
        }
        if (! array_key_exists('restricciones', $datos) || ! is_array($datos['restricciones']) || ! array_is_list($datos['restricciones']) || ! array_key_exists('demanda', $datos)) {
            throw new DomainException('Faltan restricciones o demanda.');
        }
        if ($datos['demanda'] !== null) {
            $vistos = [];
            foreach ($datos['demanda']['cursos'] ?? [] as $fila) {
                $id = (string) ($fila['id_curso_plan'] ?? '');
                if (! $existe('cursos', $id) || isset($vistos[$id])) {
                    throw new DomainException('Demanda con referencia inválida o duplicada.');
                }
                $total = $fila['total'] ?? null;
                $manana = $fila['manana'] ?? null;
                $tarde = $fila['tarde'] ?? null;
                if (! is_numeric($total) || ! is_numeric($manana) || ! is_numeric($tarde) || (int) $total < 0 || (int) $manana < 0 || (int) $tarde < 0 || (int) $manana > (int) $total || (int) $tarde > (int) $total - (int) $manana) {
                    throw new DomainException('Agregados de demanda inválidos.');
                }
                $vistos[$id] = true;
            }
        }
    }

    private function duracion(mixed $inicio, mixed $fin): ?int
    {
        $segundos = static function (mixed $valor): ?int {
            if (! is_string($valor) || ! preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $valor, $m) || (int) $m[1] > 23 || (int) $m[2] > 59 || (int) $m[3] > 59) {
                return null;
            }

            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        };
        $a = $segundos($inicio);
        $b = $segundos($fin);
        if ($a === null || $b === null || $b <= $a || ($b - $a) % 60 !== 0) {
            return null;
        }

        return intdiv($b - $a, 60);
    }
}
