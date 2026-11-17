<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\CatEstadoAcademico;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\HistorialEstadoAcademico;
use App\Modules\SeguimientoEgresado\Models\ImportacionPadron;
use App\Modules\SeguimientoEgresado\Models\PlanCurricular;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use App\Modules\SeguimientoEgresado\Services\PadronImportService;
use App\Modules\SeguimientoEgresado\Support\Esquema;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PadronController extends Controller
{
    /**
     * Muestra la vista principal del Módulo M1: Padrón, Cohortes, Acreditación y Cargas.
     */
    public function index(Request $request, IndicadoresService $indicadoresService)
    {
        $busqueda = $request->query('q');
        $cohorteFiltro = $request->query('cohorte');
        $estadoFiltro = $request->query('estado');

        try {
            // 1. Cohortes y métricas de I1 (denominador oficial = ingresantes)
            $cohortes = Cohorte::with('planCurricular')->orderBy('anio_ingreso', 'asc')->get();

            // 2. Consulta de egresados con relaciones vigentes
            $egresadosQuery = Egresado::with([
                'cohorte',
                'contactoVigente',
                'estadoVigente.estadoAcademico',
            ]);

            if (! empty($busqueda)) {
                $egresadosQuery->buscar($busqueda);
            }

            if (! empty($cohorteFiltro) && $cohorteFiltro !== 'todas') {
                $egresadosQuery->where('id_cohorte', $cohorteFiltro);
            }

            if (! empty($estadoFiltro) && $estadoFiltro !== 'todos') {
                $egresadosQuery->porEstado($estadoFiltro);
            }

            $egresados = $egresadosQuery->orderBy('apellido_paterno')->get();

            // 3. Importaciones de secretaría para la pestaña 4
            $importaciones = ImportacionPadron::with(['usuario.rol', 'filas.egresado'])
                ->orderByDesc('fecha_importacion')
                ->get();

            // 4. Catálogos para filtros
            $estadosAcademicos = CatEstadoAcademico::where('activo', true)->get();
            $planesCurriculares = PlanCurricular::withCount('cohortes')->orderBy('vigencia_desde', 'asc')->get();

            // 5. Métricas globales del módulo M1
            $totalEgresados = Egresado::count();
            $totalCohortes = $cohortes->count();
            $totalAcreditados = Egresado::whereHas('estadoVigente.estadoAcademico', function ($q) {
                $q->whereIn('codigo', ['BACHILLER', 'TITULADO']);
            })->count();
            $totalImportaciones = $importaciones->count();

            // Indicador I1 global consolidado
            $i1Data = $indicadoresService->getIndicadorI1();
        } catch (\Throwable $e) {
            // Respaldo resiliente para pruebas de navegación sin base de datos
            $cohortes = collect();
            $egresados = collect();
            $importaciones = collect();
            $estadosAcademicos = collect();
            $planesCurriculares = collect();
            $totalEgresados = 0;
            $totalCohortes = 0;
            $totalAcreditados = 0;
            $totalImportaciones = 0;
            $i1Data = ['codigo' => 'I1', 'data' => []];
        }

        return view('modules.seguimiento-egresado.paginas.padron', compact(
            'cohortes',
            'egresados',
            'importaciones',
            'estadosAcademicos',
            'planesCurriculares',
            'totalEgresados',
            'totalCohortes',
            'totalAcreditados',
            'totalImportaciones',
            'busqueda',
            'cohorteFiltro',
            'estadoFiltro',
            'i1Data'
        ));
    }

    /**
     * Procesa la carga de archivos de padrón (CSV/Excel) evitando duplicados.
     */
    public function importar(Request $request, PadronImportService $importService)
    {
        $request->validate([
            'promocion' => ['required', 'integer', 'exists:'.Esquema::validar('cohorte').',id'],
            'archivo_padron' => 'required|file|max:10240', // 10MB max
        ], [
            'promocion.required' => 'Elige la promoción a la que pertenecen los alumnos del archivo.',
            'promocion.exists' => 'La promoción elegida no existe.',
            'archivo_padron.required' => 'Elige el archivo con los alumnos.',
            'archivo_padron.file' => 'El archivo no es válido.',
            'archivo_padron.max' => 'El archivo no debe pasar de 10 MB.',
        ]);

        $archivo = $request->file('archivo_padron');
        $promocion = Cohorte::findOrFail($request->integer('promocion'));
        // Siempre «todo o nada»: si una fila falla, no se guarda ninguna.
        $resultado = $importService->importarArchivo($archivo, null, true, $promocion->id);

        if ($resultado['rechazadas'] > 0 && $resultado['aceptadas'] === 0) {
            $problemas = collect($resultado['detalles'] ?? [])
                ->filter(fn (array $d) => ($d['error'] ?? '') !== PadronImportService::MENSAJE_FILA_SIN_ERROR)
                ->map(fn (array $d) => 'Fila '.$d['numero_fila'].': '.$d['error'])
                ->values();
            $mensaje = 'No se cargó ningún alumno. Corrige esto en el archivo y vuelve a cargarlo:'
                ."\n".$problemas->take(5)->implode("\n")
                .($problemas->count() > 5 ? "\ny ".($problemas->count() - 5).' más (míralas en el historial de cargas).' : '');

            return redirect()->to(route('seguimiento-egresado.padron.promocion', $promocion->id).'#paso-2')
                ->with('import_resumen', $resultado)
                ->with('error', $mensaje);
        }

        $n = $resultado['aceptadas'];
        $mensaje = ($n === 1 ? 'Se cargó 1 alumno' : "Se cargaron {$n} alumnos")." en la Promoción {$promocion->anio_ingreso}. "
            .'Siguiente paso: enviarles el enlace de contacto.';

        return redirect()->to(route('seguimiento-egresado.padron.promocion', $promocion->id).'#paso-3')
            ->with('import_resumen', $resultado)
            ->with('success', $mensaje);
    }

    /**
     * API JSON para modal con el expediente integral del egresado.
     */
    public function fichaEgresado(int $id, IndicadoresService $indicadoresService)
    {
        $egresado = Egresado::with([
            'cohorte.planCurricular',
            'contactoVigente',
            'estadoVigente.estadoAcademico',
            'historialEstados.estadoAcademico',
            'historialEstados.usuarioRegistro',
        ])->findOrFail($id);

        $historialSeguimiento = $indicadoresService->getHistorialSeguimiento($id);

        return response()->json([
            'egresado' => $egresado,
            'nombre_completo' => $egresado->nombre_completo,
            'contacto' => $egresado->contactoVigente,
            'estado_actual' => $egresado->estadoVigente?->estadoAcademico?->nombre ?? 'Egresado',
            'historial_academico' => $egresado->historialEstados,
            'historial_seguimiento' => $historialSeguimiento,
        ]);
    }

    /**
     * Descarga de plantilla CSV de ejemplo para Secretaría.
     */
    public function descargarPlantilla(): StreamedResponse
    {
        $cabeceras = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_padron_egresados_unt.csv"',
        ];

        return response()->stream(function () {
            $salida = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fprintf($salida, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($salida, ['matricula', 'tipo_doc', 'numero_documento', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo_institucional']);
            fputcsv($salida, ['1021400818', 'DNI', '74561239', 'Claudia Marcela', 'Paredes', 'Ríos', 'claudia.paredes@correo.test']);
            fputcsv($salida, ['1021400918', 'DNI', '71239845', 'Jorge Luis', 'Navarro', 'Sánchez', 'jorge.navarro@correo.test']);

            fclose($salida);
        }, 200, $cabeceras);
    }

    /**
     * Guarda o actualiza un expediente individual de egresado.
     */
    public function guardarEgresado(Request $request)
    {
        $id = $request->input('id');

        $request->validate([
            'codigo_matricula' => 'required|string|max:20',
            'numero_documento' => 'required|string|max:20',
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'id_cohorte' => 'required',
            'periodo_egreso' => 'nullable|string|max:20',
            'grado' => 'required|string|in:PROXIMO,EGRESADO,BACHILLER,TITULADO',
            'apto_f1' => 'required|string|in:APTO,PENDIENTE',
            'correo_institucional' => 'required|email|max:150',
            'correo_personal' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:50',
        ], [
            'correo_institucional.required' => 'El correo institucional es obligatorio: a él se envía el enlace de contacto inicial.',
            'correo_institucional.email' => 'El correo institucional no es válido.',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $cohorte = Cohorte::where('id', $request->input('id_cohorte'))
                ->orWhere('anio_ingreso', $request->input('id_cohorte'))
                ->first();

            if (! $cohorte) {
                return response()->json(['success' => false, 'message' => 'La cohorte indicada no existe. Regístrela primero en «Cohortes y Denominador I1».'], 422);
            }

            $cohorteId = $cohorte->id;

            if ($id) {
                $egresado = Egresado::findOrFail($id);
            } else {
                $existe = Egresado::where('codigo_matricula', $request->codigo_matricula)
                    ->orWhere('numero_documento', $request->numero_documento)
                    ->first();
                if ($existe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un egresado registrado con la matrícula o documento indicado.',
                    ], 422);
                }
                $egresado = new Egresado;
                $egresado->tipo_documento = 'DNI';
                $egresado->programa = 'Ingeniería Informática';
                $egresado->sede = 'Trujillo';
                $egresado->creado_en = now();
            }

            $egresado->id_cohorte = $cohorteId;
            $egresado->codigo_matricula = $request->codigo_matricula;
            $egresado->numero_documento = $request->numero_documento;
            $egresado->nombres = $request->nombres;
            $egresado->apellido_paterno = $request->apellido_paterno;
            $egresado->apellido_materno = $request->apellido_materno;
            $egresado->periodo_egreso = $request->periodo_egreso ?: null;
            $egresado->correo_institucional = $request->correo_institucional;
            $egresado->save();

            // El contacto solo se guarda si la Secretaría trae un correo personal y un celular reales; si no, lo registra
            // el propio alumno con su enlace de contacto inicial.
            if ($request->correo_personal && $request->telefono) {
                $contacto = $egresado->contactoVigente;
                if (! $contacto) {
                    $contacto = new ContactoEgresado;
                    $contacto->id_egresado = $egresado->id;
                    $contacto->vigente_desde = now();
                    $contacto->origen_registro = 'SECRETARIA';
                }
                $contacto->correo_personal = $request->correo_personal;
                $contacto->celular_whatsapp = $request->telefono;
                $contacto->save();
            }

            $catEstado = CatEstadoAcademico::where('codigo', $request->grado)->firstOrFail();

            $estadoActual = $egresado->estadoVigente;
            $cambioGrado = ! $estadoActual || $estadoActual->id_estado_academico !== $catEstado->id;

            if ($cambioGrado) {
                if ($estadoActual) {
                    $estadoActual->vigente_hasta = $this->cierreDeVigencia($estadoActual->vigente_desde);
                    $estadoActual->save();
                }

                $usuarioId = UsuarioInterno::idActual();
                HistorialEstadoAcademico::create([
                    'id_egresado' => $egresado->id,
                    'id_estado_academico' => $catEstado->id,
                    'creditos_aprobados' => null,
                    'apto_graduarse' => $request->apto_f1 === 'APTO',
                    'vigente_desde' => now(),
                    'vigente_hasta' => null,
                    'id_usuario_registro' => $usuarioId ?? 1,
                ]);
            } else {
                $estadoActual->apto_graduarse = $request->apto_f1 === 'APTO';
                $estadoActual->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Expediente guardado exitosamente en el padrón oficial.',
                'egresado' => $egresado->fresh(['cohorte', 'contactoVigente', 'estadoVigente.estadoAcademico']),
            ]);
        });
    }

    /**
     * Registra o actualiza una cohorte de ingresantes.
     */
    public function guardarCohorte(Request $request)
    {
        $id = $request->input('id');

        $request->validate([
            'anio_ingreso' => 'required|integer|min:2000|max:2035',
            'total_ingresantes' => 'required|integer|min:1|max:1000',
            'codigo_promocion' => 'nullable|string|max:50',
            'duracion_esperada_anios' => 'nullable|integer|min:3|max:10',
            'id_plan_curricular' => 'nullable',
            'observacion' => 'nullable|string|max:500',
        ]);

        if ($id) {
            $cohorte = Cohorte::where('id', $id)->orWhere('anio_ingreso', $id)->firstOrFail();
        } else {
            $existe = Cohorte::where('anio_ingreso', $request->anio_ingreso)->first();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya existe la promoción del año {$request->anio_ingreso}.",
                ], 422);
            }
            $cohorte = new Cohorte;
            $cohorte->semestre_ingreso = 'I';
            $cohorte->id_plan_curricular = PlanCurricular::where('codigo_version', 'Plan 2017')->value('id')
                ?? PlanCurricular::first()?->id
                ?? 1;
        }

        if ($request->filled('id_plan_curricular')) {
            $plan = PlanCurricular::where('id', $request->id_plan_curricular)
                ->orWhere('codigo_version', $request->id_plan_curricular)
                ->first();
            if ($plan) {
                $cohorte->id_plan_curricular = $plan->id;
            }
        }

        $cohorte->anio_ingreso = $request->anio_ingreso;
        $cohorte->codigo_promocion = $request->codigo_promocion ?? "PROM-{$request->anio_ingreso}";
        $cohorte->total_ingresantes = $request->total_ingresantes;
        $cohorte->duracion_esperada_anios = $request->duracion_esperada_anios ?? 5;
        $cohorte->observacion = $request->observacion;
        $cohorte->save();

        return response()->json([
            'success' => true,
            'message' => "La promoción {$cohorte->anio_ingreso} quedó guardada.",
            'cohorte' => $cohorte->fresh(['planCurricular']),
        ]);
    }

    /**
     * Registra o actualiza un plan curricular en la base de datos.
     */
    public function guardarPlanCurricular(Request $request)
    {
        $id = $request->input('id');

        $request->validate([
            'codigo_version' => 'required|string|max:35',
            'vigencia_desde' => 'required|date',
            'vigencia_hasta' => 'nullable|date',
            'estado' => 'required|string|in:BORRADOR,VIGENTE,CERRADO',
        ]);

        if ($id) {
            $plan = PlanCurricular::findOrFail($id);
        } else {
            $existe = PlanCurricular::where('codigo_version', $request->codigo_version)->first();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya existe el plan de estudios {$request->codigo_version}.",
                ], 422);
            }
            $plan = new PlanCurricular;
        }

        $plan->codigo_version = $request->codigo_version;
        $plan->vigencia_desde = $request->vigencia_desde;
        $plan->vigencia_hasta = $request->vigencia_hasta;
        $plan->estado = $request->estado;
        $plan->save();

        return response()->json([
            'success' => true,
            'message' => "El plan de estudios {$plan->codigo_version} quedó guardado.",
            'plan' => $plan->loadCount('cohortes'),
        ]);
    }

    /**
     * Actualiza el estado académico y condición de acreditación F1/F4 de un egresado.
     */
    public function guardarEstadoAcademico(Request $request)
    {
        $request->validate([
            'id_egresado' => 'required|integer|exists:'.Esquema::validar('egresado').',id',
            'grado' => 'required|string|in:EGRESADO,BACHILLER,TITULADO',
            'apto_f1' => 'required|string|in:APTO,PENDIENTE',
            'creditos_aprobados' => 'nullable|numeric|min:0',
            'resolucion_bachiller' => 'nullable|string|max:100',
            'fecha_bachiller' => 'nullable|date',
            'resolucion_titulo' => 'nullable|string|max:100',
            'fecha_titulo' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($request) {
            $egresado = Egresado::findOrFail($request->id_egresado);
            $catEstado = CatEstadoAcademico::where('codigo', $request->grado)->firstOrFail();

            if ($egresado->estadoVigente) {
                $egresado->estadoVigente->vigente_hasta = $this->cierreDeVigencia($egresado->estadoVigente->vigente_desde);
                $egresado->estadoVigente->save();
            }

            $usuarioId = UsuarioInterno::idActual();
            HistorialEstadoAcademico::create([
                'id_egresado' => $egresado->id,
                'id_estado_academico' => $catEstado->id,
                'creditos_aprobados' => $request->creditos_aprobados,
                'apto_graduarse' => $request->apto_f1 === 'APTO',
                'resolucion_bachiller' => $request->resolucion_bachiller,
                'fecha_bachiller' => $request->fecha_bachiller,
                'resolucion_titulo' => $request->resolucion_titulo,
                'fecha_titulo' => $request->fecha_titulo,
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'id_usuario_registro' => $usuarioId ?? 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Estado académico actualizado para {$egresado->nombre_completo}.",
                'egresado' => $egresado->fresh(['estadoVigente.estadoAcademico']),
            ]);
        });
    }

    /**
     * Confirma oficialmente el egreso de un alumno apto (PROXIMO) con su fecha real. Cierra la etapa anterior y abre la
     * de egresado; el expediente y los contactos se conservan. El periodo se calcula de la fecha (I hasta julio, II después).
     */
    public function confirmarEgreso(Request $request, int $id)
    {
        $egresado = Egresado::with('estadoVigente.estadoAcademico')->findOrFail($id);

        $request->validate([
            'fecha_egreso' => ['required', 'date', 'before_or_equal:today', $egresado->fecha_ingreso ? 'after_or_equal:'.Carbon::parse($egresado->fecha_ingreso)->toDateString() : 'nullable'],
        ], [
            'fecha_egreso.required' => 'Indique la fecha real de egreso.',
            'fecha_egreso.before_or_equal' => 'La fecha de egreso no puede ser futura.',
            'fecha_egreso.after_or_equal' => 'La fecha de egreso no puede ser anterior al ingreso.',
        ]);

        $actual = $egresado->estadoVigente;
        if ($actual === null || $actual->estadoAcademico->codigo !== 'PROXIMO') {
            return response()->json(['success' => false, 'message' => 'Solo se puede confirmar el egreso de un alumno apto para egresar.'], 422);
        }

        // El contacto inicial es obligatorio para egresar, pero no se bloquea: se avisa a la Secretaría.
        $sinContacto = ! $egresado->contactoVigente()->exists();

        $fecha = Carbon::parse($request->fecha_egreso);

        return DB::transaction(function () use ($egresado, $actual, $fecha, $sinContacto) {
            $actual->vigente_hasta = $this->cierreDeVigencia($actual->vigente_desde);
            $actual->save();

            HistorialEstadoAcademico::create([
                'id_egresado' => $egresado->id,
                'id_estado_academico' => CatEstadoAcademico::where('codigo', 'EGRESADO')->value('id'),
                'creditos_aprobados' => $actual->creditos_aprobados,
                'apto_graduarse' => true,
                'vigente_desde' => $actual->vigente_hasta,
                'vigente_hasta' => null,
                'id_usuario_registro' => UsuarioInterno::idActual() ?? $actual->id_usuario_registro,
            ]);

            $egresado->fecha_egreso = $fecha->toDateString();
            $egresado->periodo_egreso = $fecha->year.'-'.($fecha->month <= 7 ? 'I' : 'II');
            $egresado->save();

            return response()->json([
                'success' => true,
                'aviso' => $sinContacto,
                'message' => "Se confirmó el egreso de {$egresado->nombre_completo}."
                    .($sinContacto ? ' Aviso: todavía no completó su contacto inicial, que es obligatorio para egresar. Pídele que lo complete desde su promoción.' : ''),
                'egresado' => $egresado->fresh(['cohorte', 'contactoVigente', 'estadoVigente.estadoAcademico']),
            ]);
        });
    }

    /** Momento en que se cierra una vigencia: ahora, pero nunca igual ni anterior al inicio de esa vigencia (restricción de la BD). */
    private function cierreDeVigencia(mixed $vigenteDesde): Carbon
    {
        $desde = Carbon::parse($vigenteDesde);

        return now()->greaterThan($desde) ? now() : $desde->copy()->addSecond();
    }
}
