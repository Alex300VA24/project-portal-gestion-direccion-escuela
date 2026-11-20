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
use App\Modules\SeguimientoEgresado\Models\MejoraCurricular;
use App\Modules\SeguimientoEgresado\Models\ObjetivoEducacional;
use App\Modules\SeguimientoEgresado\Models\PlanAccion;
use App\Modules\SeguimientoEgresado\Models\ReporteGenerado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Reportes\HojaExcel;
use App\Modules\SeguimientoEgresado\Services\Reportes\LibroExcel;
use Illuminate\Http\Request;
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
     * Formatos oficiales de la evaluación institucional: F5, F7, F8, F9, F10 y F11. Se entregan en Excel, con una hoja por
     * informe y el cuadro del formato (si el servidor no tiene ZipArchive, como texto separado por punto y coma).
     */
    public function exportarFormato(Request $request, string $codigo)
    {
        $codigo = strtoupper($codigo);

        [$hojas, $total] = match ($codigo) {
            'F5' => $this->hojasDePlanes('INFORME SEMESTRAL Y ANUAL SOBRE LA EVALUACIÓN DE RESULTADOS DE PLANES DE ACCIÓN', 'VINCULACION', 'PLANES DE ACCIÓN DE VINCULACIÓN CON LOS EGRESADOS'),
            'F7' => $this->hojasDePlanes('INFORME ESTADÍSTICO SEMESTRAL Y ANUAL DE LA MEJORA DE LA INSERCIÓN LABORAL DE LOS EGRESADOS', 'INSERCION', 'MEJORA DE LA INSERCIÓN LABORAL'),
            'F8' => $this->hojasDeLogro('INFORME DE LOGRO DE LOS OBJETIVOS EDUCACIONALES', EvaluacionOe::class, 'id_objetivo', 'OBJETIVOS EDUCACIONALES',
                ObjetivoEducacional::orderBy('codigo')->get()->mapWithKeys(fn ($o) => [$o->id => ['nombre' => $o->codigo.' · '.$o->descripcion, 'grupo' => null]])->all(), true),
            'F9' => $this->hojasDeLogro('INFORME DEL NIVEL DE LOGRO DE LAS COMPETENCIAS', EvaluacionCompetencia::class, 'id_competencia', 'COMPETENCIAS',
                Competencia::orderBy('tipo')->orderBy('codigo')->get()->mapWithKeys(fn ($c) => [$c->id => [
                    'nombre' => $c->codigo.' · '.$c->nombre,
                    'grupo' => $c->tipo === 'GENERAL' ? 'COMPETENCIAS GENÉRICAS' : 'COMPETENCIAS ESPECÍFICAS',
                ]])->all(), false),
            'F10' => $this->hojasDeCambios('INFORME SOBRE CAMBIOS (MEJORAS) DEL PERFIL DE EGRESO', ['PERFIL', 'OTRA'], 'PERFIL DE EGRESO'),
            'F11' => $this->hojasDeCambios('INFORME SOBRE CAMBIOS (MEJORAS) DE LOS OBJETIVOS EDUCACIONALES', ['OE'], 'OBJETIVOS EDUCACIONALES'),
            default => abort(404),
        };

        return $this->entregar($codigo, $hojas, $total);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Piezas comunes de los formatos oficiales (F5, F7, F8–F11)
    // ---------------------------------------------------------------------------------------------------------------

    /** @param  list<HojaExcel>  $hojas */
    private function entregar(string $codigo, array $hojas, int $total)
    {
        $libro = new LibroExcel;
        $base = 'formato_oficial_'.$codigo.'_'.date('Ymd_His');

        if (! LibroExcel::disponible()) {
            $this->registrarAuditoriaReporte('FORMATO', $codigo, ['total' => $total], $base.'.csv');

            return response()->streamDownload(fn () => print ($libro->aCsv($hojas)), $base.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $this->registrarAuditoriaReporte('FORMATO', $codigo, ['total' => $total], $base.'.xlsx');

        return response()->download($libro->generar($hojas), $base.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Nombre de hoja válido en Excel: hasta 31 caracteres y sin : \ / ? * [ ]. */
    private function nombreDeHoja(string $nombre): string
    {
        return mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $nombre), 0, 31);
    }

    /** Encabezado de los formatos: escuela, promoción o cohorte, año y semestre, fecha del informe. */
    private function encabezadoOficial(HojaExcel $hoja, string $titulo, array $datos): void
    {
        $hoja->filaUnida('UNIVERSIDAD NACIONAL DE TRUJILLO · ESCUELA PROFESIONAL - PREGRADO', LibroExcel::SUBTITULO, 18)
            ->filaUnida($titulo, LibroExcel::TITULO, 34)
            ->fila([[ 'ESCUELA PROFESIONAL:', LibroExcel::ETIQUETA], ['Ingeniería Informática', LibroExcel::VALOR]]);
        foreach ($datos as $etiqueta => $valor) {
            $hoja->fila([[$etiqueta.':', LibroExcel::ETIQUETA], [(string) $valor, LibroExcel::VALOR]]);
        }
        $hoja->vacia();
    }

    private function cajaDeTexto(HojaExcel $hoja, string $etiqueta, ?string $texto): void
    {
        $hoja->fila([[$etiqueta.':', LibroExcel::ETIQUETA]])->filaUnida($texto ?? '', LibroExcel::CAJA, 54)->vacia();
    }

    private function firmaOficial(HojaExcel $hoja): void
    {
        $hoja->vacia()->vacia()
            ->filaUnida('.....................................................', LibroExcel::FIRMA)
            ->filaUnida('V° B° DIRECTOR DE ESCUELA PROFESIONAL', LibroExcel::FIRMA);
    }

    private function hojaVacia(string $titulo, string $mensaje, array $anchos): HojaExcel
    {
        $hoja = new HojaExcel('Sin datos', $anchos);
        $this->encabezadoOficial($hoja, $titulo, []);
        $hoja->filaUnida($mensaje, LibroExcel::NOTA);

        return $hoja;
    }

    private function fechaDeInforme(mixed $fecha): string
    {
        return $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') : '—';
    }

    /**
     * F8 y F9: por cada promoción y periodo, el nivel de logro de cada elemento marcado con una X en su columna.
     *
     * @param  array<int, array{nombre: string, grupo: ?string}>  $elementos
     * @return array{0: list<HojaExcel>, 1: int}
     */
    private function hojasDeLogro(string $titulo, string $modelo, string $columna, string $etiquetaElementos, array $elementos, bool $conDecisiones): array
    {
        $niveles = ['MUY_BUENO' => 'MUY BUENO', 'BUENO' => 'BUENO', 'REGULAR' => 'REGULAR', 'MALO' => 'MALO', 'MUY_MALO' => 'MUY MALO'];
        $anchos = [66, 12, 12, 12, 12, 12];
        $informes = $modelo::with(['cohorte', 'nivelLogro'])->get()
            ->groupBy(fn ($f) => $f->id_cohorte.'|'.$f->periodo)
            ->sortByDesc(fn ($g) => $g->first()->periodo.'|'.str_pad((string) $g->first()->cohorte?->anio_ingreso, 4, '0', STR_PAD_LEFT));

        if ($informes->isEmpty()) {
            return [[$this->hojaVacia($titulo, 'Aún no hay informes registrados.', $anchos)], 0];
        }

        $hojas = [];
        foreach ($informes as $grupo) {
            $primero = $grupo->first();
            $anio = $primero->cohorte?->anio_ingreso ?? '—';
            $hoja = new HojaExcel($this->nombreDeHoja("Prom {$anio} {$primero->periodo}"), $anchos);
            $this->encabezadoOficial($hoja, $titulo, [
                'PROMOCIÓN O COHORTE' => 'Promoción '.$anio,
                'AÑO Y SEMESTRE' => $primero->periodo,
                'FECHA DE INFORME' => $this->fechaDeInforme($grupo->max('fecha_evaluacion')),
                'ESTADO' => $grupo->every(fn ($f) => $f->estado === 'VALIDADA') ? 'Validado por la Dirección' : 'Borrador, pendiente de validación',
            ]);
            $hoja->fila([[$etiquetaElementos, LibroExcel::ENCABEZADO], ...array_map(fn ($n) => [$n, LibroExcel::ENCABEZADO], array_values($niveles))], 28);

            $porElemento = $grupo->keyBy(fn ($f) => (int) $f->{$columna});
            $grupoActual = null;
            foreach ($elementos as $id => $elemento) {
                if (! $porElemento->has($id)) {
                    continue;
                }
                if ($elemento['grupo'] !== null && $elemento['grupo'] !== $grupoActual) {
                    $grupoActual = $elemento['grupo'];
                    $hoja->filaUnida($grupoActual, LibroExcel::GRUPO);
                }
                $nivel = $porElemento[$id]->nivelLogro?->codigo;
                $hoja->fila([[$elemento['nombre'], LibroExcel::TEXTO], ...array_map(fn ($c) => [$nivel === $c ? 'X' : '', $nivel === $c ? LibroExcel::MARCA : LibroExcel::CENTRO], array_keys($niveles))], 34);
            }

            $hoja->vacia();
            $this->cajaDeTexto($hoja, 'CONCLUSIONES', $primero->observacion);
            $this->cajaDeTexto($hoja, 'TOMA DE DECISIONES', $conDecisiones ? $primero->acciones_derivadas : '');
            $this->firmaOficial($hoja);
            $hojas[] = $hoja;
        }

        return [$hojas, $informes->count()];
    }

    /**
     * F10 y F11: lo que «dice» hoy y lo que «debe decir», con el estado del cambio y el documento que lo respalda.
     *
     * @param  array<int, string>  $tipos
     * @return array{0: list<HojaExcel>, 1: int}
     */
    private function hojasDeCambios(string $titulo, array $tipos, string $etiqueta): array
    {
        $anchos = [42, 42, 42, 36, 16, 16, 32, 12];
        $cambios = MejoraCurricular::whereIn('tipo', $tipos)->orderBy('periodo', 'desc')->orderByDesc('fecha_registro')->get()->groupBy('periodo');
        $estados = ['PROPUESTA' => 'Propuesta', 'EVALUACION' => 'En evaluación', 'APROBADA' => 'Aprobada', 'EN_EJECUCION' => 'En ejecución', 'IMPLEMENTADA' => 'Implementada', 'DESCARTADA' => 'Descartada'];
        $tiposDeCambio = ['PERFIL' => 'Perfil de egreso', 'OE' => 'Objetivos educacionales', 'OTRA' => 'Otro cambio del currículo'];

        if ($cambios->isEmpty()) {
            return [[$this->hojaVacia($titulo, 'Aún no hay cambios registrados.', $anchos)], 0];
        }

        $hojas = [];
        foreach ($cambios as $periodo => $delPeriodo) {
            $hoja = new HojaExcel($this->nombreDeHoja('Periodo '.$periodo), $anchos);
            $this->encabezadoOficial($hoja, $titulo, ['AÑO Y SEMESTRE' => $periodo, 'FECHA DE INFORME' => $this->fechaDeInforme($delPeriodo->max('fecha_registro'))]);
            $hoja->fila(array_map(fn ($t) => [$t, LibroExcel::ENCABEZADO], [$etiqueta, 'DICE', 'DEBE DECIR', 'POR QUÉ SE CAMBIA', 'ESTADO', 'FECHA DE IMPLEMENTACIÓN', 'DOCUMENTO DE RESPALDO', 'CUENTA PARA I6']), 32);
            foreach ($delPeriodo as $m) {
                $cuenta = $m->estado === 'IMPLEMENTADA' && $m->origen_seguimiento && $m->fecha_implementacion && ($m->documento_referencia || $m->origen_evidencia || $m->id_evidencia);
                $hoja->fila([
                    [$m->codigo.' · '.$m->titulo.' ('.($tiposDeCambio[$m->tipo] ?? $m->tipo).')', LibroExcel::TEXTO],
                    [$m->dice ?? '—', LibroExcel::TEXTO],
                    [$m->debe_decir ?? '—', LibroExcel::TEXTO],
                    [$m->descripcion, LibroExcel::TEXTO],
                    [$estados[$m->estado] ?? $m->estado, LibroExcel::CENTRO],
                    [$this->fechaDeInforme($m->fecha_implementacion), LibroExcel::CENTRO],
                    [$m->documento_referencia ?? '—', LibroExcel::TEXTO],
                    [$cuenta ? 'Sí' : 'No', $cuenta ? LibroExcel::MARCA : LibroExcel::CENTRO],
                ], 62);
            }
            $this->firmaOficial($hoja);
            $hojas[] = $hoja;
        }

        return [$hojas, $cambios->sum(fn ($g) => $g->count())];
    }

    /**
     * F5 y F7: por periodo, cada plan de acción con su grado de cumplimiento (Sí/No) y sus observaciones. Si no se cumplió,
     * se muestra el plazo para el plan de mejora.
     *
     * @return array{0: list<HojaExcel>, 1: int}
     */
    private function hojasDePlanes(string $titulo, string $tipo, string $etiqueta): array
    {
        $anchos = [52, 14, 14, 70];
        $planes = PlanAccion::where('tipo', $tipo)->orderBy('periodo', 'desc')->orderBy('codigo')->get()->groupBy('periodo');

        if ($planes->isEmpty()) {
            return [[$this->hojaVacia($titulo, 'Aún no hay planes de acción registrados.', $anchos)], 0];
        }

        $hojas = [];
        foreach ($planes as $periodo => $delPeriodo) {
            $hoja = new HojaExcel($this->nombreDeHoja('Periodo '.$periodo), $anchos);
            $this->encabezadoOficial($hoja, $titulo, ['AÑO Y SEMESTRE' => $periodo, 'FECHA DE INFORME' => now(FiltroIndicadores::ZONA)->format('d/m/Y')]);
            $hoja->fila([[$etiqueta, LibroExcel::ENCABEZADO], ['GRADO DE CUMPLIMIENTO: SÍ', LibroExcel::ENCABEZADO], ['GRADO DE CUMPLIMIENTO: NO', LibroExcel::ENCABEZADO], ['OBSERVACIONES (si no cumplió, proponer planes de mejora y con plazos)', LibroExcel::ENCABEZADO]], 36);
            foreach ($delPeriodo as $p) {
                $cumplido = $p->grado_cumplimiento !== null && (float) $p->grado_cumplimiento >= 100;
                $observaciones = array_filter([
                    $p->actividad,
                    $p->grado_cumplimiento !== null ? 'Avance: '.rtrim(rtrim(number_format((float) $p->grado_cumplimiento, 2, '.', ''), '0'), '.').' %' : null,
                    $p->observacion,
                    ! $cumplido && $p->fecha_limite ? 'Plazo: '.$this->fechaDeInforme($p->fecha_limite) : null,
                ]);
                $hoja->fila([
                    [$p->codigo.' · '.$p->titulo, LibroExcel::TEXTO],
                    [$cumplido ? 'X' : '', $cumplido ? LibroExcel::MARCA : LibroExcel::CENTRO],
                    [$cumplido ? '' : 'X', $cumplido ? LibroExcel::CENTRO : LibroExcel::MARCA],
                    [implode(' | ', $observaciones), LibroExcel::TEXTO],
                ], 48);
            }
            $this->firmaOficial($hoja);
            $hojas[] = $hoja;
        }

        return [$hojas, $planes->sum(fn ($g) => $g->count())];
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
