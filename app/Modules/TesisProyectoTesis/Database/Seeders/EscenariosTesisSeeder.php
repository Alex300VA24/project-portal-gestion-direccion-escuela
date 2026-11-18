<?php

namespace App\Modules\TesisProyectoTesis\Database\Seeders;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\ActividadCronograma;
use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\CriterioRubrica;
use App\Modules\TesisProyectoTesis\Models\DetalleEvaluacion;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\HistorialEstado;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ProyectoEstudiante;
use App\Modules\TesisProyectoTesis\Models\ReporteSimilitud;
use App\Modules\TesisProyectoTesis\Models\Resolucion;
use App\Modules\TesisProyectoTesis\Models\ResultadoJurado;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Models\Rubrica;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Models\TipoSolicitud;
use App\Modules\TesisProyectoTesis\Services\ArchivoService;
use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\NotificacionService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use App\Modules\TesisProyectoTesis\Support\PdfSimple;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SOLO DESARROLLO Y PRUEBAS. Deja once proyectos PT-DEMO-NN en distintos puntos del flujo,
 * cada uno con sus PDF de ejemplo reales y el aviso de la acción pendiente.
 * Idempotente: al volver a ejecutarlo borra los PT-DEMO-* y los proyectos que las cuentas de los
 * escenarios (nuevo@, est01…11@) crearon durante un ensayo, con sus archivos y avisos, y recrea los PT-DEMO-*.
 * No toca los proyectos de las demás cuentas (p. ej. PT-2026-001 de alumno@portal.local).
 *
 * php artisan db:seed --class="App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder"
 */
final class EscenariosTesisSeeder extends Seeder
{
    public const PREFIJO = 'PT-DEMO-';

    /** Cuentas de estudiante de los escenarios: correo => [nombres, paterno, materno, código, dni]. */
    public const ESTUDIANTES = [
        'nuevo@portal.local' => ['Valeria', 'Campos', 'Rojas', '1027005001', '7100000'],
        'est01@portal.local' => ['Kevin', 'Aguilar', 'Ríos', '1027005011', '7100001'],
        'est02@portal.local' => ['Sofía', 'Benites', 'León', '1027005012', '7100002'],
        'est03@portal.local' => ['Marco', 'Gutiérrez', 'Paz', '1027005013', '7100003'],
        'est04@portal.local' => ['Camila', 'Vásquez', 'Ortiz', '1027005014', '7100004'],
        'est05@portal.local' => ['Andrea', 'Quispe', 'Mendoza', '1027005015', '7100005'],
        'est06@portal.local' => ['Luis', 'Fernández', 'Cruz', '1027005016', '7100006'],
        'est07@portal.local' => ['Renzo', 'Cárdenas', 'Pérez', '1027005017', '7100007'],
        'est08@portal.local' => ['Milagros', 'Flores', 'Arteaga', '1027005018', '7100008'],
        'est09@portal.local' => ['Jorge', 'Salinas', 'Mori', '1027005019', '7100009'],
        'est10@portal.local' => ['Ximena', 'Rojas', 'Lozano', '1027005020', '7100010'],
        'est11@portal.local' => ['Bruno', 'Medina', 'Saldaña', '1027005021', '7100011'],
    ];

    private ArchivoService $archivos;

    private NotificacionService $avisos;

    private int $idPeriodo;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('EscenariosTesisSeeder se omite: solo para local o testing.');

            return;
        }

        $this->call(TesisDatabaseSeeder::class);

        $this->archivos = app(ArchivoService::class);
        $this->avisos = app(NotificacionService::class);
        $this->idPeriodo = (int) app(CatalogoService::class)->periodoActual()->id_periodo;

        $demo = app(DemoTesisSeeder::class);
        foreach (self::ESTUDIANTES as $correo => [$n, $p, $m, $codigo, $dni]) {
            $demo->estudiante($correo, $n, $p, $m, $codigo, $dni);
        }

        DB::transaction(function () {
            $this->limpiar();
            $this->e01();
            $this->e02();
            $this->e03();
            $this->e04();
            $this->e05();
            $this->e06();
            $this->e07();
            $this->e08();
            $this->e09();
            $this->e10();
            $this->e11();
        });
    }

    /**
     * Borra los proyectos PT-DEMO-* y los de ensayo de las cuentas de los escenarios (cualquier proyecto
     * con un integrante de self::ESTUDIANTES), con todo lo que cuelga de ellos (en orden por las FK RESTRICT).
     */
    public function limpiar(): void
    {
        $idsEstudiante = DB::table('core.estudiante as e')
            ->join('seguridad.usuario as u', 'u.id_persona', '=', 'e.id_persona')
            ->whereIn(DB::raw('LOWER(u.correo_institucional)'), array_keys(self::ESTUDIANTES))
            ->pluck('e.id_estudiante')->all();

        $ids = Proyecto::query()->where('codigo', 'like', self::PREFIJO.'%')->pluck('id_proyecto')
            ->merge(ProyectoEstudiante::query()->whereIn('id_estudiante', $idsEstudiante)->pluck('id_proyecto'))
            ->map(fn ($v) => (int) $v)->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $entregas = Entrega::query()->whereIn('id_proyecto', $ids)->pluck('id_entrega')->all();
        $solicitudes = Solicitud::query()->whereIn('id_proyecto', $ids)->pluck('id_solicitud')->all();
        $archivos = array_merge(
            Proyecto::query()->whereIn('id_proyecto', $ids)->pluck('id_archivo_documento')->all(),
            Entrega::query()->whereIn('id_entrega', $entregas)->pluck('id_archivo')->all(),
            ReporteSimilitud::query()->whereIn('id_entrega', $entregas)->pluck('id_archivo')->all(),
            Resolucion::query()->whereIn('id_solicitud', $solicitudes)->pluck('id_archivo')->all(),
            Acta::query()->whereIn('id_proyecto', $ids)->pluck('id_archivo_firmado')->all(),
        );

        // Primero las observaciones: cuelgan de una revisión (CASCADE) y apuntan a la entrega donde se corrigieron
        // (SET NULL). Si el proyecto las borra en cascada, PostgreSQL puede aplicar el SET NULL después de borrar la
        // revisión y fallar la FK según el orden físico de las filas (falla intermitente al reiniciar).
        Observacion::query()->whereIn('id_revision', Revision::query()->whereIn('id_entrega', $entregas)->select('id_revision'))->delete();
        Acta::query()->whereIn('id_proyecto', $ids)->delete();
        Sustentacion::query()->whereIn('id_proyecto', $ids)->delete(); // resultados en cascada
        AsesorProyecto::query()->whereIn('id_proyecto', $ids)->delete();
        Resolucion::query()->whereIn('id_solicitud', $solicitudes)->delete();
        Solicitud::query()->whereIn('id_proyecto', $ids)->delete();
        $this->avisos->eliminarDeProyectos($ids);
        Proyecto::query()->whereIn('id_proyecto', $ids)->delete(); // resto en cascada

        $this->archivos->eliminar(array_values(array_unique(array_filter($archivos))));
    }

    // ----- Escenarios -----

    /** E01 · Registrado, sin solicitud: el Director solicita el asesor. */
    private function e01(): void
    {
        $p = $this->proyecto('01', 'Plataforma de analítica de aprendizaje con tableros para docentes de educación secundaria', ['est01@portal.local'], 'LI-SI', 3);
        $this->aviso(['director@portal.local'], 'proyecto.registrado', "Nuevo proyecto {$p->codigo}", 'Requiere solicitar asesor al Decanato.', $p);
    }

    /** E02 · Registrado con solicitud en el Decanato: la Secretaría registra la resolución. */
    private function e02(): void
    {
        $p = $this->proyecto('02', 'Sistema de visión artificial para clasificar palta Hass por calibre en una agroexportadora', ['est02@portal.local'], 'LI-IA', 6);
        $this->solicitud($p, 'Pendiente', 'DOC-102');
        $this->aviso(['secretaria@portal.local'], 'asesor.solicitado', "Solicitud de asesor enviada · {$p->codigo}", 'Registra la resolución del Decanato cuando llegue.', $p);
    }

    /** E03 · En desarrollo con un avance pendiente: el asesor lo revisa. */
    private function e03(): void
    {
        $p = $this->proyecto('03', 'Aplicación web para la gestión de citas en el consultorio odontológico de una clínica universitaria', ['est03@portal.local', 'alumno3@portal.local'], 'LI-SW', 40);
        $this->asesor($p);
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO]);
        $v1 = $this->entrega($p, 1, 'Avance', 4, 'SinObservaciones', 'est03@portal.local', 20);
        $this->revisionAvance($v1, 'Conforme');
        $this->entrega($p, 2, 'Avance', 5, 'Pendiente', 'alumno3@portal.local', 1, 'Se completó la matriz de consistencia.');
        $this->aviso(['asesor@portal.local'], 'entrega.subida', "Nueva entrega · {$p->codigo} v2", 'Los estudiantes subieron el avance de la semana 5.', $p);
    }

    /** E04 · En desarrollo con observaciones del asesor: el estudiante responde (y una ya respondida para levantar). */
    private function e04(): void
    {
        $p = $this->proyecto('04', 'Sistema de recomendación de cursos electivos basado en el historial académico de los estudiantes', ['est04@portal.local'], 'LI-IA', 45);
        $this->asesor($p);
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO]);
        $v1 = $this->entrega($p, 1, 'Avance', 5, 'Observada', 'est04@portal.local', 10);
        $r = $this->revisionAvance($v1, 'Observado');
        $this->observacion($r, 'Matriz de consistencia', 'Define las dimensiones de la variable «rendimiento académico» y relaciona cada una con un indicador medible.');
        $this->observacion($r, 'Problema', 'El objetivo específico 2 no responde al problema planteado. Reformúlalo.');
        $v2 = $this->entrega($p, 2, 'Avance', 6, 'Pendiente', 'est04@portal.local', 2);
        $this->observacion($r, 'Marco teórico', 'Cita las fuentes del marco teórico en formato APA 7.', 'Respondida', 'Se corrigieron las 12 citas del capítulo II al formato APA 7.', $v2);
        $this->aviso(['est04@portal.local'], 'avance.revisado', 'El asesor revisó tu entrega v1', '3 observaciones por levantar.', $p);
        $this->aviso(['asesor@portal.local'], 'observacion.respondida', "Observación respondida · {$p->codigo}", 'El estudiante indicó cómo corrigió la observación. Revísala y dala por levantada.', $p);
    }

    /** E05 · Versión final entregada: el asesor verifica con la rúbrica y pasa Turnitin. */
    private function e05(): void
    {
        $p = $this->proyecto('05', 'Modelo de aprendizaje profundo para detectar fraude en transacciones de una cooperativa de ahorro', ['est05@portal.local'], 'LI-IA', 70);
        $this->asesor($p);
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO]);
        foreach ([1 => 'SinObservaciones', 2 => 'SinObservaciones', 3 => 'SinObservaciones'] as $v => $estado) {
            $this->revisionAvance($this->entrega($p, $v, 'Avance', $v + 3, $estado, 'est05@portal.local', 30 - $v * 7), 'Conforme');
        }
        $this->entrega($p, 4, 'Final', 12, 'Pendiente', 'est05@portal.local', 1, 'Versión final del F1 para verificación.');
        $this->aviso(['asesor@portal.local'], 'entrega.subida', "Nueva entrega · {$p->codigo} v4", 'Los estudiantes subieron la versión final.', $p);
    }

    /** E06 · Verificado por el asesor (rúbrica + Turnitin): el Director asigna el jurado. */
    private function e06(): void
    {
        $p = $this->proyecto('06', 'Chatbot para la atención de consultas de matrícula en la Escuela de Ingeniería Informática', ['est06@portal.local'], 'LI-SW', 80);
        $this->asesor($p);
        $this->etapaAsesorCompleta($p, 'Proyecto', 'est06@portal.local', 14.0);
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO, Estados::VERIFICADO_ASESOR]);
        $this->aviso(['director@portal.local'], 'asesor.conformidad', "Conformidad del asesor · {$p->codigo}", 'Listo para asignar jurado (Turnitin 14.00 %).', $p);
    }

    /** E07 · En revisión del jurado (Proyecto): Presidente conforme; Secretario y Vocal pendientes. */
    private function e07(): void
    {
        $p = $this->proyecto('07', 'Sistema de información para el control de inventario en mypes de calzado de El Porvenir', ['est07@portal.local'], 'LI-SI', 90);
        $this->asesor($p);
        $final = $this->etapaAsesorCompleta($p, 'Proyecto', 'est07@portal.local', 11.5);
        $this->jurado($p);
        $this->verificacion($final, 'DOC-102', 'Jurado', 'Proyecto');
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO, Estados::VERIFICADO_ASESOR, Estados::EN_REVISION_JURADO]);
        $this->aviso(['jurado2@portal.local', 'jurado3@portal.local'], 'jurado.asignado', "Fuiste asignado como jurado · {$p->codigo}", "Tienes un proyecto por revisar con la rúbrica: {$p->titulo}", $p);
    }

    /** E08 · Etapa Tesis (semana ≥ 8): avance F2 por revisar (asesor) y revisión intermedia habilitada (jurado). */
    private function e08(): void
    {
        $p = $this->proyecto('08', 'Aplicación móvil para el seguimiento nutricional de pacientes con diabetes tipo 2', ['est08@portal.local'], 'LI-SW', 150);
        $this->asesor($p);
        $final = $this->etapaAsesorCompleta($p, 'Proyecto', 'est08@portal.local', 9.0);
        $this->jurado($p);
        $this->juradoConforme($final, 'Proyecto');
        $this->dictamen($p);
        $this->pasarATesis($p);
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO, Estados::VERIFICADO_ASESOR, Estados::EN_REVISION_JURADO, Estados::APROBADO, Estados::EN_TESIS]);
        $v1 = $this->entrega($p, 1, 'Avance', 7, 'SinObservaciones', 'est08@portal.local', 15);
        $this->revisionAvance($v1, 'Conforme');
        $this->entrega($p, 2, 'Avance', 9, 'Pendiente', 'est08@portal.local', 1, 'Resultados preliminares del piloto.');
        $this->aviso(['asesor@portal.local'], 'entrega.subida', "Nueva entrega · {$p->codigo} v2", 'Los estudiantes subieron el avance de la semana 9.', $p);
        $this->aviso(['jurado1@portal.local', 'jurado2@portal.local', 'jurado3@portal.local'], 'jurado.revision_intermedia', "Revisión intermedia · {$p->codigo}",
            'La tesis llegó a la semana '.Parametros::SEMANA_REVISION_INTERMEDIA.'. Deja tus observaciones con la rúbrica como referencia.', $p);
    }

    /** E09 · Tesis con los 3 jurados conformes: la Secretaría programa la sustentación. */
    private function e09(): void
    {
        $p = $this->tesisLista('09', 'Sistema de gestión documental con firma digital para la Mesa de Partes de una municipalidad', 'est09@portal.local', 'LI-SI', 200);
        $this->aviso(['secretaria@portal.local'], 'tesis.lista', "Programar sustentación · {$p->codigo}", 'La tesis tiene la conformidad de los tres jurados.', $p);
    }

    /** E10 · Sustentación programada hoy: Presidente ya registró; Secretario y Vocal registran su resultado. */
    private function e10(): void
    {
        $p = $this->tesisLista('10', 'Plataforma de telemedicina para el monitoreo remoto de gestantes en zonas rurales de La Libertad', 'est10@portal.local', 'LI-SW', 210);
        $s = $this->sustentacion($p, Carbon::today()->setTime(9, 0), 'LAB-8', 'Programada');
        $this->resultado($s, 'DOC-102', 'Aprobatorio');
        $this->historial($p, [Estados::PROGRAMADO]);
        $this->aviso(['jurado2@portal.local', 'jurado3@portal.local'], 'sustentacion.programada', "Sustentación programada · {$p->codigo}",
            Carbon::today()->format('d/m/Y').' a las 09:00 · LAB-8 (presencial).', $p);
    }

    /** E11 · Sustentada por unanimidad: acta generada, la Secretaría sube la firmada e informa al Director. */
    private function e11(): void
    {
        $p = $this->tesisLista('11', 'Sistema de inteligencia de negocios para la toma de decisiones en una distribuidora de alimentos', 'est11@portal.local', 'LI-SI', 220);
        $s = $this->sustentacion($p, Carbon::yesterday()->setTime(10, 0), 'INFO-5', 'Realizada');
        foreach (['DOC-102', 'DOC-103', 'DOC-104'] as $d) {
            $this->resultado($s, $d, 'Aprobatorio');
        }
        $s->update(['total_aprobatorios' => 3, 'resultado' => 'Aprobado', 'modo_resultado' => 'Unanimidad', 'hora_fin' => '11:30']);
        $acta = Acta::create([
            'tipo' => 'Sustentacion', 'id_proyecto' => $p->id_proyecto, 'id_sustentacion' => $s->id_sustentacion,
            'numero' => 'ACT-DEMO-11', 'resultado' => 'Aprobado por unanimidad', 'condicion' => 'Expedito', 'emitida_en' => Carbon::yesterday()->setTime(11, 40),
        ]);
        $p->update(['fecha_aprobacion_tesis' => Carbon::yesterday()->toDateString()]);
        $this->historial($p, [Estados::PROGRAMADO, Estados::SUSTENTADO]);
        $this->aviso(['secretaria@portal.local'], 'acta.generada', "Acta N.° {$acta->numero} · {$p->codigo}",
            'Aprobado por unanimidad. Imprime el acta para la firma y súbela escaneada.', $p);
    }

    // ----- Bloques de construcción -----

    /** @param list<string> $alumnos */
    private function proyecto(string $n, string $titulo, array $alumnos, string $linea, int $diasAtras): Proyecto
    {
        $registro = Carbon::now()->subDays($diasAtras);
        $p = Proyecto::create([
            'codigo' => self::PREFIJO.$n,
            'titulo' => $titulo,
            'resumen' => 'Proyecto de ejemplo para pruebas manuales del módulo de tesis (escenario '.$n.').',
            'id_linea' => (int) DB::table('core.linea_investigacion')->where('codigo', $linea)->value('id_linea'),
            'id_periodo_registro' => $this->idPeriodo,
            'etapa' => Estados::ETAPA_PROYECTO,
            'estado' => Estados::REGISTRADO,
            'enfoque' => 'Cuantitativo',
            'tipo_investigacion' => 'Aplicada',
            'diseno' => 'Preexperimental',
            'lugar_ejecucion' => 'Escuela de Ingeniería Informática',
            'institucion' => 'Universidad Nacional de Trujillo',
            'duracion_meses' => 8,
            'financiamiento' => 'Autofinanciado',
        ]);
        $p->forceFill(['registrado_en' => $registro])->save();

        foreach ($alumnos as $i => $correo) {
            ProyectoEstudiante::create(['id_proyecto' => $p->id_proyecto, 'id_estudiante' => $this->estudiante($correo), 'orden_autor' => $i + 1,
                'estado' => 'Activo', 'fecha_incorporacion' => $registro->toDateString()]);
        }

        ActividadCronograma::create(['id_proyecto' => $p->id_proyecto, 'descripcion' => 'Revisión bibliográfica', 'orden' => 1,
            'fecha_inicio' => $registro->toDateString(), 'fecha_fin' => $registro->copy()->addMonth()->toDateString(), 'horas_semanales' => 8]);

        $p->update(['id_archivo_documento' => $this->pdf($alumnos[0], "F1_{$p->codigo}.pdf", "Proyecto de tesis (F1) · {$p->codigo}", [$titulo])]);

        HistorialEstado::create(['id_proyecto' => $p->id_proyecto, 'estado_anterior' => null, 'estado_nuevo' => Estados::REGISTRADO,
            'cambiado_por' => $this->usuario($alumnos[0]), 'cambiado_en' => $registro, 'observacion' => 'Registro del proyecto (F1).']);

        return $p->refresh();
    }

    /** Agrega los cambios de estado (espaciados en el tiempo) y deja el proyecto en el último. */
    private function historial(Proyecto $p, array $estados): void
    {
        $anterior = $p->historial()->get()->last();
        $desde = Carbon::parse($anterior->cambiado_en);
        $hasta = Carbon::now()->subHour();
        $paso = max(1, (int) floor($desde->diffInMinutes($hasta) / (count($estados) + 1)));

        foreach ($estados as $i => $estado) {
            HistorialEstado::create(['id_proyecto' => $p->id_proyecto, 'estado_anterior' => $anterior->estado_nuevo, 'estado_nuevo' => $estado,
                'cambiado_por' => $this->usuario('secretaria@portal.local'), 'cambiado_en' => $desde->copy()->addMinutes($paso * ($i + 1)),
                'observacion' => 'Escenario demo.']);
            $anterior = (object) ['estado_nuevo' => $estado];
        }

        $p->update(['estado' => end($estados)]);
    }

    private function solicitud(Proyecto $p, string $estado, ?string $sugerido = null): Solicitud
    {
        return Solicitud::create([
            'id_proyecto' => $p->id_proyecto,
            'id_tipo_solicitud' => (int) TipoSolicitud::query()->firstOrCreate(['nombre' => Parametros::TIPO_SOLICITUD_ASESOR])->id_tipo_solicitud,
            'presentada_por' => $this->usuario('director@portal.local'),
            'estado' => $estado,
            'destinatario' => 'Decano de la Facultad de Ciencias Físicas y Matemáticas',
            'destinatario_area' => 'Decanato',
            'asunto' => 'Solicitud de designación de asesor de tesis · '.$p->codigo,
            'detalle' => "Solicito la designación de un docente asesor para el proyecto «{$p->titulo}».",
            'id_docente_sugerido' => $sugerido ? $this->docente($sugerido) : null,
            'atendida_en' => $estado === 'Atendida' ? Carbon::now()->subDays(30) : null,
        ]);
    }

    private function asesor(Proyecto $p, string $codigo = 'DOC-101'): void
    {
        $s = $this->solicitud($p, 'Atendida');
        $numero = 'RES-DEMO-'.substr($p->codigo, -2).'-2026-FCFYM';
        $r = Resolucion::create([
            'numero' => $numero, 'fecha' => Carbon::now()->subDays(30)->toDateString(), 'asunto' => 'Designación de asesor de tesis',
            'organo_emisor' => 'Decanato · FCFYM', 'dirigido_a' => 'Director de Escuela', 'id_solicitud' => $s->id_solicitud,
            'registrado_por' => $this->usuario('secretaria@portal.local'),
            'id_archivo' => $this->pdf('secretaria@portal.local', "Resolucion_{$numero}.pdf", "Resolución N.° {$numero}",
                ['Decanato de la Facultad de Ciencias Físicas y Matemáticas', "Se designa asesor del proyecto {$p->codigo}: {$p->titulo}."]),
        ]);
        AsesorProyecto::create(['id_proyecto' => $p->id_proyecto, 'id_docente' => $this->docente($codigo), 'tipo' => 'Asesor',
            'id_resolucion' => $r->id_resolucion, 'estado' => 'Activo', 'fecha_inicio' => $r->fecha]);
    }

    private function entrega(Proyecto $p, int $version, string $tipo, int $semana, string $estado, string $alumno, int $diasAtras, ?string $comentario = null): Entrega
    {
        $e = Entrega::create([
            'id_proyecto' => $p->id_proyecto, 'etapa' => $p->etapa, 'version' => $version, 'tipo' => $tipo, 'numero_semana' => $semana,
            'comentario' => $comentario, 'entregado_por' => $this->estudiante($alumno), 'estado' => $estado,
            'id_archivo' => $this->pdf($alumno, "{$p->codigo}_{$p->etapa}_v{$version}.pdf", ($tipo === 'Final' ? 'Versión final' : "Avance semana {$semana}")." · {$p->codigo} v{$version}",
                [$p->titulo, 'Documento de ejemplo generado por el seeder de escenarios.']),
        ]);
        $e->forceFill(['entregado_en' => Carbon::now()->subDays($diasAtras)])->save();

        return $e->refresh();
    }

    private function revisionAvance(Entrega $e, string $dictamen): Revision
    {
        return Revision::create(['id_entrega' => $e->id_entrega, 'id_docente' => $this->docente('DOC-101'), 'tipo' => 'Avance', 'rol_revisor' => 'Asesor',
            'dictamen' => $dictamen, 'revisado_en' => Carbon::parse($e->entregado_en)->addDay()]);
    }

    private function observacion(Revision $r, string $seccion, string $texto, string $estado = 'Pendiente', ?string $respuesta = null, ?Entrega $correccion = null): void
    {
        Observacion::create(['id_revision' => $r->id_revision, 'seccion_documento' => $seccion, 'descripcion' => $texto, 'estado' => $estado,
            'respuesta_estudiante' => $respuesta, 'id_entrega_correccion' => $correccion?->id_entrega, 'respondida_en' => $respuesta ? Carbon::now()->subDay() : null]);
    }

    /** Verificación conforme con la rúbrica (todos los criterios de la etapa en «cumple»). */
    private function verificacion(Entrega $e, string $codigo, string $rol, string $etapa): Revision
    {
        $rubrica = Rubrica::query()->where('estado', 'Vigente')->firstOrFail();
        $r = Revision::create(['id_entrega' => $e->id_entrega, 'id_docente' => $this->docente($codigo), 'id_rubrica' => $rubrica->id_rubrica,
            'tipo' => 'Verificacion', 'rol_revisor' => $rol, 'dictamen' => 'Conforme', 'cumple_todo' => true, 'conforme' => true,
            'revisado_en' => Carbon::parse($e->entregado_en)->addDays(2)]);

        CriterioRubrica::query()->where('id_rubrica', $rubrica->id_rubrica)->whereIn('aplica_a', ['Ambas', $etapa])
            ->pluck('id_criterio')->each(fn ($id) => DetalleEvaluacion::create(['id_revision' => $r->id_revision, 'id_criterio' => $id, 'cumple' => true, 'no_aplica' => false]));

        return $r;
    }

    /** Avances + versión final + rúbrica conforme del asesor + Turnitin apto. Devuelve la versión final. */
    private function etapaAsesorCompleta(Proyecto $p, string $etapa, string $alumno, float $similitud): Entrega
    {
        $this->revisionAvance($this->entrega($p, 1, 'Avance', 4, 'SinObservaciones', $alumno, 40), 'Conforme');
        $final = $this->entrega($p, 2, 'Final', 12, 'Culminada', $alumno, 20);
        $this->verificacion($final, 'DOC-101', 'Asesor', $etapa);
        ReporteSimilitud::create(['id_entrega' => $final->id_entrega, 'porcentaje' => $similitud, 'herramienta' => 'Turnitin', 'resultado' => 'Apto',
            'id_docente' => $this->docente('DOC-101'), 'indicaciones' => 'Similitud dentro del límite.',
            'id_archivo' => $this->pdf('asesor@portal.local', "Turnitin_{$p->codigo}_{$etapa}.pdf", "Reporte de similitud · {$p->codigo}",
                ["Porcentaje de similitud: {$similitud} %", 'Resultado: apto (límite '.Parametros::SIMILITUD_MAXIMA.' %).'])]);

        return $final;
    }

    private function jurado(Proyecto $p): void
    {
        foreach (['Presidente' => 'DOC-102', 'Secretario' => 'DOC-103', 'Vocal' => 'DOC-104'] as $cargo => $codigo) {
            JuradoProyecto::create(['id_proyecto' => $p->id_proyecto, 'id_docente' => $this->docente($codigo), 'cargo' => $cargo, 'estado' => 'Activo',
                'asignado_por' => $this->usuario('director@portal.local'), 'fecha_asignacion' => Carbon::now()->subDays(15)->toDateString()]);
        }
    }

    private function juradoConforme(Entrega $final, string $etapa): void
    {
        foreach (['DOC-102', 'DOC-103', 'DOC-104'] as $codigo) {
            $this->verificacion($final, $codigo, 'Jurado', $etapa);
        }
    }

    private function dictamen(Proyecto $p): void
    {
        Acta::create([
            'tipo' => 'Dictamen', 'id_proyecto' => $p->id_proyecto, 'numero' => 'DIC-DEMO-'.substr($p->codigo, -2), 'resultado' => 'Aprobado por unanimidad',
            'emitida_en' => Carbon::now()->subDays(60), 'registrado_por' => $this->usuario('secretaria@portal.local'), 'firmada_en' => Carbon::now()->subDays(58),
            'informado_director_en' => Carbon::now()->subDays(58),
            'id_archivo_firmado' => $this->pdf('secretaria@portal.local', "Dictamen_{$p->codigo}_firmado.pdf", "Dictamen de aprobación del proyecto · {$p->codigo}",
                ['El proyecto reúne las condiciones para ser ejecutado.', 'Firmado por los tres miembros del jurado.']),
        ]);
    }

    private function pasarATesis(Proyecto $p): void
    {
        $p->update(['etapa' => Estados::ETAPA_TESIS, 'fecha_aprobacion_proyecto' => Carbon::now()->subDays(60)->toDateString(), 'id_periodo_tesis' => $this->idPeriodo]);
    }

    /** Proyecto completo hasta «Listo para sustentar» (etapa Tesis con los tres jurados conformes). */
    private function tesisLista(string $n, string $titulo, string $alumno, string $linea, int $diasAtras): Proyecto
    {
        $p = $this->proyecto($n, $titulo, [$alumno], $linea, $diasAtras);
        $this->asesor($p);
        $finalProyecto = $this->etapaAsesorCompleta($p, 'Proyecto', $alumno, 10.0);
        $this->jurado($p);
        $this->juradoConforme($finalProyecto, 'Proyecto');
        $this->dictamen($p);
        $this->pasarATesis($p);
        $p->refresh();
        $finalTesis = $this->etapaAsesorCompleta($p, 'Tesis', $alumno, 12.0);
        $this->juradoConforme($finalTesis, 'Tesis');
        $this->historial($p, [Estados::CON_ASESOR, Estados::EN_DESARROLLO, Estados::VERIFICADO_ASESOR, Estados::EN_REVISION_JURADO,
            Estados::APROBADO, Estados::EN_TESIS, Estados::EN_REVISION_JURADO, Estados::LISTO_SUSTENTAR]);

        return $p->refresh();
    }

    private function sustentacion(Proyecto $p, Carbon $inicio, string $ambiente, string $estado): Sustentacion
    {
        return Sustentacion::create([
            'id_proyecto' => $p->id_proyecto, 'fecha_hora' => $inicio, 'modalidad' => 'Presencial',
            'id_ambiente' => (int) DB::table('core.ambiente')->where('codigo', $ambiente)->value('id_ambiente'),
            'estado' => $estado, 'numero_intento' => 1, 'programada_por' => $this->usuario('secretaria@portal.local'),
        ]);
    }

    private function resultado(Sustentacion $s, string $codigo, string $resultado): void
    {
        $idJurado = JuradoProyecto::query()->where('id_proyecto', $s->id_proyecto)->where('id_docente', $this->docente($codigo))->value('id_jurado');
        ResultadoJurado::create(['id_sustentacion' => $s->id_sustentacion, 'id_jurado' => $idJurado, 'resultado' => $resultado,
            'dictamen' => $resultado, 'propone_mencion' => false, 'registrado_en' => Carbon::now()]);
    }

    /** @param list<string> $correos */
    private function aviso(array $correos, string $evento, string $titulo, string $mensaje, Proyecto $p): void
    {
        $this->avisos->notificar(array_map(fn ($c) => $this->usuario($c), $correos), $evento, $titulo, $mensaje, $p);
    }

    /** @param list<string> $lineas */
    private function pdf(string $correoAutor, string $nombre, string $titulo, array $lineas = []): int
    {
        $contenido = PdfSimple::generar($titulo, [
            'Universidad Nacional de Trujillo · Escuela de Ingeniería Informática',
            '',
            ...$lineas,
            '',
            'Documento de ejemplo (escenarios de prueba). Generado el '.Carbon::now()->format('d/m/Y H:i').'.',
        ]);

        return $this->archivos->guardarContenido($contenido, $nombre, $this->usuario($correoAutor), 'demo');
    }

    private function usuario(string $correo): int
    {
        return (int) DB::table('seguridad.usuario')->where('correo_institucional', $correo)->value('id_usuario');
    }

    private function estudiante(string $correo): int
    {
        return (int) DB::table('core.estudiante as e')->join('seguridad.usuario as u', 'u.id_persona', '=', 'e.id_persona')
            ->where('u.correo_institucional', $correo)->value('e.id_estudiante');
    }

    private function docente(string $codigo): int
    {
        return (int) DB::table('core.docente')->where('codigo_docente', $codigo)->value('id_docente');
    }
}
