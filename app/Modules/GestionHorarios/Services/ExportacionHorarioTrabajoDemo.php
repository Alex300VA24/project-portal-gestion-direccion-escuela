<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Adaptación de la revisión congelada al exportador institucional existente. Solo QA. */
final class ExportacionHorarioTrabajoDemo
{
    public function consultar(User $usuario, int $version, array $filtros = []): array
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        $id = HorarioUnicoDemo::id();
        abort_unless($id !== null, 404);
        $revision = (new HorariosVersionadosDemo)->leer($usuario, $id, $version);
        $datos = $revision['datos'];
        $nombres = DB::table('core.docente as d')->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
            ->where('p.tipo_documento', 'QA')->where('d.codigo_docente', 'like', 'QA-%')
            ->selectRaw("d.codigo_docente, trim(concat_ws(' ', p.nombres, p.apellido_paterno, p.apellido_materno)) as nombre")
            ->pluck('nombre', 'codigo_docente')->all();
        $ambientes = DB::table('core.ambiente')->pluck('nombre', 'codigo')->all();
        $adaptado = self::adaptar($datos, $nombres, $ambientes);
        $adaptado = self::filtrar($datos, $adaptado, $filtros);
        $vista = $filtros['vista'] ?? 'ciclo';
        $titulo = match ($vista) {
            'docente' => 'DOCENTE · '.($nombres[$filtros['docente'] ?? ''] ?? 'Docente'),
            'ambiente' => 'AMBIENTE · '.($ambientes[$filtros['ambiente'] ?? ''] ?? ($filtros['ambiente'] ?? '')),
            default => '',
        };
        $docentes = collect($adaptado['docentes'])->map(fn ($lista) => collect(array_map(fn ($nombre) => (object) ['nombres'=>$nombre, 'apellido_paterno'=>null, 'apellido_materno'=>null], $lista)));
        return [
            'sesiones'=>collect($adaptado['sesiones']), 'docentes'=>$docentes,
            'bloquesPeriodo'=>collect($adaptado['bloques']), 'alcanceExportacion'=>$titulo, 'exportacionAcademicaQa'=>true,
            'periodoSeleccionado'=>(object) ['codigo'=>'2026-II'],
            'seleccion'=>(object) ['estado'=>'BORRADOR DE DEMOSTRACIÓN · V'.$version],
        ];
    }

    public static function filtrar(array $datos, array $adaptado, array $filtros): array
    {
        $vista = $filtros['vista'] ?? 'ciclo';
        if (!in_array($vista, ConsultaSemanalDemo::VISTAS, true)) { throw new InvalidArgumentException('Consulta desconocida.'); }
        $secciones = array_column($datos['secciones'], null, 'id_seccion');
        $permitidas = ConsultaSemanalDemo::filtrar($datos['actividades'], $secciones, $datos['sesiones'], $vista,
            (string)($filtros['ciclo'] ?? ''), (string)($filtros['seccion'] ?? ''), (string)($filtros['docente'] ?? ''),
            (string)($filtros['ambiente'] ?? ''), (string)($filtros['curso'] ?? ''));
        $ids = array_fill_keys(array_column($permitidas, 'id'), true);
        $adaptado['sesiones'] = array_values(array_filter($adaptado['sesiones'], fn ($s) => isset($ids[$s->id_actividad])));
        $adaptado['docentes'] = array_intersect_key($adaptado['docentes'], $ids);
        return $adaptado;
    }

    /** Las asignaciones y franjas proceden de la revisión, no de las actividades vigentes. */
    public static function adaptar(array $datos, array $nombres, array $ambientes): array
    {
        $secciones = array_column($datos['secciones'], null, 'id_seccion');
        $actividades = array_column($datos['actividades'], null, 'id');
        $sesiones = $docentes = $bloques = [];
        foreach ($datos['sesiones'] as $id => $s) {
            $a = $actividades[$id] ?? throw new InvalidArgumentException('Sesión sin actividad en la revisión.');
            $seccion = $secciones[$a['seccion_id']] ?? throw new InvalidArgumentException('Actividad sin sección en la revisión.');
            $inicio = $s['inicio']; $fin = $s['fin'];
            if (!is_int($inicio) || !is_int($fin) || $inicio < 0 || $fin >= 1440 || $fin - $inicio !== (int)$a['duracion'] || $fin <= $inicio || !in_array($s['dia'], HorariosVersionadosDemo::DIAS, true)) {
                throw new InvalidArgumentException('Franja inválida en la revisión.');
            }
            $codigoAmbiente = $s['ambiente'] ?? '';
            $sesiones[] = (object) [
                'id_detalle'=>(string)$id, 'id_actividad'=>(int)$id, 'id_plan'=>$datos['plan'],
                'plan_nombre'=>'Plan de demostración', 'facultad_nombre'=>'Facultad de Ciencias Físicas y Matemáticas',
                'escuela_nombre'=>'Escuela Profesional de Informática',
                'ciclo'=>$seccion['ciclo'], 'seccion'=>$seccion['seccion'],
                'curso_codigo'=>$seccion['curso'], 'curso_nombre'=>$seccion['nombre'], 'tipo_actividad'=>$a['tipo'],
                'grupo_actividad'=>$a['grupo'] ?? '',
                'dia_semana'=>$s['dia'], 'hora_inicio'=>HorariosVersionadosDemo::hora($inicio), 'hora_fin'=>HorariosVersionadosDemo::hora($fin),
                'ambiente'=>$ambientes[$codigoAmbiente] ?? ($codigoAmbiente ?: 'Sin ambiente registrado'),
            ];
            $docentes[$id] = array_values(array_unique(array_map(fn ($d) => trim($nombres[$d['codigo_docente']] ?? '') ?: 'Docente sin nombre conciliado', $a['docentes'])));
        }
        foreach ($datos['bloques'] as [$inicio, $fin]) {
            $bloques[] = (object) ['hora_inicio'=>HorariosVersionadosDemo::hora($inicio), 'hora_fin'=>HorariosVersionadosDemo::hora($fin)];
        }
        return compact('sesiones','docentes','bloques');
    }
}
