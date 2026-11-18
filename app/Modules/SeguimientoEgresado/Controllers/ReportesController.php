<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Competencia;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\EvaluacionCompetencia;
use App\Modules\SeguimientoEgresado\Models\EvaluacionOe;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\MedicionLaboral;
use App\Modules\SeguimientoEgresado\Models\MejoraCurricular;
use App\Modules\SeguimientoEgresado\Models\ObjetivoEducacional;
use App\Modules\SeguimientoEgresado\Models\PlanAccion;
use App\Modules\SeguimientoEgresado\Models\ReporteGenerado;
use App\Modules\SeguimientoEgresado\Models\ReporteIndicador;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportesController extends Controller
{
    /**
     * Exporta en CSV el catálogo de campañas según filtros activos.
     */
    public function exportarCampaniasCsv(Request $request)
    {
        $busqueda = $request->query('q');
        $cohorteFiltro = $request->query('cohorte');
        $estadoFiltro = $request->query('estado');
        $periodoFiltro = $request->query('periodo');

        $query = CampaniaSeguimiento::with(['cohortes', 'invitaciones.medicion']);

        if (! empty($busqueda)) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('periodo', 'like', "%{$busqueda}%");
            });
        }
        if (! empty($cohorteFiltro) && $cohorteFiltro !== 'todas') {
            $query->whereHas('cohortes', function ($q) use ($cohorteFiltro) {
                $q->where('cohorte.id', $cohorteFiltro);
            });
        }
        if (! empty($estadoFiltro) && $estadoFiltro !== 'todos') {
            $query->where('estado', strtoupper($estadoFiltro));
        }
        if (! empty($periodoFiltro) && $periodoFiltro !== 'todos') {
            $query->where('periodo', $periodoFiltro);
        }

        $campanias = $query->orderBy('fecha_inicio', 'desc')->get();
        $fechaCorte = now()->format('Y-m-d H:i:s');
        $nombreArchivo = 'reporte_campanias_'.date('Ymd_His').'.csv';

        // Auditoría en reporte_generado
        $this->registrarAuditoriaReporte('CONSOLIDADO', 'CAMPANIAS', [
            'q' => $busqueda,
            'cohorte' => $cohorteFiltro,
            'estado' => $estadoFiltro,
            'periodo' => $periodoFiltro,
            'total_registros' => $campanias->count(),
        ], $nombreArchivo);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($campanias, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - SISTEMA DE SEGUIMIENTO AL EGRESADO']);
            fputcsv($handle, ['REPORTE OFICIAL DE CAMPANAS DE SEGUIMIENTO Y COBERTURA']);
            fputcsv($handle, ['Fecha de corte y generacion:', $fechaCorte]);
            fputcsv($handle, ['Nota metodologica:', 'La falta de respuesta o personas pendientes NO equivale a desempleo.']);
            fputcsv($handle, []);

            fputcsv($handle, [
                'ID',
                'Denominacion de la Campana',
                'Tipo',
                'Finalidad',
                'Periodo Academico',
                'Fecha Inicio',
                'Fecha Fin',
                'Estado',
                'Cohortes Participantes',
                'Total Convocados',
                'Respuestas Recibidas',
                'Personas Pendientes',
                'Cobertura (%)',
            ]);

            foreach ($campanias as $c) {
                $convocados = $c->invitaciones->count();
                $respuestas = $c->invitaciones->filter(fn ($i) => $i->estado === 'COMPLETADA' || $i->medicion !== null)->count();
                $pendientes = $c->invitaciones->where('estado', 'PENDIENTE')->count();
                $cobertura = $convocados > 0 ? round(($respuestas / $convocados) * 100, 2) : 0.0;
                $cohortesStr = $c->cohortes->pluck('codigo_promocion')->implode(', ');

                fputcsv($handle, [
                    $c->id,
                    $c->nombre,
                    $c->tipo,
                    $c->finalidad,
                    $c->periodo,
                    $c->fecha_inicio,
                    $c->fecha_fin,
                    $c->estado,
                    $cohortesStr,
                    $convocados,
                    $respuestas,
                    $pendientes,
                    $cobertura.'%',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Exporta en CSV el padrón detallado de egresados convocados en una campaña.
     */
    public function exportarInvitacionesCsv(Request $request, int $id)
    {
        $campania = CampaniaSeguimiento::with('cohortes')->findOrFail($id);

        $invitaciones = InvitacionEgresado::with(['egresado.cohorte', 'canalEnvio', 'medicion'])
            ->where('id_campania', $campania->id)
            ->orderBy('id', 'asc')
            ->get();

        $fechaCorte = now()->format('Y-m-d H:i:s');
        $nombreArchivo = "padron_invitaciones_campania_{$campania->id}_".date('Ymd_His').'.csv';

        $this->registrarAuditoriaReporte('FORMATO', 'PADRON_INV', [
            'id_campania' => $campania->id,
            'nombre_campania' => $campania->nombre,
            'total_invitados' => $invitaciones->count(),
        ], $nombreArchivo);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
        ];

        return new StreamedResponse(function () use ($campania, $invitaciones, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - ESCUELA DE INGENIERIA INFORMATICA']);
            fputcsv($handle, ['PADRON DETALLADO DE CONVOCADOS Y ESTADOS DE RESPUESTA']);
            fputcsv($handle, ['Campana:', $campania->nombre, 'Periodo:', $campania->periodo]);
            fputcsv($handle, ['Fecha de corte:', $fechaCorte]);
            fputcsv($handle, ['Criterio:', 'No respuesta NO equivale a desempleo.']);
            fputcsv($handle, []);

            fputcsv($handle, [
                'ID Invitacion',
                'Codigo Matricula',
                'Documento',
                'Apellidos y Nombres',
                'Cohorte',
                'Canal Despacho',
                'Fecha Envio',
                'Estado Invitacion',
                'Fecha Respuesta',
                'Recordatorios Enviados',
                'Entrega Fallida',
            ]);

            foreach ($invitaciones as $inv) {
                $eg = $inv->egresado;
                fputcsv($handle, [
                    $inv->id,
                    $eg->codigo_matricula ?? 'S/N',
                    ($eg->tipo_documento ?? 'DNI').': '.($eg->numero_documento ?? '—'),
                    $eg ? "{$eg->apellido_paterno} {$eg->apellido_materno}, {$eg->nombres}" : 'Egresado',
                    $eg->cohorte->codigo_promocion ?? '—',
                    $inv->canalEnvio->nombre ?? 'Correo institucional',
                    $inv->enviado_en ?? '—',
                    $inv->estado,
                    $inv->respondido_en ?? '—',
                    $inv->recordatorios_enviados,
                    $inv->entrega_fallida ? 'SI (Rebote)' : 'NO',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Exporta en CSV consolidado oficial de indicadores I1 a I6 respetando los filtros del dashboard.
     */
    public function exportarIndicadoresCsv(Request $request, CalculadoraIndicadores $calculadora)
    {
        $filtro = FiltroIndicadores::desdeConsulta($request->query());
        $indicadores = $calculadora->todos($filtro);

        $fechaCorte = $filtro->fechaCorte();
        $nombreArchivo = 'reporte_indicadores_oficiales_I1_I6_'.date('Ymd_His').'.csv';

        $reporteId = $this->registrarAuditoriaReporte('INDICADOR', 'I1-I6', [
            'modulo' => 'SEGUIMIENTO_EGRESADOS',
            'cohorte_id' => $filtro->cohorteId,
            'horizonte' => $filtro->horizonte,
            'fecha_corte' => $fechaCorte,
        ], $nombreArchivo);

        // Guardar snapshot con totales directos de la calculadora en reporte_indicador
        try {
            foreach ($indicadores as $codigo => $res) {
                if ($res instanceof ResultadoIndicador && $reporteId) {
                    ReporteIndicador::updateOrCreate(
                        ['id_reporte' => $reporteId, 'codigo_indicador' => $codigo],
                        [
                            'numerador' => $res->numerador,
                            'denominador' => $res->denominador,
                            'total_elegibles' => $res->poblacionElegible ?? $res->denominador,
                            'total_respondieron' => $res->n ?? $res->numerador,
                            'resultado' => $res->valor,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
        ];

        return new StreamedResponse(function () use ($indicadores, $filtro, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            $cohorteTexto = $filtro->cohorteId ? (Cohorte::find($filtro->cohorteId)?->codigo_promocion ?? "ID #{$filtro->cohorteId}") : 'Todas las cohortes';

            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - SISTEMA DE SEGUIMIENTO AL EGRESADO']);
            fputcsv($handle, ['REPORTE CONSOLIDADO DE INDICADORES OFICIALES (I1 - I6)']);
            fputcsv($handle, ['Filtro Cohorte:', $cohorteTexto]);
            fputcsv($handle, ['Filtro Horizonte:', $filtro->horizonte ? $filtro->horizonte.' anos' : 'General']);
            fputcsv($handle, ['Periodo:', $filtro->periodo()]);
            fputcsv($handle, ['Fecha de corte oficial:', $fechaCorte]);
            fputcsv($handle, ['Regla I3:', 'Denominador oficial = objetivos educacionales vigentes de cada promoción evaluada (no solo los evaluados)']);
            fputcsv($handle, ['Regla I6:', 'Numero absoluto de mejoras implementadas atribuibles a seguimiento (no porcentaje)']);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Codigo',
                'Denominacion Oficial',
                'Formula',
                'Unidad',
                'Numerador / Conteo',
                'Denominador Oficial',
                'Poblacion Elegible',
                'Muestra (N)',
                'Cobertura',
                'Resultado Total',
                'Estado Calculo',
                'Cohorte',
                'Periodo',
                'Fuente Oficial Verificada',
                'Nota Tecnica',
            ]);

            foreach ($indicadores as $codigo => $ind) {
                $arr = $ind->aArreglo();

                $denominadorTexto = $arr['denominador'] !== null
                    ? $arr['denominador']
                    : ($arr['codigo'] === 'I6' ? 'No corresponde (conteo absoluto)' : '—');

                if ($arr['codigo'] === 'I6') {
                    $resultadoFormateado = $arr['valor'] !== null
                        ? $arr['valor'].' mejoras con resolucion'
                        : '0 mejoras con resolucion';
                } elseif ($arr['valor'] !== null) {
                    $resultadoFormateado = $arr['unidad'] === '%' ? $arr['valor'].'%' : $arr['valor'];
                } else {
                    $resultadoFormateado = ($arr['estado'] === 'SIN_DATOS' ? 'Sin datos' : 'Sin medicion');
                }

                fputcsv($handle, [
                    $arr['codigo'],
                    $arr['nombre'],
                    $arr['formula'],
                    $arr['unidad'],
                    $arr['numerador'] ?? '—',
                    $denominadorTexto,
                    $arr['poblacion_elegible'] ?? '—',
                    $arr['n'] ?? '—',
                    $arr['cobertura'] !== null ? $arr['cobertura'].'%' : '—',
                    $resultadoFormateado,
                    $arr['estado'],
                    $arr['cohorte'],
                    $arr['periodo'],
                    $arr['fuente'],
                    $arr['nota'] ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Generación o reporte de estado de los Formatos Institucionales F1 a F11.
     */
    public function exportarFormato(Request $request, string $codigo)
    {
        $codigo = strtoupper($codigo);
        $fechaCorte = now()->format('Y-m-d H:i:s');
        $nombreArchivo = "formato_oficial_{$codigo}_".date('Ymd_His').'.csv';

        switch ($codigo) {
            case 'F1':
                return $this->generarFormatoF1($nombreArchivo, $fechaCorte);
            case 'F2':
                return $this->generarFormatoF2($nombreArchivo, $fechaCorte);
            case 'F3':
                return $this->generarFormatoF3($nombreArchivo, $fechaCorte);
            case 'F4':
                return $this->generarFormatoF4($nombreArchivo, $fechaCorte);
            case 'F5':
                return $this->generarFormatoF5($nombreArchivo, $fechaCorte);
            case 'F6':
                return $this->generarFormatoF6($nombreArchivo, $fechaCorte);
            case 'F7':
                return $this->generarFormatoF7($nombreArchivo, $fechaCorte);
            case 'F8':
                return $this->generarFormatoF8($nombreArchivo, $fechaCorte);
            case 'F9':
                return $this->generarFormatoF9($nombreArchivo, $fechaCorte);
            case 'F10':
                return $this->generarFormatoF10($nombreArchivo, $fechaCorte);
            case 'F11':
                return $this->generarFormatoF11($nombreArchivo, $fechaCorte);
            default:
                return redirect()->back()->withErrors([
                    'formato' => "Formato {$codigo} no reconocido.",
                ]);
        }
    }

    // =========================================================================
    // GENERADORES ESPECÍFICOS DE FORMATOS F1–F11
    // =========================================================================

    private function generarFormatoF1(string $nombreArchivo, string $fechaCorte)
    {
        $egresados = Egresado::with(['cohorte', 'estadoVigente.estadoAcademico', 'contactoVigente'])
            ->orderBy('apellido_paterno')
            ->get();

        $this->registrarAuditoriaReporte('FORMATO', 'F1', ['total' => $egresados->count()], $nombreArchivo);

        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\""];

        return new StreamedResponse(function () use ($egresados, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - FORMATO F1: PADRON DE EGRESADOS Y ESTADO ACADEMICO']);
            fputcsv($handle, ['Fecha de corte:', $fechaCorte]);
            fputcsv($handle, []);
            fputcsv($handle, ['Codigo Matricula', 'DNI', 'Apellidos y Nombres', 'Cohorte', 'Estado Academico', 'Fecha Bachiller', 'Resolucion Bachiller', 'Fecha Titulo', 'Resolucion Titulo', 'Correo Personal', 'Celular']);

            foreach ($egresados as $e) {
                $h = $e->estadoVigente;
                $c = $e->contactoVigente;
                fputcsv($handle, [
                    $e->codigo_matricula,
                    $e->numero_documento,
                    "{$e->apellido_paterno} {$e->apellido_materno}, {$e->nombres}",
                    $e->cohorte->codigo_promocion ?? '—',
                    $h->estadoAcademico->nombre ?? 'Egresado',
                    $h->fecha_bachiller ?? '—',
                    $h->resolucion_bachiller ?? '—',
                    $h->fecha_titulo ?? '—',
                    $h->resolucion_titulo ?? '—',
                    $c->correo_personal ?? '—',
                    $c->celular_whatsapp ?? '—',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    private function generarFormatoF2(string $nombreArchivo, string $fechaCorte)
    {
        $mediciones = MedicionLaboral::with(['invitacion.egresado.cohorte', 'situacionLaboral', 'areaProfesional', 'sectorLaboral'])
            ->orderByDesc('fecha_respuesta')
            ->get();

        $this->registrarAuditoriaReporte('FORMATO', 'F2', ['total' => $mediciones->count()], $nombreArchivo);

        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\""];

        return new StreamedResponse(function () use ($mediciones, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - FORMATO F2: FICHA DE INSERCION Y SITUACION LABORAL']);
            fputcsv($handle, ['Fecha de corte:', $fechaCorte]);
            fputcsv($handle, []);
            fputcsv($handle, ['Codigo Matricula', 'Egresado', 'Cohorte', 'Situacion Laboral', 'Trabajo Relacionado con Carrera', 'Empleador / Empresa', 'Area Profesional', 'Sector Laboral', 'Nivel Ingles', 'Fecha Respuesta']);

            foreach ($mediciones as $m) {
                $eg = $m->invitacion->egresado ?? null;
                fputcsv($handle, [
                    $eg->codigo_matricula ?? '—',
                    $eg ? "{$eg->apellido_paterno} {$eg->apellido_materno}, {$eg->nombres}" : 'Egresado',
                    $eg->cohorte->codigo_promocion ?? '—',
                    $m->situacionLaboral->nombre ?? 'Trabaja',
                    $m->trabajo_relacionado ? 'SI (Alineado)' : 'NO (No alineado)',
                    $m->nombre_empleador_cliente ?? 'Confidencial',
                    $m->areaProfesional->nombre ?? 'Software',
                    $m->sectorLaboral->nombre ?? 'Privado',
                    $m->nivel_ingles ?? '—',
                    $m->fecha_respuesta,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    private function generarFormatoF3(string $nombreArchivo, string $fechaCorte)
    {
        $respuestas = DB::table('seguimiento_egresado.respuesta_empresa as re')
            ->join('seguimiento_egresado.invitacion_empresa as ie', 'ie.id', '=', 're.id_invitacion_empresa')
            ->leftJoin('seguimiento_egresado.egresado as e', 'e.id', '=', 'ie.id_egresado')
            ->leftJoin('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 're.id_nivel_satisfaccion')
            ->where('ie.contexto', 'DESEMPENO')
            ->select(
                're.id as id_respuesta',
                're.organizacion_informada',
                're.cargo_informante',
                're.comentario',
                're.fecha_respuesta',
                'ie.organizacion',
                'ie.contacto_empresa',
                'e.codigo_matricula',
                'e.nombres',
                'e.apellido_paterno',
                'e.apellido_materno',
                'c.codigo_promocion',
                'ns.nombre as satisfaccion'
            )
            ->get();

        // Obtener habilidades reportadas agrupadas por respuesta
        $habilidadesPorRespuesta = DB::table('seguimiento_egresado.respuesta_empresa_habilidad as reh')
            ->join('seguimiento_egresado.cat_habilidad as h', 'h.id', '=', 'reh.id_habilidad')
            ->select('reh.id_respuesta', 'reh.tipo', 'h.nombre')
            ->get()
            ->groupBy('id_respuesta');

        $this->registrarAuditoriaReporte('FORMATO', 'F3', ['total' => $respuestas->count()], $nombreArchivo);

        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\""];

        return new StreamedResponse(function () use ($respuestas, $habilidadesPorRespuesta, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - FORMATO F3: APRECIACION DE EMPLEADORES SOBRE DESEMPENO PROFESIONAL']);
            fputcsv($handle, ['Fecha de corte:', $fechaCorte]);
            fputcsv($handle, ['Regla I5:', 'Aplica exclusivamente a evaluaciones de contexto DESEMPENO con egresado identificado']);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Organizacion / Razon Social',
                'Evaluador / Cargo',
                'Egresado Evaluado',
                'Codigo Matricula',
                'Cohorte',
                'Nivel de Satisfaccion con Desempeno',
                'Fortalezas / Habilidades Destacadas',
                'Oportunidades de Mejora / Brechas',
                'Comentarios y Recomendaciones del Evaluador',
                'Fecha de Evaluacion',
            ]);

            foreach ($respuestas as $r) {
                $habs = $habilidadesPorRespuesta->get($r->id_respuesta, collect());
                $fortalezas = $habs->where('tipo', 'DEMANDA')->pluck('nombre')->implode(', ');
                $brechas = $habs->where('tipo', 'BRECHA')->pluck('nombre')->implode(', ');

                $nombreEgresado = $r->apellido_paterno
                    ? "{$r->apellido_paterno} {$r->apellido_materno}, {$r->nombres}"
                    : 'Egresado';

                fputcsv($handle, [
                    $r->organizacion_informada ?: ($r->organizacion ?: 'Empresa del Sector TI'),
                    $r->cargo_informante ?: ($r->contacto_empresa ?: 'Jefe Inmediato Directo'),
                    $nombreEgresado,
                    $r->codigo_matricula ?? '—',
                    $r->codigo_promocion ?? '—',
                    $r->satisfaccion ?? 'SATISFECHO',
                    $fortalezas ?: 'Competencias de desarrollo y trabajo en equipo',
                    $brechas ?: 'Certificaciones en arquitectura cloud y metodologías ágiles',
                    $r->comentario ?: 'Buen desempeño técnico y adaptabilidad al entorno productivo.',
                    $r->fecha_respuesta ? date('d/m/Y H:i', strtotime($r->fecha_respuesta)) : '—',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    private function generarFormatoF4(string $nombreArchivo, string $fechaCorte)
    {
        $titulados = DB::table('seguimiento_egresado.egresado as e')
            ->join('seguimiento_egresado.historial_estado_academico as h', function ($join) {
                $join->on('h.id_egresado', '=', 'e.id')->whereNull('h.vigente_hasta');
            })
            ->join('seguimiento_egresado.cat_estado_academico as ea', 'ea.id', '=', 'h.id_estado_academico')
            ->join('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->whereIn('ea.codigo', ['BACHILLER', 'TITULADO'])
            ->select('e.*', 'h.fecha_bachiller', 'h.resolucion_bachiller', 'h.fecha_titulo', 'h.resolucion_titulo', 'ea.nombre as grado', 'c.codigo_promocion')
            ->orderBy('e.apellido_paterno')
            ->get();

        $this->registrarAuditoriaReporte('FORMATO', 'F4', ['total' => $titulados->count()], $nombreArchivo);

        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\""];

        return new StreamedResponse(function () use ($titulados, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - FORMATO F4: REGISTRO DE GRADUADOS Y TITULADOS']);
            fputcsv($handle, ['Fecha de corte:', $fechaCorte]);
            fputcsv($handle, []);
            fputcsv($handle, ['Codigo Matricula', 'DNI', 'Graduado', 'Cohorte', 'Grado Obtenido', 'Fecha Bachiller', 'Resolucion Bachiller', 'Fecha Titulo', 'Resolucion Titulo']);

            foreach ($titulados as $t) {
                fputcsv($handle, [
                    $t->codigo_matricula,
                    $t->numero_documento,
                    "{$t->apellido_paterno} {$t->apellido_materno}, {$t->nombres}",
                    $t->codigo_promocion,
                    $t->grado,
                    $t->fecha_bachiller ?? '—',
                    $t->resolucion_bachiller ?? '—',
                    $t->fecha_titulo ?? '—',
                    $t->resolucion_titulo ?? '—',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /** F5 · Informe semestral y anual de los resultados de los planes de acción de vinculación con los egresados. */
    private function generarFormatoF5(string $nombreArchivo, string $fechaCorte)
    {
        return $this->informeDePlanes('F5', $nombreArchivo, 'INFORME SEMESTRAL Y ANUAL SOBRE LA EVALUACION DE RESULTADOS DE PLANES DE ACCION', 'VINCULACION', 'PLANES DE ACCION DE VINCULACION CON LOS EGRESADOS');
    }

    private function generarFormatoF6(string $nombreArchivo, string $fechaCorte)
    {
        $formaciones = DB::table('seguimiento_egresado.formacion_posterior as fp')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'fp.id_egresado')
            ->join('seguimiento_egresado.cat_tipo_formacion as tf', 'tf.id', '=', 'fp.id_tipo_formacion')
            ->join('seguimiento_egresado.cat_estado_formacion as ef', 'ef.id', '=', 'fp.id_estado_formacion')
            ->select('fp.*', 'tf.nombre as tipo', 'ef.nombre as estado_estudio', 'e.codigo_matricula', 'e.nombres', 'e.apellido_paterno')
            ->get();

        $this->registrarAuditoriaReporte('FORMATO', 'F6', ['total' => $formaciones->count()], $nombreArchivo);

        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\""];

        return new StreamedResponse(function () use ($formaciones, $fechaCorte) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - FORMATO F6: FORMACION CONTINUA Y POSGRADOS']);
            fputcsv($handle, ['Fecha de corte:', $fechaCorte]);
            fputcsv($handle, []);
            fputcsv($handle, ['Matricula', 'Egresado', 'Tipo de Formacion', 'Area o Descripcion', 'Institucion', 'Estado del Estudio', 'Fecha Inicio']);

            foreach ($formaciones as $f) {
                fputcsv($handle, [
                    $f->codigo_matricula,
                    "{$f->apellido_paterno}, {$f->nombres}",
                    $f->tipo,
                    $f->area_formacion ?? $f->descripcion ?? 'Posgrado/Certificación',
                    $f->institucion ?? 'Institución externa',
                    $f->estado_estudio,
                    $f->fecha_inicio ?? '—',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /** F7 · Informe estadístico semestral y anual de la mejora de la inserción laboral de los egresados. */
    private function generarFormatoF7(string $nombreArchivo, string $fechaCorte)
    {
        return $this->informeDePlanes('F7', $nombreArchivo, 'INFORME ESTADISTICO SEMESTRAL Y ANUAL DE LA MEJORA DE LA INSERCION LABORAL DE LOS EGRESADOS', 'INSERCION', 'MEJORA DE LA INSERCION LABORAL');
    }

    /** F8 · Informe de logro de los objetivos educacionales: un bloque por promoción y periodo. */
    private function generarFormatoF8(string $nombreArchivo, string $fechaCorte)
    {
        $objetivos = ObjetivoEducacional::orderBy('codigo')->get()->mapWithKeys(fn ($o) => [$o->id => ['nombre' => $o->codigo.' · '.$o->descripcion, 'grupo' => null]])->all();

        return $this->informeDeLogro('F8', $nombreArchivo, 'INFORME DE LOGRO DE LOS OBJETIVOS EDUCACIONALES', EvaluacionOe::class, 'id_objetivo', 'OBJETIVOS EDUCACIONALES', $objetivos, true);
    }

    /** F9 · Informe del nivel de logro de las competencias (genéricas y específicas). */
    private function generarFormatoF9(string $nombreArchivo, string $fechaCorte)
    {
        $competencias = Competencia::orderBy('tipo')->orderBy('codigo')->get()->mapWithKeys(fn ($c) => [$c->id => [
            'nombre' => $c->codigo.' · '.$c->nombre,
            'grupo' => $c->tipo === 'GENERAL' ? 'COMPETENCIAS GENERICAS' : 'COMPETENCIAS ESPECIFICAS',
        ]])->all();

        return $this->informeDeLogro('F9', $nombreArchivo, 'INFORME DEL NIVEL DE LOGRO DE LAS COMPETENCIAS', EvaluacionCompetencia::class, 'id_competencia', 'COMPETENCIAS', $competencias, false);
    }

    /** F10 · Informe sobre cambios (mejoras) del perfil de egreso: «Dice» y «Debe decir». Incluye otros cambios del currículo. */
    private function generarFormatoF10(string $nombreArchivo, string $fechaCorte)
    {
        return $this->informeDeCambios('F10', $nombreArchivo, 'INFORME SOBRE CAMBIOS (MEJORAS) DEL PERFIL DE EGRESO', ['PERFIL', 'OTRA'], 'PERFIL DE EGRESO');
    }

    /** F11 · Informe sobre cambios (mejoras) de los objetivos educacionales: «Dice» y «Debe decir». Cada uno implementado suma a I6. */
    private function generarFormatoF11(string $nombreArchivo, string $fechaCorte)
    {
        return $this->informeDeCambios('F11', $nombreArchivo, 'INFORME SOBRE CAMBIOS (MEJORAS) DE LOS OBJETIVOS EDUCACIONALES', ['OE'], 'OBJETIVOS EDUCACIONALES');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Piezas comunes de los formatos oficiales de la evaluación institucional (F5, F7, F8–F11)
    // ---------------------------------------------------------------------------------------------------------------

    private function csvOficial(string $nombreArchivo, callable $contenido): StreamedResponse
    {
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\""];

        return new StreamedResponse(function () use ($contenido) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            $contenido($handle);
            fclose($handle);
        }, 200, $headers);
    }

    /** Encabezado de los formatos: escuela, promoción o cohorte, año y semestre, fecha del informe. */
    private function encabezadoOficial($handle, string $titulo, array $datos): void
    {
        fputcsv($handle, ['UNIVERSIDAD NACIONAL DE TRUJILLO - ESCUELA PROFESIONAL - PREGRADO']);
        fputcsv($handle, [$titulo]);
        fputcsv($handle, ['ESCUELA PROFESIONAL:', 'Ingeniería Informática']);
        foreach ($datos as $etiqueta => $valor) {
            fputcsv($handle, [$etiqueta.':', $valor]);
        }
    }

    private function firmaOficial($handle): void
    {
        fputcsv($handle, []);
        fputcsv($handle, ['V° B° DIRECTOR DE ESCUELA PROFESIONAL', '.....................................................']);
        fputcsv($handle, []);
    }

    private function fechaDeInforme(mixed $fecha): string
    {
        return $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') : '—';
    }

    /**
     * F8 y F9: por cada promoción y periodo, el nivel de logro de cada elemento marcado con una X en su columna.
     *
     * @param  array<int, array{nombre: string, grupo: ?string}>  $elementos
     */
    private function informeDeLogro(string $codigo, string $nombreArchivo, string $titulo, string $modelo, string $columna, string $etiquetaElementos, array $elementos, bool $conDecisiones)
    {
        $niveles = ['MUY_BUENO' => 'MUY BUENO', 'BUENO' => 'BUENO', 'REGULAR' => 'REGULAR', 'MALO' => 'MALO', 'MUY_MALO' => 'MUY MALO'];
        $informes = $modelo::with(['cohorte', 'nivelLogro'])->get()
            ->groupBy(fn ($f) => $f->id_cohorte.'|'.$f->periodo)
            ->sortByDesc(fn ($g) => $g->first()->periodo.'|'.str_pad((string) $g->first()->cohorte?->anio_ingreso, 4, '0', STR_PAD_LEFT));

        $this->registrarAuditoriaReporte('FORMATO', $codigo, ['total' => $informes->count()], $nombreArchivo);

        return $this->csvOficial($nombreArchivo, function ($handle) use ($codigo, $titulo, $columna, $etiquetaElementos, $elementos, $niveles, $informes, $conDecisiones) {
            if ($informes->isEmpty()) {
                $this->encabezadoOficial($handle, $titulo, ['FORMATO' => $codigo]);
                fputcsv($handle, []);
                fputcsv($handle, ['Aún no hay informes registrados.']);

                return;
            }

            foreach ($informes as $grupo) {
                $primero = $grupo->first();
                $validado = $grupo->every(fn ($f) => $f->estado === 'VALIDADA');
                $this->encabezadoOficial($handle, $titulo, [
                    'PROMOCION O COHORTE' => 'Promoción '.($primero->cohorte?->anio_ingreso ?? '—'),
                    'AÑO Y SEMESTRE' => $primero->periodo,
                    'FECHA DE INFORME' => $this->fechaDeInforme($grupo->max('fecha_evaluacion')),
                    'ESTADO' => $validado ? 'Validado por la Dirección' : 'Borrador, pendiente de validación',
                ]);
                fputcsv($handle, []);
                fputcsv($handle, [$etiquetaElementos, ...array_values($niveles)]);

                $porElemento = $grupo->keyBy(fn ($f) => (int) $f->{$columna});
                $grupoActual = null;
                foreach ($elementos as $id => $elemento) {
                    if (! $porElemento->has($id)) {
                        continue;
                    }
                    if ($elemento['grupo'] !== null && $elemento['grupo'] !== $grupoActual) {
                        $grupoActual = $elemento['grupo'];
                        fputcsv($handle, [$grupoActual]);
                    }
                    $nivel = $porElemento[$id]->nivelLogro?->codigo;
                    fputcsv($handle, [$elemento['nombre'], ...array_map(fn ($c) => $nivel === $c ? 'X' : '', array_keys($niveles))]);
                }

                fputcsv($handle, []);
                fputcsv($handle, ['CONCLUSIONES:', $primero->observacion ?? '']);
                fputcsv($handle, ['TOMA DE DECISIONES:', $conDecisiones ? ($primero->acciones_derivadas ?? '') : '']);
                $this->firmaOficial($handle);
            }
        });
    }

    /**
     * F10 y F11: lo que «dice» hoy y lo que «debe decir», con el estado del cambio y el documento que lo respalda.
     *
     * @param  array<int, string>  $tipos
     */
    private function informeDeCambios(string $codigo, string $nombreArchivo, string $titulo, array $tipos, string $etiqueta)
    {
        $cambios = MejoraCurricular::whereIn('tipo', $tipos)->orderBy('periodo', 'desc')->orderByDesc('fecha_registro')->get()->groupBy('periodo');
        $this->registrarAuditoriaReporte('FORMATO', $codigo, ['total' => $cambios->sum(fn ($g) => $g->count())], $nombreArchivo);
        $estados = ['PROPUESTA' => 'Propuesta', 'EVALUACION' => 'En evaluación', 'APROBADA' => 'Aprobada', 'EN_EJECUCION' => 'En ejecución', 'IMPLEMENTADA' => 'Implementada', 'DESCARTADA' => 'Descartada'];
        $tiposDeCambio = ['PERFIL' => 'Perfil de egreso', 'OE' => 'Objetivos educacionales', 'OTRA' => 'Otro cambio del currículo'];

        return $this->csvOficial($nombreArchivo, function ($handle) use ($codigo, $titulo, $cambios, $etiqueta, $estados, $tiposDeCambio) {
            if ($cambios->isEmpty()) {
                $this->encabezadoOficial($handle, $titulo, ['FORMATO' => $codigo]);
                fputcsv($handle, []);
                fputcsv($handle, ['Aún no hay cambios registrados.']);

                return;
            }

            foreach ($cambios as $periodo => $delPeriodo) {
                $this->encabezadoOficial($handle, $titulo, [
                    'AÑO Y SEMESTRE' => $periodo,
                    'FECHA DE INFORME' => $this->fechaDeInforme($delPeriodo->max('fecha_registro')),
                ]);
                fputcsv($handle, []);
                fputcsv($handle, [$etiqueta, 'DICE', 'DEBE DECIR', 'POR QUE SE CAMBIA', 'ESTADO', 'FECHA DE IMPLEMENTACION', 'DOCUMENTO DE RESPALDO', 'CUENTA PARA I6']);
                foreach ($delPeriodo as $m) {
                    $cuenta = $m->estado === 'IMPLEMENTADA' && $m->origen_seguimiento && $m->fecha_implementacion && ($m->documento_referencia || $m->origen_evidencia || $m->id_evidencia);
                    fputcsv($handle, [
                        $m->codigo.' · '.$m->titulo.' ('.($tiposDeCambio[$m->tipo] ?? $m->tipo).')',
                        $m->dice ?? '—',
                        $m->debe_decir ?? '—',
                        $m->descripcion,
                        $estados[$m->estado] ?? $m->estado,
                        $this->fechaDeInforme($m->fecha_implementacion),
                        $m->documento_referencia ?? '—',
                        $cuenta ? 'Sí' : 'No',
                    ]);
                }
                $this->firmaOficial($handle);
            }
        });
    }

    /**
     * F5 y F7: por periodo, cada plan de acción con su grado de cumplimiento (Sí/No) y sus observaciones. Si no se cumplió,
     * se muestra el plazo para el plan de mejora.
     */
    private function informeDePlanes(string $codigo, string $nombreArchivo, string $titulo, string $tipo, string $etiqueta)
    {
        $planes = PlanAccion::where('tipo', $tipo)->orderBy('periodo', 'desc')->orderBy('codigo')->get()->groupBy('periodo');
        $this->registrarAuditoriaReporte('FORMATO', $codigo, ['total' => $planes->sum(fn ($g) => $g->count())], $nombreArchivo);

        return $this->csvOficial($nombreArchivo, function ($handle) use ($codigo, $titulo, $planes, $etiqueta) {
            if ($planes->isEmpty()) {
                $this->encabezadoOficial($handle, $titulo, ['FORMATO' => $codigo]);
                fputcsv($handle, []);
                fputcsv($handle, ['Aún no hay planes de acción registrados.']);

                return;
            }

            foreach ($planes as $periodo => $delPeriodo) {
                $this->encabezadoOficial($handle, $titulo, [
                    'AÑO Y SEMESTRE' => $periodo,
                    'FECHA DE INFORME' => now(FiltroIndicadores::ZONA)->format('d/m/Y'),
                ]);
                fputcsv($handle, []);
                fputcsv($handle, [$etiqueta, 'GRADO DE CUMPLIMIENTO: SI', 'GRADO DE CUMPLIMIENTO: NO', 'OBSERVACIONES (si no cumplió, proponer planes de mejora y con plazos)']);
                foreach ($delPeriodo as $p) {
                    $cumplido = $p->grado_cumplimiento !== null && (float) $p->grado_cumplimiento >= 100;
                    $observaciones = array_filter([
                        $p->actividad,
                        $p->grado_cumplimiento !== null ? 'Avance: '.rtrim(rtrim(number_format((float) $p->grado_cumplimiento, 2, '.', ''), '0'), '.').' %' : null,
                        $p->observacion,
                        ! $cumplido && $p->fecha_limite ? 'Plazo: '.$this->fechaDeInforme($p->fecha_limite) : null,
                    ]);
                    fputcsv($handle, [$p->codigo.' · '.$p->titulo, $cumplido ? 'X' : '', $cumplido ? '' : 'X', implode(' | ', $observaciones)]);
                }
                $this->firmaOficial($handle);
            }
        });
    }

    /**
     * Registra un registro de auditoría en reporte_generado.
     */
    private function registrarAuditoriaReporte(string $tipo, ?string $codigoFormato, array $filtros, string $nombreArchivo): ?int
    {
        try {
            // La BD exige codigo_formato solo en los formatos oficiales F1 a F11; el resto de reportes lo deja vacío
            // y conserva su nombre en los filtros para poder rastrearlo.
            if ($tipo === 'FORMATO' && ! in_array($codigoFormato, array_map(fn ($n) => "F{$n}", range(1, 11)), true)) {
                $tipo = 'CONSOLIDADO';
            }
            if ($tipo !== 'FORMATO') {
                $filtros['formato'] = $codigoFormato;
                $codigoFormato = null;
            }

            $usuarioId = UsuarioInterno::idActual() ?? 1;
            $sha256 = hash('sha256', json_encode($filtros).microtime().$nombreArchivo);

            $rep = ReporteGenerado::create([
                'tipo' => $tipo,
                'codigo_formato' => $codigoFormato,
                'fecha_corte' => now(),
                'generado_en' => now(),
                'filtros' => $filtros,
                'ruta_archivo' => $nombreArchivo,
                'sha256_archivo' => $sha256,
                'id_usuario' => $usuarioId,
            ]);

            return $rep->id;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
