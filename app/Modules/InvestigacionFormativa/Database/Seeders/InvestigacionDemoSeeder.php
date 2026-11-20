<?php

namespace App\Modules\InvestigacionFormativa\Database\Seeders;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Encuesta;
use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\EncuestaService;
use App\Modules\InvestigacionFormativa\Services\EvaluacionService;
use App\Modules\InvestigacionFormativa\Services\GrupoService;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class InvestigacionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])
            || DB::getDriverName() !== 'pgsql'
            || ! str_contains(DB::connection()->getDatabaseName(), '_test_demo_')) {
            throw new RuntimeException('Use local/testing y una base PostgreSQL desechable con _test_demo_ en su nombre.');
        }

        $password = (string) getenv('IF_DEMO_PASSWORD');
        if (strlen($password) < 12) {
            throw new RuntimeException('Defina IF_DEMO_PASSWORD con al menos 12 caracteres. No se imprime ni se guarda en archivos.');
        }

        $resultado = DB::transaction(fn (): array => $this->crearDatos($password));
        $this->command?->info('Datos de demostración creados. Use la contraseña suministrada en IF_DEMO_PASSWORD.');
        $this->command?->table(['Cuenta', 'Correo', 'Roles'], array_map(
            static fn (array $cuenta): array => [$cuenta['nombre'], $cuenta['email'], implode(', ', $cuenta['roles'])],
            $resultado['cuentas'],
        ));
        $this->command?->table(['Sección', 'ID', 'Ruta de resultados'], array_map(
            static fn (int $id, string $nombre): array => [$nombre, $id, '/investigacion-formativa/director/secciones/'.$id.'/resultados'],
            $resultado['secciones'], array_keys($resultado['secciones']),
        ));
        if ($directorio = getenv('IF_DEMO_EXPORT_DIR')) {
            $this->exportar($directorio, $resultado);
        }
        $this->command?->warn('No se crean grupos, entregas, archivos ni notas. La integración de archivos sigue pendiente.');
    }

    /** @return array{cuentas: list<array>, secciones: array<string, int>, lote: string} */
    private function crearDatos(string $password): array
    {
        $this->call(InvestigacionPermisosSeeder::class);
        $lote = strtolower(Str::random(8));
        $facultad = $this->insertar('core.facultad', 'id_facultad', [
            'codigo' => 'IFD-'.$lote, 'nombre' => 'Facultad de demostración '.$lote,
        ]);
        $departamento = $this->insertar('core.departamento_academico', 'id_departamento', [
            'id_facultad' => $facultad, 'codigo' => 'IFD-'.$lote, 'nombre' => 'Departamento de demostración',
        ]);
        $modalidad = $this->insertar('core.modalidad_ingreso', 'id_modalidad', [
            'codigo' => 'IFD-'.$lote, 'nombre' => 'Demostración local', 'activo' => true,
        ]);
        $area = $this->insertar('core.area_formacion', 'id_area', ['codigo' => 'IFD-'.$lote, 'nombre' => 'Demostración']);
        $periodos = [];
        foreach ([1, 2] as $numero) {
            $tipo = $numero === 1 ? 'I' : 'II';
            $existente = DB::table('core.periodo_academico')->where('anio', now()->year)->where('tipo', $tipo)->value('id_periodo');
            $periodos[] = $existente === null ? $this->insertar('core.periodo_academico', 'id_periodo', [
                'codigo' => now()->year.'-'.$tipo, 'anio' => (int) now()->year,
                'tipo' => $numero === 1 ? 'I' : 'II',
                'fecha_inicio' => now()->startOfYear()->addMonths(($numero - 1) * 6)->toDateString(),
                'fecha_fin' => now()->startOfYear()->addMonths($numero * 6)->subDay()->toDateString(),
                'numero_semanas' => 17, 'estado' => $numero === 1 ? 'Cerrado' : 'Activo',
            ]) : (int) $existente;
        }

        $escuelas = [];
        $planes = [];
        $cursos = [];
        foreach (['A', 'B'] as $indice => $codigo) {
            $escuelas[$indice] = $this->insertar('core.escuela_profesional', 'id_escuela', [
                'id_facultad' => $facultad, 'codigo' => 'IFD-'.$lote.'-'.$codigo,
                'nombre' => 'Escuela de demostración '.$codigo.' '.$lote, 'estado' => 'Activo',
            ]);
            $planes[$indice] = $this->insertar('core.plan_estudios', 'id_plan', [
                'id_escuela' => $escuelas[$indice], 'codigo' => 'IFD-'.$lote.'-'.$codigo,
                'nombre' => 'Plan de demostración '.$codigo, 'version' => 'Demo',
                'anio_aprobacion' => (int) now()->year, 'fecha_vigencia_inicio' => now()->startOfYear()->toDateString(),
                'es_predeterminado' => true, 'estado' => 'Vigente',
            ]);
            $curso = $this->insertar('core.curso', 'id_curso', [
                'codigo' => 'IFD-'.$lote.'-'.$codigo, 'nombre' => 'Investigación de demostración '.$codigo,
                'creditos' => 3, 'estado' => 'Activo',
            ]);
            $cursos[$indice] = $this->insertar('core.curso_plan', 'id_curso_plan', [
                'id_plan' => $planes[$indice], 'id_curso' => $curso, 'ciclo' => 5,
                'tipo' => 'Obligatorio', 'id_area' => $area, 'creditos_requeridos' => 0,
            ]);
            $this->insertar('core.linea_investigacion', 'id_linea', [
                'id_escuela' => $escuelas[$indice], 'codigo' => 'IFD-'.$lote.'-'.$codigo,
                'nombre' => 'Línea de demostración '.$codigo, 'estado' => 'Activo',
            ]);
        }

        // Líneas de investigación oficiales de la "antigua" currícula.
        // Datos de muestra proporcionados por el propietario del proyecto.
        // Son un reemplazo candidato: las líneas oficiales definitivas deben
        // suministrarse por la fuente académica autorizada y estas filas de
        // demostración deben ser sustituidas, no combinadas con las oficiales.
        // Los códigos usan el lote actual para ser únicos en cada base de
        // demostración y permitir re-siembra sin colisiones de código único.
        foreach ([
            'Algoritmos y complejidad',
            'Ingeniería de software',
            'Administración de la información',
            'Sistemas inteligentes',
            'Arquitectura de computadoras, redes y comunicaciones',
            'Gráficos, visualización e interacción humano-computador',
        ] as $orden => $nombre) {
            $this->insertar('core.linea_investigacion', 'id_linea', [
                'id_escuela' => $escuelas[0],
                'codigo' => $lote.'-ANT-'.str_pad($orden + 1, 2, '0', STR_PAD_LEFT),
                'nombre' => $nombre,
                'estado' => 'Activo',
            ]);
        }
        $this->insertar('investigacion_formativa.tipo_producto', 'id_tipo_producto', ['nombre' => 'Informe de demostración '.$lote]);

        $cuentas = [];
        $docentes = [];
        $estudiantes = [];
        foreach ([
            'director-a' => [['director_escuela'], 0], 'director-b' => [['director_escuela'], 1],
            'secretaria-a' => [['secretaria'], 0],
            'docente-a' => [['profesor'], 0], 'docente-b' => [['profesor'], 1],
            'docente-libre' => [['profesor'], 0],
            'coordinador' => [['alumno'], 0], 'integrante' => [['alumno'], 0],
            'segundo-grupo' => [['alumno'], 0], 'retirado' => [['alumno'], 0], 'externo' => [['alumno'], 1],
            'multirrol' => [['profesor', 'alumno'], 0],
        ] as $nombre => [$roles, $escuela]) {
            $email = $nombre.'.'.$lote.'@demo.example.test';
            $persona = $this->insertar('core.persona', 'id_persona', [
                'tipo_documento' => 'CE', 'numero_documento' => $lote.'-'.$nombre,
                'nombres' => 'Demo '.str_replace('-', ' ', $nombre), 'apellido_paterno' => 'Local',
            ]);
            $usuario = $this->insertar('seguridad.usuario', 'id_usuario', [
                'id_persona' => $persona, 'correo_institucional' => $email,
                'contrasena_hash' => Hash::make(Str::random(40)), 'debe_cambiar_contrasena' => false, 'estado' => 'Activo',
            ]);
            $user = User::create([
                'name' => 'Demo '.$nombre, 'email' => $email, 'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $user->assignRole($roles);
            DB::table('investigacion_formativa.identidad_usuario')->insert([
                'user_id' => $user->id, 'id_usuario_seguridad' => $usuario, 'activo' => true,
            ]);
            if (in_array('director_escuela', $roles, true)) {
                DB::table('investigacion_formativa.director_escuela')->insert([
                    'user_id' => $user->id, 'id_escuela' => $escuelas[$escuela], 'activo' => true,
                ]);
            }
            if (in_array('secretaria', $roles, true)) {
                DB::table('investigacion_formativa.secretaria_escuela')->insert([
                    'user_id' => $user->id, 'id_escuela' => $escuelas[$escuela], 'activo' => true,
                ]);
            }
            if (in_array('profesor', $roles, true)) {
                $docentes[$nombre] = $this->insertar('core.docente', 'id_docente', [
                    'id_persona' => $persona, 'codigo_docente' => 'IFD-'.$lote.'-'.count($docentes),
                    'id_departamento' => $departamento, 'estado' => 'Activo',
                ]);
            }
            if (in_array('alumno', $roles, true)) {
                $estudiantes[$nombre] = $this->insertar('core.estudiante', 'id_estudiante', [
                    'id_persona' => $persona, 'codigo_universitario' => 'IFD-'.$lote.'-'.count($estudiantes),
                    'id_escuela' => $escuelas[$escuela], 'id_plan' => $planes[$escuela],
                    'id_periodo_ingreso' => $periodos[0], 'id_modalidad_ingreso' => $modalidad, 'estado' => 'Activo',
                ]);
            }
            $cuentas[$nombre] = ['nombre' => $nombre, 'email' => $email, 'roles' => $roles, 'user' => $user];
        }

        $secciones = [];
        foreach ([
            'completa' => [0, 1, 'docente-a', 'Activa'],
            'incompleta' => [0, 1, 'docente-a', 'Activa'],
            'deshabilitada' => [0, 1, 'docente-a', 'Activa'],
            'historica' => [0, 0, 'docente-a', 'Cerrada'],
            'otra-escuela' => [1, 1, 'docente-b', 'Activa'],
        ] as $nombre => [$escuela, $periodo, $docente, $estado]) {
            $id = $this->insertar('core.seccion', 'id_seccion', [
                'id_periodo' => $periodos[$periodo], 'id_curso_plan' => $cursos[$escuela],
                'codigo' => 'IFD-'.$nombre, 'capacidad' => 30,
                'id_docente_responsable' => $docentes[$docente], 'estado' => $estado,
            ]);
            $secciones[$nombre] = $id;
            DB::table('investigacion_formativa.seccion_configuracion')->insert([
                'id_seccion' => $id, 'habilitada' => in_array($nombre, ['completa', 'incompleta', 'otra-escuela'], true), 'version' => 1,
            ]);
            if ($estado === 'Activa') {
                DB::table('investigacion_formativa.competencia_seccion')->insert([
                    'id_seccion' => $id, 'codigo' => 'DEMO-C1', 'descripcion' => 'Formula un problema de investigación.',
                    'fuente' => 'Ejemplo local sin aprobación académica', 'seleccionada' => false,
                ]);
            }
        }
        foreach ($estudiantes as $nombre => $id) {
            $ids = $nombre === 'externo' ? [$secciones['otra-escuela']] : [$secciones['completa'], $secciones['incompleta'], $secciones['deshabilitada']];
            foreach ($ids as $seccion) {
                DB::table('investigacion_formativa.participante_seccion')->insert([
                    'id_seccion' => $seccion, 'id_estudiante' => $id,
                    'estado' => $nombre === 'retirado' ? 'retirado' : 'activo',
                    'fuente' => 'Demostración local', 'referencia_origen' => $lote,
                ]);
            }
        }

        $configuracion = app(ConfiguracionService::class);
        $actor = app(AccesoInvestigacionService::class)->resolver($cuentas['docente-a']['user']);
        $seccion = $secciones['completa'];
        $competencia = (int) DB::table('investigacion_formativa.competencia_seccion')->where('id_seccion', $seccion)->value('id_competencia');
        $configuracion->guardarCompetencias($actor, $seccion, [$competencia], 1);
        $configuracion->guardarRubrica($actor, $seccion, [
            'nombre' => 'Rúbrica de demostración', 'descripcion' => 'Ejemplo local sin aprobación académica.',
            'criterios' => [[
                'descripcion' => 'Planteamiento del problema',
                'niveles' => array_map(static fn (int $nivel): array => [
                    'nivel' => $nivel, 'descripcion' => 'Nivel '.$nivel, 'puntaje' => $nivel,
                ], [1, 2, 3, 4]),
            ]],
        ], 2);
        $configuracion->guardarCronograma($actor, $seccion, array_map(
            static fn (int $dias): string => now('America/Lima')->addDays($dias)->format('Y-m-d\TH:i'),
            [30, 60, 90],
        ), 3);

        $this->crearDatosMuestra($actor, $secciones, $cuentas, $docentes, $estudiantes, $periodos);

        return ['cuentas' => array_values($cuentas), 'secciones' => $secciones, 'lote' => $lote];
    }

    private function exportar(string $directorio, array $resultado): void
    {
        $destino = rtrim($directorio, '/').'/'.$resultado['lote'];
        File::makeDirectory($destino, 0700, true);
        $usuarios = array_map(static fn (array $cuenta): int => (int) $cuenta['user']->id, $resultado['cuentas']);
        $secciones = array_values($resultado['secciones']);
        $archivos = [
            'identidades' => ['investigacion_formativa.identidad_usuario', ['user_id', 'id_usuario_seguridad', 'activo'], 'user_id', $usuarios],
            'directores' => ['investigacion_formativa.director_escuela', ['user_id', 'id_escuela', 'activo'], 'user_id', $usuarios],
            'secretarias' => ['investigacion_formativa.secretaria_escuela', ['user_id', 'id_escuela', 'activo'], 'user_id', $usuarios],
            'participantes' => ['investigacion_formativa.participante_seccion', ['id_seccion', 'id_estudiante', 'estado', 'fuente', 'referencia_origen'], 'id_seccion', $secciones],
            'competencias' => ['investigacion_formativa.competencia_seccion', ['id_seccion', 'codigo', 'descripcion', 'fuente', 'seleccionada'], 'id_seccion', $secciones],
        ];
        foreach ($archivos as $nombre => [$tabla, $columnas, $clave, $ids]) {
            $archivo = fopen($destino.'/'.$nombre.'.csv', 'x');
            if ($archivo === false) {
                throw new RuntimeException('No se pudo crear el CSV de demostración.');
            }
            try {
                fputcsv($archivo, $columnas, ',', '"', '');
                foreach (DB::table($tabla)->whereIn($clave, $ids)->orderBy($clave)->get($columnas) as $fila) {
                    fputcsv($archivo, array_map(static fn (mixed $valor): mixed => is_bool($valor) ? (int) $valor : $valor, (array) $fila), ',', '"', '');
                }
            } finally {
                fclose($archivo);
            }
        }
        $cuentas = array_map(static fn (array $cuenta): array => [
            'nombre' => $cuenta['nombre'], 'email' => $cuenta['email'], 'roles' => $cuenta['roles'], 'user_id' => $cuenta['user']->id,
        ], $resultado['cuentas']);
        File::put($destino.'/demo.json', json_encode([
            'cuentas' => $cuentas, 'secciones' => $resultado['secciones'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->command?->info('Ejemplos CSV y datos de acceso sin contraseña: '.$destino);
    }

    /**
     * Crea datos de muestra completos a través de los servicios normales del
     * módulo: grupos, entregas, evaluaciones, decisiones y respuestas de encuesta.
     *
     * Usa rutas reales de servicio (no inserciones directas de negocio) para que
     * los datos de demostración reflejen flujos operativos válidos.
     */
    private function crearDatosMuestra(
        ActorInvestigacion $actor,
        array $secciones,
        array $cuentas,
        array $docentes,
        array $estudiantes,
        array $periodos,
    ): void {
        $gruposService = app(GrupoService::class);
        $evaluacionService = app(EvaluacionService::class);
        $decisionService = app(DecisionTrabajoService::class);
        $encuestaService = app(EncuestaService::class);

        $seccionCompleta = $secciones['completa'];

        // Si ya existen grupos en cualquier sección, los datos de muestra ya
        // fueron creados (idempotencia para re-seed en misma base).
        $gruposExistentes = DB::table('investigacion_formativa.grupo')
            ->count();
        if ($gruposExistentes > 0) {
            return;
        }

        // Línea de investigación de la escuela de la sección (scoped, no global-first)
        // Obtenemos el id_escuela de la sección completa como valor escalar para
        // evitar ambigüedades de nombres de columna en el WHERE.
        $seccionEscuela = DB::table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->where('s.id_seccion', $seccionCompleta)
            ->value('pe.id_escuela');

        if ($seccionEscuela === null) {
            throw new RuntimeException('No se encontró escuela para la sección completa.');
        }

        $idLinea = DB::table('core.linea_investigacion')
            ->where('id_escuela', $seccionEscuela)
            ->where('estado', 'Activo')
            ->orderBy('id_linea')
            ->value('id_linea');

        if ($idLinea === null) {
            throw new RuntimeException('No se encontró línea de investigación activa para la escuela de la sección completa.');
        }

        $idTipoProducto = DB::table('investigacion_formativa.tipo_producto')
            ->value('id_tipo_producto');

        if ($idTipoProducto === null) {
            throw new RuntimeException('No se encontró tipo de producto.');
        }

        // 1. Crear grupos y proyectos a través del servicio normal
        // Cada grupo usa a su propio coordinador como actor creador.
        $grupos = [
            [
                'nombre' => 'Grupo Alfa',
                'titulo' => 'Sistema de detección temprana de riesgos ambientales',
                'coordinador' => 'coordinador',
                'integrantes' => ['coordinador', 'integrante'],
            ],
            [
                'nombre' => 'Grupo Beta',
                'titulo' => 'Plataforma de tutoría entre pares para primeros ciclos',
                'coordinador' => 'segundo-grupo',
                'integrantes' => ['segundo-grupo'],
            ],
        ];

        $proyectos = [];
        foreach ($grupos as $grupo) {
            $actorGrupo = app(AccesoInvestigacionService::class)
                ->resolver($cuentas[$grupo['coordinador']]['user']);

            $resultado = $gruposService->crear($actorGrupo, $seccionCompleta, [
                'nombre' => $grupo['nombre'],
                'titulo' => $grupo['titulo'],
                'integrante_ids' => array_map(
                    function (string $nombre) use ($estudiantes): int {
                        return (int) $estudiantes[$nombre];
                    },
                    $grupo['integrantes'],
                ),
                'id_linea' => (int) $idLinea,
                'id_tipo_producto' => (int) $idTipoProducto,
            ]);
            $proyectos[] = (int) $resultado->id_proyecto;
        }

        // 2. Crear entregas y evaluaciones para cada proyecto (unidades 1, 2, 3)
        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');

        $entregables = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', $seccionCompleta)
            ->orderBy('orden')
            ->get(['id_entregable', 'orden']);

        // Obtener los criterios de la rúbrica de la sección para crear evaluaciones válidas
        $idRubrica = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $seccionCompleta)
            ->value('id_rubrica');

        if ($idRubrica === null) {
            throw new RuntimeException('La sección completa no tiene rúbrica configurada.');
        }

        $criteriosIds = DB::table('investigacion_formativa.criterio')
            ->where('id_rubrica', $idRubrica)
            ->orderBy('orden')
            ->pluck('id_criterio')
            ->all();

        foreach ($proyectos as $indiceProyecto => $idProyecto) {
            foreach ($entregables as $entregable) {
                $idArchivo = DB::table('comun.archivo')->insertGetId([
                    'id_modulo' => (int) $idModulo,
                    'nombre_original' => 'muestra-proyecto-'.$idProyecto.'-u'.(int) $entregable->orden.'.pdf',
                    'ruta_almacenamiento' => 'investigacion-formativa/test/muestra-proyecto-'.$idProyecto.'-u'.(int) $entregable->orden.'.pdf',
                    'tipo_mime' => 'application/pdf',
                    'tamano_bytes' => 1024,
                    'hash_sha256' => str_repeat('a', 64),
                    'subido_por' => $cuentas['coordinador']['user']->id,
                ], 'id_archivo');

                $idEntrega = DB::table('investigacion_formativa.entrega')->insertGetId([
                    'id_proyecto' => $idProyecto,
                    'id_entregable' => (int) $entregable->id_entregable,
                    'version' => 1,
                    'id_archivo' => (int) $idArchivo,
                ], 'id_entrega');

                // Evaluar con notas variadas para generar diferentes promedios
                $nivel = $indiceProyecto === 0 ? 4 : ($indiceProyecto === 1 ? 3 : 2);
                $niveles = [];
                foreach ($criteriosIds as $idCriterio) {
                    $niveles[] = ['id_criterio' => (int) $idCriterio, 'nivel' => $nivel];
                }

                $evaluacionService->guardar(
                    $actor,
                    new Entrega(['id_entrega' => (int) $idEntrega]),
                    $niveles,
                    'Evaluación de muestra para la unidad '.(int) $entregable->orden,
                    false,
                    ['Observación de demostración generada automáticamente.'],
                    0,
                );
            }
        }

        // 3. Decisiones de aprobación y selección para proyectos completos
        foreach ($proyectos as $idProyecto) {
            $decisionService->aprobar($actor, $idProyecto, 0);
        }

        // Seleccionar solo el primer proyecto como trabajo destacado
        if ($proyectos !== []) {
            $decisionService->seleccionar($actor, $proyectos[0], 0);
        }

        // 4. Crear y publicar encuesta, luego agregar respuestas de estudiantes
        $actorDirector = app(AccesoInvestigacionService::class)
            ->resolver($cuentas['director-a']['user']);

        $encuestaResultado = $encuestaService->guardarBorrador(
            $actorDirector,
            $seccionCompleta,
            [
                '¿El trabajo de investigación fortalece la formación profesional?',
                '¿La orientación del docente responsable fue clara y oportuna?',
            ],
            0,
        );

        $encuestaModel = Encuesta::query()->find((int) $encuestaResultado->id_encuesta);
        $encuestaService->publicar($actorDirector, $encuestaModel, (int) $encuestaResultado->version);

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', $seccionCompleta)
            ->where('estado', 'publicada')
            ->first();

        $preguntas = DB::table('investigacion_formativa.encuesta_pregunta')
            ->where('id_encuesta', (int) $encuesta->id_encuesta)
            ->orderBy('orden')
            ->get(['id_pregunta']);

        foreach (['coordinador', 'integrante', 'segundo-grupo'] as $nombreEstudiante) {
            if (! isset($estudiantes[$nombreEstudiante])) {
                continue;
            }
            $idEstudiante = (int) $estudiantes[$nombreEstudiante];
            $yaResponde = DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', (int) $encuesta->id_encuesta)
                ->where('id_estudiante', $idEstudiante)
                ->exists();
            if ($yaResponde) {
                continue;
            }

            $idRespuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
                'id_encuesta' => (int) $encuesta->id_encuesta,
                'id_estudiante' => $idEstudiante,
                'comentario' => 'Respuesta de muestra del estudiante '.$nombreEstudiante,
                'respondida_en' => now('UTC')->format('Y-m-d H:i:sP'),
            ], 'id_respuesta');

            $valor = 1;
            foreach ($preguntas as $pregunta) {
                DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                    'id_encuesta' => (int) $encuesta->id_encuesta,
                    'id_respuesta' => (int) $idRespuesta,
                    'id_pregunta' => (int) $pregunta->id_pregunta,
                    'valor' => $valor,
                ]);
                $valor = $valor === 5 ? 1 : $valor + 1;
            }
        }
    }

    private function insertar(string $tabla, string $clave, array $datos): int
    {
        return (int) DB::table($tabla)->insertGetId($datos, $clave);
    }
}
