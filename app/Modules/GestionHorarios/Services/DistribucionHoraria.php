<?php

namespace App\Modules\GestionHorarios\Services;

use InvalidArgumentException;

/**
 * Presentación pura de sesiones persistidas. No deduce reglas académicas,
 * modifica clases ni consulta el motor CP-SAT.
 *
 * Las sesiones con exactamente el mismo intervalo se apilan en una tarjeta;
 * los intervalos parcialmente solapados ocupan carriles independientes.
 */
final class DistribucionHoraria
{
    public const DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

    private const COLORES = [
        'C9E8F5', 'D5EBCB', 'FFE1B4', 'E4D7F6', 'F7D9E4',
        'D5ECEA', 'FDECB7', 'CFE0FA', 'E2E7C2', 'F2DCCB',
    ];

    /** @param array<int, object> $sesiones @param array<int|string, list<string>> $docentes */
    public function preparar(array $sesiones, array $docentes, array $bloques = []): array
    {
        $grupos = [];
        foreach ($sesiones as $indice => $sesion) {
            $ciclo = (string) $sesion->ciclo;
            $seccionOriginal = trim((string) $sesion->seccion);
            $esGrupoTesis = in_array($ciclo, ['10', 'X'], true)
                && preg_match('/^G[1-9][0-9]*$/i', $seccionOriginal) === 1;
            $seccion = $esGrupoTesis ? 'A' : $seccionOriginal;
            $inicio = self::minutos((string) $sesion->hora_inicio);
            $fin = self::minutos((string) $sesion->hora_fin);
            if ($inicio >= $fin) {
                throw new InvalidArgumentException('Existe una actividad con un intervalo horario inválido.');
            }
            $dia = strtoupper(trim((string) $sesion->dia_semana));
            if (! in_array($dia, self::DIAS, true)) {
                throw new InvalidArgumentException('Existe una actividad con un día de semana inválido.');
            }
            $curso = trim((string) $sesion->curso_nombre);
            $tipo = strtoupper(trim((string) $sesion->tipo_actividad));
            $clave = json_encode([(string) $sesion->id_plan, $ciclo, $seccion], JSON_THROW_ON_ERROR);
            $profesores = $docentes[(string) $sesion->id_actividad] ?? [];
            $grupos[$clave]['sesiones'][] = [
                'id' => (string) ($sesion->id_detalle ?? 'pos-'.$indice),
                'dia' => $dia,
                'inicio' => $inicio,
                'fin' => $fin,
                'horario' => self::hora($inicio).' – '.self::hora($fin),
                'codigo' => (string) ($sesion->curso_codigo ?? ''),
                'curso' => $curso,
                'tipo' => ['TEORIA' => 'Teoría', 'PRACTICA' => 'Práctica', 'LABORATORIO' => 'Laboratorio', 'TALLER' => 'Taller'][$tipo] ?? $tipo,
                'grupo' => $esGrupoTesis ? $seccionOriginal : '',
                'docente' => $profesores ? implode(', ', array_values(array_unique($profesores))) : 'Sin asignación confirmada',
                'ambiente' => trim((string) ($sesion->ambiente ?? '')) ?: 'Sin ambiente registrado',
                'color' => self::color($curso),
                'seccion_original' => $seccionOriginal,
            ];
            $grupos[$clave]['datos'] = [
                'plan' => $sesion->id_plan,
                'plan_nombre' => $sesion->plan_nombre ?? $sesion->id_plan,
                'facultad' => $sesion->facultad_nombre ?? 'Facultad no registrada',
                'escuela' => $sesion->escuela_nombre ?? 'Escuela no registrada',
                'ciclo' => $ciclo,
                'seccion' => $seccion,
            ];
        }

        // Los bloques del periodo amplían el eje horario, pero nunca sustituyen
        // una sesión almacenada. Un bloque no confirma un receso ni días activos.
        $limites = [];
        foreach ($bloques as $bloque) {
            try {
                $desde = self::minutos((string) $bloque->hora_inicio);
                $hasta = self::minutos((string) $bloque->hora_fin);
                if ($desde < $hasta) {
                    $limites[] = [$desde, $hasta];
                }
            } catch (InvalidArgumentException) {
                // Un bloque defectuoso no invalida las sesiones persistidas.
            }
        }

        $resultado = [];
        foreach ($grupos as $grupo) {
            $eventos = $grupo['sesiones'];
            usort($eventos, fn (array $a, array $b) => [array_search($a['dia'], self::DIAS, true), $a['inicio'], $a['fin'], $a['id']]
                <=> [array_search($b['dia'], self::DIAS, true), $b['inicio'], $b['fin'], $b['id']]);
            $inicios = array_column($eventos, 'inicio');
            $finales = array_column($eventos, 'fin');
            foreach ($limites as [$desde, $hasta]) {
                $inicios[] = $desde;
                $finales[] = $hasta;
            }
            $desde = intdiv(min($inicios), 60) * 60;
            $hasta = (int) ceil(max($finales) / 60) * 60;
            $dias = array_values(array_filter(self::DIAS, fn (string $dia) => in_array($dia, array_column($eventos, 'dia'), true)));
            $tarjetas = [];
            foreach ($dias as $dia) {
                $tarjetas[$dia] = $this->distribuirDia(array_values(array_filter($eventos, fn (array $s) => $s['dia'] === $dia)), $desde, $hasta);
            }
            $resultado[] = $grupo['datos'] + [
                'sesiones' => $eventos,
                'dias' => $dias,
                'desde' => $desde,
                'hasta' => $hasta,
                'tarjetas' => $tarjetas,
            ];
        }
        usort($resultado, fn (array $a, array $b) => [(int) $a['plan'], (int) $a['ciclo'], $a['seccion']]
            <=> [(int) $b['plan'], (int) $b['ciclo'], $b['seccion']]);

        return $resultado;
    }

    /** @return list<array{inicio:int, fin:int, items:array, lane:int, slots:int, top:float, alto:float}> */
    private function distribuirDia(array $sesiones, int $desde, int $hasta): array
    {
        $intervalos = [];
        foreach ($sesiones as $s) {
            $clave = $s['inicio'].':'.$s['fin'];
            $intervalos[$clave] ??= ['inicio' => $s['inicio'], 'fin' => $s['fin'], 'items' => []];
            $intervalos[$clave]['items'][] = $s;
        }
        $tarjetas = array_values($intervalos);
        usort($tarjetas, fn ($a, $b) => [$a['inicio'], $a['fin']] <=> [$b['inicio'], $b['fin']]);
        $componentes = [];
        $componente = [];
        $limite = null;
        foreach ($tarjetas as $tarjeta) {
            if ($componente && $tarjeta['inicio'] >= $limite) {
                $componentes[] = $componente;
                $componente = [];
                $limite = null;
            }
            $componente[] = $tarjeta;
            $limite = max($limite ?? $tarjeta['fin'], $tarjeta['fin']);
        }
        if ($componente) {
            $componentes[] = $componente;
        }
        $resultado = [];
        foreach ($componentes as $items) {
            $ocupadoHasta = [];
            $asignadas = [];
            foreach ($items as $tarjeta) {
                $carril = 0;
                while (isset($ocupadoHasta[$carril]) && $ocupadoHasta[$carril] > $tarjeta['inicio']) {
                    $carril++;
                }
                $ocupadoHasta[$carril] = $tarjeta['fin'];
                $tarjeta['lane'] = $carril;
                $asignadas[] = $tarjeta;
            }
            $cantidad = max(1, count($ocupadoHasta));
            foreach ($asignadas as $tarjeta) {
                $tarjeta['slots'] = $cantidad;
                $tarjeta['top'] = 100 * ($tarjeta['inicio'] - $desde) / ($hasta - $desde);
                $tarjeta['alto'] = 100 * ($tarjeta['fin'] - $tarjeta['inicio']) / ($hasta - $desde);
                $resultado[] = $tarjeta;
            }
        }

        return $resultado;
    }

    public static function minutos(string $hora): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $hora, $partes)
            || (int) $partes[1] > 23 || (int) $partes[2] > 59 || (isset($partes[3]) && (int) $partes[3] > 59)) {
            throw new InvalidArgumentException('Hora inválida: '.$hora);
        }

        return 60 * (int) $partes[1] + (int) $partes[2];
    }

    public static function hora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    public static function color(string $curso): string
    {
        return self::COLORES[hexdec(substr(hash('sha256', $curso), 0, 8)) % count(self::COLORES)];
    }
}
