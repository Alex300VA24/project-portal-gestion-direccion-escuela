<?php

namespace App\Modules\InvestigacionFormativa\Database\Seeders;

use App\Models\User;
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
    /** @var list<string> */
    private const MODOS = ['workflow', 'showcase'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])
            || DB::getDriverName() !== 'pgsql'
            || ! str_contains(DB::connection()->getDatabaseName(), '_test_demo_')) {
            throw new RuntimeException('Use local/testing y una base PostgreSQL desechable con _test_demo_ en su nombre.');
        }

        $modo = strtolower(trim((string) getenv('IF_DEMO_MODE')));
        if (! in_array($modo, self::MODOS, true)) {
            throw new RuntimeException('Defina IF_DEMO_MODE=workflow o IF_DEMO_MODE=showcase.');
        }

        $password = (string) getenv('IF_DEMO_PASSWORD');
        if (strlen($password) < 12) {
            throw new RuntimeException('Defina IF_DEMO_PASSWORD con al menos 12 caracteres. No se imprime ni se guarda en archivos.');
        }

        $resultado = DB::transaction(fn (): array => $this->crearDatos($password, $modo));
        $this->command?->info("Datos de demostración creados en modo {$modo} (lote {$resultado['lote']}). Use la contraseña suministrada en IF_DEMO_PASSWORD.");
        $this->command?->table(['Cuenta', 'Correo', 'Roles'], array_map(
            static fn (array $cuenta): array => [$cuenta['nombre'], $cuenta['email'], implode(', ', $cuenta['roles'])],
            $resultado['cuentas'],
        ));
        $this->command?->table(['Sección', 'ID', 'Ruta de resultados'], array_map(
            static fn (int $id, string $nombre): array => [$nombre, $id, '/investigacion-formativa/director/secciones/'.$id.'/resultados'],
            $resultado['secciones'], array_keys($resultado['secciones']),
        ));
        $this->command?->table(['Dato de muestra', 'Cantidad'], [
            ['Grupos', count($resultado['muestra']['grupos'])],
            ['Entregas', $resultado['muestra']['entregas']],
            ['Evaluaciones', $resultado['muestra']['evaluaciones']],
            ['Respuestas de encuesta', $resultado['muestra']['respuestas']],
            ['Aprobaciones', $resultado['muestra']['aprobaciones']],
            ['Selección vigente', $resultado['muestra']['seleccion'] === null ? 0 : 1],
        ]);
        if ($directorio = getenv('IF_DEMO_EXPORT_DIR')) {
            $this->exportar($directorio, $resultado);
        }
        $this->command?->warn('Los archivos de las entregas son fixtures locales. La integración de archivos en producción sigue pendiente.');
    }

    /**
     * @return array{
     *     modo: string,
     *     cuentas: list<array>,
     *     secciones: array<string, int>,
     *     lote: string,
     *     muestra: array{
     *         grupos: array<string, array{id_grupo: int, id_proyecto: int}>,
     *         entregas: int,
     *         evaluaciones: int,
     *         respuestas: int,
     *         aprobaciones: int,
     *         seleccion: int|null
     *     }
     * }
     */
    private function crearDatos(string $password, string $modo): array
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
        $lineas = [];
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
            $lineas[$indice] = $this->insertar('core.linea_investigacion', 'id_linea', [
                'id_escuela' => $escuelas[$indice], 'codigo' => 'IFD-'.$lote.'-'.$codigo,
                'nombre' => 'Línea de demostración '.$codigo, 'estado' => 'Activo',
            ]);
        }

        // Curso sin Investigación Formativa dentro del mismo plan y periodo.
        // Sirve como experiencia curricular implementada que no aplica el
        // módulo y permite comprobar el denominador del indicador 3.1.
        $cursoNoIf = $this->insertar('core.curso', 'id_curso', [
            'codigo' => 'IFD-'.$lote.'-C', 'nombre' => 'Gestión de proyectos de demostración C',
            'creditos' => 2, 'estado' => 'Activo',
        ]);
        $cursos[2] = $this->insertar('core.curso_plan', 'id_curso_plan', [
            'id_plan' => $planes[0], 'id_curso' => $cursoNoIf, 'ciclo' => 6,
            'tipo' => 'Obligatorio', 'id_area' => $area, 'creditos_requeridos' => 0,
        ]);

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
        $idTipoProducto = $this->insertar('investigacion_formativa.tipo_producto', 'id_tipo_producto', [
            'nombre' => 'Informe de demostración '.$lote,
        ]);

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
            $cuentas[$nombre] = [
                'nombre' => $nombre,
                'email' => $email,
                'roles' => $roles,
                'user' => $user,
                'id_usuario' => $usuario,
            ];
        }

        $secciones = [];
        foreach ([
            'completa' => [0, 1, 'docente-a', 'Activa', 0],
            'incompleta' => [0, 1, 'docente-a', 'Activa', 0],
            'deshabilitada' => [0, 1, 'docente-a', 'Activa', 2],
            'historica' => [0, 0, 'docente-a', 'Cerrada', 0],
            'otra-escuela' => [1, 1, 'docente-b', 'Activa', 1],
        ] as $nombre => [$escuela, $periodo, $docente, $estado, $curso]) {
            $id = $this->insertar('core.seccion', 'id_seccion', [
                'id_periodo' => $periodos[$periodo], 'id_curso_plan' => $cursos[$curso],
                'codigo' => 'IFD-'.$lote.'-'.$nombre, 'capacidad' => 30,
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

        $muestra = $this->crearDatosMuestra(
            $modo,
            $lote,
            $actor,
            $lineas[0],
            $idTipoProducto,
            $secciones,
            $cuentas,
            $estudiantes,
        );

        return [
            'modo' => $modo,
            'cuentas' => array_values($cuentas),
            'secciones' => $secciones,
            'lote' => $lote,
            'muestra' => $muestra,
        ];
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
            'version' => 2,
            'modo' => $resultado['modo'],
            'lote' => $resultado['lote'],
            'cuentas' => $cuentas,
            'secciones' => $resultado['secciones'],
            'muestra' => $resultado['muestra'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->command?->info('Ejemplos CSV y datos de acceso sin contraseña: '.$destino);
    }

    /**
     * Crea los datos de muestra a través de los servicios normales del módulo.
     *
     * El modo workflow crea grupos, entregas, evaluaciones y la encuesta con
     * respuestas, pero no registra decisiones. El modo showcase agrega dos
     * aprobaciones y una selección vigente. Las entregas y sus archivos son
     * fixtures locales porque el módulo aún no expone un servicio de escritura
     * de entregas en producción.
     *
     * @return array{
     *     grupos: array<string, array{id_grupo: int, id_proyecto: int}>,
     *     entregas: int,
     *     evaluaciones: int,
     *     respuestas: int,
     *     aprobaciones: int,
     *     seleccion: int|null
     * }
     */
    private function crearDatosMuestra(
        string $modo,
        string $lote,
        ActorInvestigacion $actor,
        int $idLinea,
        int $idTipoProducto,
        array $secciones,
        array $cuentas,
        array $estudiantes,
    ): array {
        $acceso = app(AccesoInvestigacionService::class);
        $gruposService = app(GrupoService::class);
        $evaluacionService = app(EvaluacionService::class);
        $decisionService = app(DecisionTrabajoService::class);
        $encuestaService = app(EncuestaService::class);

        $seccionCompleta = $secciones['completa'];

        // 1. Crear grupos y proyectos a través del servicio normal.
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

        $gruposCreados = [];
        foreach ($grupos as $grupo) {
            $actorGrupo = $acceso->resolver($cuentas[$grupo['coordinador']]['user']);

            $resultado = $gruposService->crear($actorGrupo, $seccionCompleta, [
                'nombre' => $grupo['nombre'],
                'titulo' => $grupo['titulo'],
                'integrante_ids' => array_map(
                    function (string $nombre) use ($estudiantes): int {
                        return (int) $estudiantes[$nombre];
                    },
                    $grupo['integrantes'],
                ),
                'id_linea' => $idLinea,
                'id_tipo_producto' => $idTipoProducto,
            ]);

            $gruposCreados[$grupo['nombre']] = [
                'id_grupo' => (int) $resultado->id_grupo,
                'id_proyecto' => (int) $resultado->id_proyecto,
            ];
        }

        // 2. Fixtures de entrega y archivo. El módulo no tiene escritura de
        // entregas en producción; solo se insertan estas filas de soporte.
        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');

        if ($idModulo === null) {
            throw new RuntimeException('No se encontró el módulo Investigación Formativa.');
        }

        $entregables = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', $seccionCompleta)
            ->orderBy('orden')
            ->get(['id_entregable', 'orden']);

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

        $entregasCreadas = 0;
        $evaluacionesCreadas = 0;

        foreach ($grupos as $indiceGrupo => $grupo) {
            $idProyecto = $gruposCreados[$grupo['nombre']]['id_proyecto'];
            $idUsuarioSubida = (int) $cuentas[$grupo['coordinador']]['id_usuario'];
            $nivel = $indiceGrupo === 0 ? 4 : 3;

            foreach ($entregables as $entregable) {
                $nombreArchivo = 'muestra-proyecto-'.$idProyecto.'-u'.(int) $entregable->orden.'.pdf';
                $idArchivo = DB::table('comun.archivo')->insertGetId([
                    'id_modulo' => (int) $idModulo,
                    'nombre_original' => $nombreArchivo,
                    'ruta_almacenamiento' => 'investigacion-formativa/test/'.$lote.'/'.$nombreArchivo,
                    'tipo_mime' => 'application/pdf',
                    // El fixture no tiene archivo almacenado: tamaño cero y sin hash.
                    'tamano_bytes' => 0,
                    'hash_sha256' => null,
                    'subido_por' => $idUsuarioSubida,
                ], 'id_archivo');

                $idEntrega = DB::table('investigacion_formativa.entrega')->insertGetId([
                    'id_proyecto' => $idProyecto,
                    'id_entregable' => (int) $entregable->id_entregable,
                    'version' => 1,
                    'id_archivo' => (int) $idArchivo,
                ], 'id_entrega');
                $entregasCreadas++;

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
                $evaluacionesCreadas++;
            }
        }

        // 3. Solo el modo showcase registra decisiones docentes.
        $aprobaciones = 0;
        $seleccion = null;

        if ($modo === 'showcase') {
            foreach ($gruposCreados as $grupoCreado) {
                $decisionService->aprobar($actor, $grupoCreado['id_proyecto'], 0);
                $aprobaciones++;
            }

            if ($gruposCreados !== []) {
                $primerProyecto = $gruposCreados['Grupo Alfa']['id_proyecto'];
                $decisionService->seleccionar($actor, $primerProyecto, 0);
                $seleccion = $primerProyecto;
            }
        }

        // 4. Crear y publicar la encuesta; registrar respuestas por servicio.
        $actorDirector = $acceso->resolver($cuentas['director-a']['user']);

        $encuestaResultado = $encuestaService->guardarBorrador(
            $actorDirector,
            $seccionCompleta,
            [
                'Satisfacción general con la experiencia de investigación formativa',
                '¿La orientación del docente responsable fue clara y oportuna?',
            ],
            0,
        );

        $encuestaModel = Encuesta::query()->findOrFail((int) $encuestaResultado->id_encuesta);
        $encuestaService->publicar($actorDirector, $encuestaModel, (int) $encuestaResultado->version);

        $preguntas = $encuestaService->vistaGestor($seccionCompleta)->preguntas;

        $respuestasMuestra = [
            'coordinador' => ['valores' => [5, 4], 'comentario' => 'Respuesta de muestra del estudiante coordinador.'],
            'integrante' => ['valores' => [4, 5], 'comentario' => 'Respuesta de muestra del estudiante integrante.'],
            'segundo-grupo' => ['valores' => [3, 4], 'comentario' => 'Respuesta de muestra del estudiante segundo-grupo.'],
        ];

        $respuestasCreadas = 0;
        foreach ($respuestasMuestra as $nombreEstudiante => $respuestaMuestra) {
            if (count($preguntas) !== count($respuestaMuestra['valores'])) {
                throw new RuntimeException('Las respuestas de demostración no coinciden con las preguntas publicadas.');
            }

            $respuestas = [];
            foreach ($preguntas as $indice => $pregunta) {
                $respuestas[] = [
                    'id_pregunta' => $pregunta->id_pregunta,
                    'valor' => $respuestaMuestra['valores'][$indice],
                ];
            }

            $actorEstudiante = $acceso->resolver($cuentas[$nombreEstudiante]['user']);
            $resultado = $encuestaService->responder(
                $actorEstudiante,
                $encuestaModel,
                $respuestas,
                $respuestaMuestra['comentario'],
            );

            if (! $resultado->ya_respondida) {
                $respuestasCreadas++;
            }
        }

        return [
            'grupos' => $gruposCreados,
            'entregas' => $entregasCreadas,
            'evaluaciones' => $evaluacionesCreadas,
            'respuestas' => $respuestasCreadas,
            'aprobaciones' => $aprobaciones,
            'seleccion' => $seleccion,
        ];
    }

    private function insertar(string $tabla, string $clave, array $datos): int
    {
        return (int) DB::table($tabla)->insertGetId($datos, $clave);
    }
}
