<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use Illuminate\Database\ConnectionInterface;

/** Lee el orden y su versión en la transacción de captura del ensamblador. */
final class ProveedorPrioridadFicticio implements ProveedorComplementosTransaccional
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ProveedorComplementosTransaccional $base) {}

    public function conexion(): ConnectionInterface
    {
        return $this->db;
    }

    public function obtener(string $periodo, string $huella): array
    {
        $datos = $this->base->obtener($periodo, $huella);
        $escuela = $datos['escuela_id'];
        $version = $this->db->table('prueba.prioridad_config')->where('escuela', $escuela)->value('version');
        $orden = $this->db->table('prueba.prioridad_orden')->where('escuela', $escuela)->orderBy('posicion')->get();
        if ($version === null) {
            throw new \RuntimeException('No existe una versión de prioridad para la escuela ficticia.');
        }
        $datos['prioridad_fuente'] = ['escuela_id' => (string) $escuela, 'version' => (int) $version, 'orden' => $orden->map(fn ($fila) => ['docente_id' => (string) $fila->docente, 'posicion' => (int) $fila->posicion])->all()];
        // El motor solo admite docentes presentes en la solicitud del periodo.
        $docentes = $this->db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('s.id_periodo', $periodo)->where('ad.estado', 'CONFIRMADA')->pluck('ad.id_docente')->map(fn ($id) => (string) $id)->all();
        $filtrados = $orden->filter(fn ($fila) => in_array((string) $fila->docente, $docentes, true))->values();
        if ($filtrados->isNotEmpty()) {
            $datos['prioridad_docente'] = ['escuela_id' => (string) $escuela, 'politica' => 'SOFT_P01_LEXICOGRAFICA_V1', 'version' => (int) $version, 'orden' => $filtrados->map(fn ($fila, $i) => ['docente_id' => (string) $fila->docente, 'posicion' => $i + 1])->all()];
        }

        return $datos;
    }
}
