<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\ConsultaAsignacionDocenteRepository;
use App\Modules\GestionHorarios\Repositories\ConsultaDisponibilidadDocenteRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use Illuminate\Database\ConnectionInterface;

final class ConsultaDisponibilidadDocente
{
    private const DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo, ?int $docente, string $buscar): array
    {
        $periodos = (new OfertaAcademicaRepository($this->db))->periodos();
        $todos = (new ConsultaAsignacionDocenteRepository($this->db))->docentes('');
        $docentes = $buscar === '' ? $todos : (new ConsultaAsignacionDocenteRepository($this->db))->docentes($buscar);
        $docenteValido = $docente !== null && $todos->contains('id_docente', $docente);
        $periodoValido = $periodo !== null && $periodos->contains('id_periodo', $periodo);
        $resultado = ['periodos' => $periodos, 'docentes' => $docentes, 'periodoValido' => $periodoValido, 'docenteValido' => $docenteValido, 'declaracion' => null, 'bloques' => collect(), 'matriz' => [], 'actividades' => collect(), 'avisos' => [], 'dias' => self::DIAS];
        if (! $periodoValido || ! $docenteValido) {
            return $resultado;
        }
        $repo = new ConsultaDisponibilidadDocenteRepository($this->db);
        $declaraciones = $repo->declaraciones($periodo, $docente);
        $declaracion = $declaraciones->count() === 1 ? $declaraciones->first() : null;
        $bloques = $repo->bloques($periodo);
        $registros = $repo->registros($periodo, $docente);
        $avisos = [];
        if ($declaraciones->count() > 1) {
            $avisos[] = 'Hay declaraciones duplicadas para el docente y periodo; revise los registros.';
        }
        if ($declaracion && (! in_array($declaracion->estado, ['PENDIENTE', 'PRESENTADA', 'NO_PRESENTADA'], true) || ! in_array($declaracion->tratamiento_regular, ['ESTRICTA', 'FLEXIBLE'], true))) {
            $avisos[] = 'La declaración tiene un estado o tratamiento no reconocido.';
        }
        if ($declaracion && $declaracion->estado === 'PRESENTADA' && ! $declaracion->presentado_en) {
            $avisos[] = 'La declaración presentada no tiene fecha de presentación.';
        }
        foreach ($bloques as $bloque) {
            if (! $bloque->hora_inicio || ! $bloque->hora_fin || $bloque->hora_inicio >= $bloque->hora_fin) {
                $avisos[] = 'La configuración del periodo contiene bloques horarios inválidos.';
            }
        }
        $matriz = [];
        $ventanas = [];
        foreach ($registros as $registro) {
            if (! in_array($registro->dia_semana, self::DIAS, true) || (int) $registro->periodo_bloque !== $periodo || ! $registro->hora_inicio || ! $registro->hora_fin || $registro->hora_inicio >= $registro->hora_fin) {
                $avisos[] = 'Hay bloques registrados con día, horario o periodo inconsistente.';

                continue;
            }
            if ($registro->id_declaracion !== null && (! $declaracion || (int) $registro->id_declaracion !== (int) $declaracion->id_declaracion)) {
                $avisos[] = 'Hay bloques vinculados a una declaración distinta o inexistente.';
            }
            if ($registro->id_declaracion === null) {
                $avisos[] = 'Hay bloques sin vínculo explícito a una declaración.';
            }
            $clave = $registro->id_bloque.':'.$registro->dia_semana;
            $matriz[$clave] = ($matriz[$clave] ?? 0) + 1;
            $ventanas[$registro->dia_semana][] = [$registro->hora_inicio, $registro->hora_fin];
        }
        if (collect($matriz)->contains(fn ($cantidad) => $cantidad > 1)) {
            $avisos[] = 'Hay bloques duplicados para el mismo día y horario.';
        }
        if ($registros->isNotEmpty() && (! $declaracion || $declaracion->estado !== 'PRESENTADA')) {
            $avisos[] = 'Hay bloques registrados sin una declaración presentada; su aplicación requiere revisión.';
        }
        $actividades = $repo->actividades($periodo, $docente);
        foreach ($actividades as $actividad) {
            $actividad->observacion_preliminar = '';
            if (! in_array($actividad->dia_semana, self::DIAS, true) || ! $actividad->hora_inicio || ! $actividad->hora_fin || $actividad->hora_inicio >= $actividad->hora_fin) {
                $actividad->observacion_preliminar = 'Datos de día u horario incompletos o inválidos.';
            } elseif ($declaracion && $declaracion->estado === 'PRESENTADA' && $registros->isNotEmpty() && ! $avisos && in_array($actividad->estado, ['PROPUESTA', 'CONFIRMADA'], true) && ! $this->cubierta($ventanas[$actividad->dia_semana] ?? [], $actividad->hora_inicio, $actividad->hora_fin)) {
                $actividad->observacion_preliminar = 'Aparentemente fuera de los bloques registrados; requiere revisión.';
            }
        }
        $resultado['declaracion'] = $declaracion;
        $resultado['declaracionesDuplicadas'] = $declaraciones->count() > 1;
        $resultado['bloques'] = $bloques;
        $resultado['matriz'] = $matriz;
        $resultado['actividades'] = $actividades;
        $resultado['avisos'] = array_values(array_unique($avisos));
        $resultado['totalRegistros'] = $registros->count();

        return $resultado;
    }

    /** Comprueba únicamente cobertura de intervalos registrados, no reglas del motor. */
    private function cubierta(array $ventanas, string $inicio, string $fin): bool
    {
        sort($ventanas);
        $hasta = $inicio;
        foreach ($ventanas as [$desde, $termina]) {
            if ($desde > $hasta) {
                break;
            }
            if ($termina > $hasta) {
                $hasta = $termina;
            }
            if ($hasta >= $fin) {
                return true;
            }
        }

        return false;
    }
}
