<?php

namespace App\Modules\PreMatricula\Database\Seeders;

use App\Models\User;
use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Services\CuentasEstudiantes;
use App\Modules\PreMatricula\Services\PeriodosAcademicos;
use App\Modules\PreMatricula\Services\VentanaPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de demostración para probar Pre Matrícula de punta a punta:
 * malla curricular, períodos 2026-I (cerrado) y 2026-II (en prematrícula, con ventana activa)
 * y la cuenta alumno@portal.local vinculada a un estudiante con historial.
 *
 * Es idempotente y reutiliza lo que ya exista (plan predeterminado, cursos, períodos).
 * Lo único que cambia de un dato existente es el estado del período 2026-II, que pasa a PREMATRICULA.
 *
 * php artisan db:seed --class="App\Modules\PreMatricula\Database\Seeders\PreMatriculaDemoSeeder"
 */
final class PreMatriculaDemoSeeder extends Seeder
{
    private const CORREO_ALUMNO = 'alumno@portal.local';

    private const CODIGO_ALUMNO = '1000000001';

    /** codigo => [nombre, creditos, ciclo, horas T, P, L, prerrequisitos] */
    private const MALLA = [
        'INF101' => ['Introducción a la Ingeniería Informática', 3, 1, 2, 2, 0, []],
        'MAT101' => ['Matemática Básica', 4, 1, 3, 2, 0, []],
        'COM101' => ['Lenguaje y Comunicación', 3, 1, 2, 2, 0, []],
        'INF102' => ['Fundamentos de Programación', 4, 1, 2, 0, 4, []],
        'MAT201' => ['Cálculo I', 4, 2, 3, 2, 0, ['MAT101']],
        'INF201' => ['Programación Orientada a Objetos', 4, 2, 2, 0, 4, ['INF102']],
        'INF202' => ['Matemática Discreta', 3, 2, 2, 2, 0, ['MAT101']],
        'FIS201' => ['Física I', 4, 2, 3, 0, 2, ['MAT101']],
        'MAT301' => ['Cálculo II', 4, 3, 3, 2, 0, ['MAT201']],
        'INF301' => ['Estructura de Datos', 4, 3, 2, 0, 4, ['INF201', 'INF202']],
        'INF302' => ['Base de Datos I', 4, 3, 2, 0, 4, ['INF201']],
        'EST301' => ['Estadística General', 3, 3, 2, 2, 0, ['MAT201']],
        'INF401' => ['Análisis y Diseño de Algoritmos', 4, 4, 3, 0, 2, ['INF301']],
        'INF402' => ['Base de Datos II', 4, 4, 2, 0, 4, ['INF302']],
        'INF403' => ['Ingeniería de Software I', 4, 4, 3, 2, 0, ['INF302']],
        'INF404' => ['Sistemas Operativos', 4, 4, 2, 0, 4, ['INF301']],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $plan = $this->plan();
            $this->malla($plan);
            $anterior = $this->periodo('2026-I', '2026-03-30', '2026-07-31', PeriodosAcademicos::CERRADO, false);
            $actual = $this->periodo('2026-II', '2026-08-31', '2026-12-20', PeriodosAcademicos::PREMATRICULA, true);
            $this->ventanaActiva($actual);
            $this->alumno($plan, $anterior);
        });

        $this->command?->info('Pre Matrícula: datos de demostración listos. Ingresa con '.self::CORREO_ALUMNO.' (alumno) o director@portal.local (coordinación).');
    }

    private function plan(): object
    {
        $existente = DB::table('core.plan_estudios')->where('es_predeterminado', true)->orderBy('id_plan')->first();
        if ($existente !== null) {
            return $existente;
        }

        $idFacultad = $this->obtenerOCrear('core.facultad', 'id_facultad', ['codigo' => 'FAC-ING'], ['nombre' => 'Facultad de Ingeniería']);
        $idEscuela = $this->obtenerOCrear('core.escuela_profesional', 'id_escuela', ['codigo' => 'EP-INF'], [
            'id_facultad' => $idFacultad, 'nombre' => 'Ingeniería Informática', 'estado' => 'Activo',
        ]);
        $idPlan = $this->obtenerOCrear('core.plan_estudios', 'id_plan', ['codigo' => 'PLAN-2018-INF'], [
            'id_escuela' => $idEscuela, 'nombre' => 'Plan de estudios 2018 - Ingeniería Informática', 'version' => '2018',
            'anio_aprobacion' => 2018, 'fecha_vigencia_inicio' => '2018-03-01', 'es_predeterminado' => true, 'estado' => 'Vigente',
        ]);

        return DB::table('core.plan_estudios')->where('id_plan', $idPlan)->first();
    }

    private function malla(object $plan): void
    {
        if (DB::table('core.curso_plan')->where('id_plan', $plan->id_plan)->exists()) {
            return;
        }

        $idArea = DB::table('core.area_formacion')->orderBy('id_area')->value('id_area')
            ?? $this->obtenerOCrear('core.area_formacion', 'id_area', ['codigo' => 'ESP'], ['nombre' => 'Especialidad']);

        $ids = [];
        foreach (self::MALLA as $codigo => [$nombre, $creditos, $ciclo, $teoria, $practica, $laboratorio]) {
            $idCurso = $this->obtenerOCrear('core.curso', 'id_curso', ['codigo' => $codigo], [
                'nombre' => $nombre, 'creditos' => $creditos, 'horas_teoria' => $teoria,
                'horas_practica' => $practica, 'horas_laboratorio' => $laboratorio, 'estado' => 'Activo',
            ]);
            $ids[$codigo] = DB::table('core.curso_plan')->insertGetId([
                'id_plan' => $plan->id_plan, 'id_curso' => $idCurso, 'ciclo' => $ciclo, 'tipo' => 'Obligatorio', 'id_area' => $idArea,
            ], 'id_curso_plan');
        }

        foreach (self::MALLA as $codigo => $curso) {
            foreach ($curso[6] as $requisito) {
                DB::table('core.curso_prerequisito')->insert(['id_curso_plan' => $ids[$codigo], 'id_curso_plan_requisito' => $ids[$requisito]]);
            }
        }
    }

    private function periodo(string $codigo, string $inicio, string $fin, string $estado, bool $exigirEstado): PeriodoAcademico
    {
        $periodo = PeriodoAcademico::query()->where('codigo', $codigo)->first();
        if ($periodo !== null && (! $exigirEstado || $periodo->estado === $estado)) {
            return $periodo;
        }

        $datos = $periodo === null
            ? ['codigo' => $codigo, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'estado' => $estado]
            : ['estado' => $estado];

        return app(PeriodosAcademicos::class)->guardar($datos, null, $periodo);
    }

    /** Garantiza una ventana abierta hoy en el período de demostración. */
    private function ventanaActiva(PeriodoAcademico $periodo): void
    {
        $ventanas = app(VentanaPrematricula::class);
        if ($ventanas->activa($periodo->id_periodo) !== null) {
            return;
        }

        $zona = VentanaPrematricula::ZONA_HORARIA;
        $ultima = CronogramaPrematricula::query()->where('id_periodo', $periodo->id_periodo)->max('fecha_fin');
        $inicio = CarbonImmutable::now($zona);
        if ($ultima !== null && CarbonImmutable::parse($ultima)->gt($inicio)) {
            $inicio = CarbonImmutable::parse($ultima)->addMinute();
        }

        CronogramaPrematricula::query()->create([
            'id_periodo' => $periodo->id_periodo,
            'tipo' => CronogramaPrematricula::query()->where('id_periodo', $periodo->id_periodo)->where('tipo', CronogramaPrematricula::TIPO_ORDINARIA)->exists()
                ? CronogramaPrematricula::TIPO_EXTRAORDINARIA
                : CronogramaPrematricula::TIPO_ORDINARIA,
            'fecha_inicio' => $inicio->utc(),
            'fecha_fin' => $inicio->addDays(30)->endOfDay()->utc(),
            'estado' => 'PROGRAMADA',
            'motivo' => 'Ventana de demostración',
            'zona_horaria' => $zona,
        ]);
    }

    /** Vincula alumno@portal.local con un estudiante y le registra el primer ciclo aprobado. */
    private function alumno(object $plan, PeriodoAcademico $anterior): void
    {
        $user = User::query()->where('email', self::CORREO_ALUMNO)->first();
        if ($user === null) {
            $this->command?->warn('No existe '.self::CORREO_ALUMNO.'. Ejecuta primero php artisan db:seed.');

            return;
        }

        $usuario = DB::table('seguridad.usuario')->whereRaw('lower(correo_institucional) = ?', [self::CORREO_ALUMNO])->first();
        $idPersona = $usuario?->id_persona;
        $idEstudiante = $idPersona === null ? null : DB::table('core.estudiante')->where('id_persona', $idPersona)->value('id_estudiante');

        if ($idEstudiante === null) {
            $idPersona ??= DB::table('core.persona')->insertGetId([
                'tipo_documento' => CuentasEstudiantes::TIPO_DOCUMENTO, 'numero_documento' => self::CODIGO_ALUMNO,
                'nombres' => 'Alumno', 'apellido_paterno' => 'Demo',
            ], 'id_persona');
            $idEstudiante = DB::table('core.estudiante')->insertGetId([
                'codigo_universitario' => self::CODIGO_ALUMNO, 'estado' => 'Activo', 'id_persona' => $idPersona,
                'id_escuela' => $plan->id_escuela, 'id_plan' => $plan->id_plan, 'id_periodo_ingreso' => $anterior->id_periodo,
                'id_modalidad_ingreso' => DB::table('core.modalidad_ingreso')->where('activo', true)->orderBy('id_modalidad')->value('id_modalidad')
                    ?? $this->obtenerOCrear('core.modalidad_ingreso', 'id_modalidad', ['codigo' => 'ORD'], ['nombre' => 'Examen ordinario', 'activo' => true]),
            ], 'id_estudiante');
        }

        if ($usuario === null) {
            DB::table('seguridad.usuario')->insert([
                'correo_institucional' => self::CORREO_ALUMNO, 'contrasena_hash' => $user->password,
                'estado' => 'Activo', 'id_persona' => $idPersona, 'debe_cambiar_contrasena' => false,
            ]);
        }

        if (DB::table('prematricula.historial_academico')->where('id_estudiante', $idEstudiante)->exists()) {
            return;
        }

        $primerCiclo = DB::table('core.curso_plan')->where('id_plan', $plan->id_plan)->where('ciclo', 1)->pluck('id_curso_plan');
        $notas = [15, 14, 16, 13];
        foreach ($primerCiclo as $indice => $idCursoPlan) {
            DB::table('prematricula.historial_academico')->insert([
                'id_estudiante' => $idEstudiante, 'id_curso_plan' => $idCursoPlan, 'id_periodo' => $anterior->id_periodo,
                'nota_final' => $notas[$indice % count($notas)], 'estado' => 'APROBADO',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $clave
     * @param  array<string, mixed>  $valores
     */
    private function obtenerOCrear(string $tabla, string $id, array $clave, array $valores): int
    {
        return (int) (DB::table($tabla)->where($clave)->value($id) ?? DB::table($tabla)->insertGetId($clave + $valores, $id));
    }
}
