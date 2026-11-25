<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use Illuminate\Database\ConnectionInterface;

/** Solo se instancia en pruebas contra tablas ficticias aisladas. */
final class ProveedorComplementosPostgresFicticio implements ProveedorComplementosTransaccional
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ?int $tiempoLimite = null) {}

    public function conexion(): ConnectionInterface
    {
        return $this->db;
    }

    public function obtener(string $periodo, string $huella): array
    {
        $meta = $this->db->table('prueba.meta')->where('periodo', $periodo)->first();
        if (! $meta) {
            return [];
        }
        $poblacion = $actividades = $patrones = $particiones = $nombres = [];
        foreach ($this->db->table('prueba.docente_nombre')->get() as $fila) {
            $nombres[(string) $fila->id_docente] = $fila->nombre;
        }
        foreach ($this->db->table('prueba.padron')->orderBy('id_seccion')->orderBy('estudiante_id')->get() as $fila) {
            $poblacion[(string) $fila->id_seccion][] = ['estudiante_id' => $fila->estudiante_id, 'preferencia' => $fila->preferencia];
        }
        foreach ($this->db->table('prueba.participante_actividad')->orderBy('id_actividad')->orderBy('estudiante_id')->get() as $fila) {
            $actividades[(string) $fila->id_actividad][] = $fila->estudiante_id;
        }
        foreach ($this->db->table('prueba.patron')->orderBy('id_actividad')->orderBy('orden')->get() as $fila) {
            $patrones[(string) $fila->id_actividad][] = (int) $fila->duracion_minutos;
        }
        foreach ($this->db->table('prueba.particion')->get() as $fila) {
            $particiones[(string) $fila->id_actividad] = $fila->subgrupo;
        }
        $franjas = [];
        foreach ($this->db->table('gestion_horarios.disponibilidad_docente as d')->join('gestion_horarios.bloque_horario as b', 'b.id_bloque', '=', 'd.id_bloque')->where('d.id_periodo', $periodo)->orderBy('d.id_docente')->orderBy('d.dia_semana')->orderBy('b.hora_inicio')->orderBy('b.hora_fin')->orderBy('d.id_disponibilidad')->get(['d.id_docente', 'd.dia_semana', 'b.hora_inicio', 'b.hora_fin']) as $fila) {
            $id = (string) $fila->id_docente;
            $franjas[$id][] = ['dia' => $fila->dia_semana, 'inicio' => (int) substr($fila->hora_inicio, 0, 2), 'fin' => (int) substr($fila->hora_fin, 0, 2)];
        }
        $disponibilidad = [];
        foreach ($franjas as $id => $ventanas) {
            $disponibilidad[] = ['docente_id' => (string) $id, 'estado' => 'DECLARADA', 'franjas' => $ventanas];
        }
        $sesiones = [];
        foreach ($this->db->table('prueba.fisica')->orderBy('id')->get() as $fila) {
            $participantes = [];
            foreach ($this->db->table('prueba.fisica_participante')->where('id_fisica', $fila->id)->orderBy('id_seccion')->orderBy('estudiante_id')->get() as $p) {
                $sid = (string) $p->id_seccion;
                $participantes[$sid]['seccion_id'] = $sid;
                $participantes[$sid]['estudiantes'][] = $p->estudiante_id;
            }
            $sesiones[] = ['id' => $fila->id, 'actividad_id' => (string) $fila->id_actividad, 'tipo' => $fila->tipo, 'duracion_minutos' => (int) $fila->duracion_minutos, 'docente_id' => (string) $fila->id_docente, 'ambiente_id' => (string) $fila->id_ambiente, 'participantes' => array_values($participantes)];
        }
        $sabados = [];
        foreach ($this->db->table('prueba.sabado')->orderBy('ciclo')->get() as $fila) {
            $sabados[(string) $fila->ciclo] = (bool) $fila->permitido;
        }

        return ['contrato' => 'COMPLEMENTOS_ACADEMICOS_FICTICIOS_V1', 'origen' => 'PRUEBA_FICTICIA', 'periodo' => $periodo, 'huella_instantanea' => $huella, 'fuente' => $meta->fuente, 'version' => $meta->version, 'versiones' => array_fill_keys(['poblacion', 'participantes', 'particiones', 'sesiones', 'calendario', 'disponibilidad', 'restricciones', 'patrones'], $meta->version), 'consistencia' => 'MISMA_TRANSACCION_POSTGRESQL', 'escuela_id' => $meta->escuela_id, 'politica' => $meta->politica, 'tiempo_limite' => $this->tiempoLimite ?? (int) $meta->tiempo_limite,
            'poblacion' => $poblacion, 'participantes_actividad' => $actividades, 'patrones' => $patrones, 'particiones' => $particiones, 'sesiones_fisicas' => $sesiones, 'disponibilidad' => $disponibilidad, 'nombres_docentes' => $nombres, 'restricciones' => ['estado' => $meta->restricciones_estado],
            'calendario' => ['calendario' => ['dias' => $this->db->table('prueba.dia')->orderBy('orden')->pluck('dia')->all(), 'hora_inicial' => (int) $meta->hora_inicial, 'hora_final' => (int) $meta->hora_final, 'receso' => ['inicio' => (int) $meta->receso_inicio, 'fin' => (int) $meta->receso_fin], 'sabados_por_ciclo' => $sabados, 'bloques_hora_base' => $this->db->table('prueba.bloque_base')->orderBy('inicio')->get()->map(fn ($b) => ['inicio' => (int) $b->inicio, 'fin' => (int) $b->fin])->all()], 'capacidad_subgrupo_autorizada' => (int) $meta->limite_lab, 'politica_laboratorios' => $meta->politica_laboratorios, 'politica_grupos_laboratorio' => $meta->politica_grupos_laboratorio]];
    }
}
