<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Modules\InvestigacionFormativa\Support\PuntajeRubrica;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class ConfiguracionService
{
    private const ZONA_HORARIA = 'America/Lima';

    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DatosAcademicosService $datos,
    ) {}

    public function estado(int $idSeccion): object
    {
        $config = $this->configuracionDeSeccion($idSeccion);

        if ($config === null) {
            return (object) [
                'id_seccion' => $idSeccion,
                'id_configuracion' => null,
                'habilitada' => false,
                'version' => 1,
                'id_rubrica' => null,
                'tiene_competencias' => false,
                'tiene_rubrica' => false,
                'tiene_cronograma' => false,
                'configurada' => false,
                'configurado_en' => null,
                'faltantes' => [
                    'Competencias seleccionadas',
                    'Rúbrica completa',
                    'Cronograma de tres unidades',
                ],
            ];
        }

        return $this->calcularEstado($config, $idSeccion);
    }

    public function estaConfigurada(int $idSeccion): bool
    {
        return (bool) $this->estado($idSeccion)->configurada;
    }

    /**
     * Crea o bloquea la fila de configuración para una escritura de la sección.
     *
     * @return object{id_configuracion: int, id_seccion: int, habilitada: bool, version: int, id_rubrica: int|null, configurado_en: string|null}
     */
    public function bloquearParaEscritura(int $idSeccion): object
    {
        return $this->crearYBloquearConfiguracion($idSeccion);
    }

    /**
     * Registra el cambio de configuración producido por una importación operativa.
     *
     * Debe ejecutarse dentro de una transacción que ya bloquee la configuración
     * de la sección.
     */
    public function sincronizarVersionPorImportacion(int $idSeccion): void
    {
        $config = $this->crearYBloquearConfiguracion($idSeccion);
        $this->sellar($config, $idSeccion, null);
    }

    /**
     * @return list<object{id_competencia: int, codigo: string, descripcion: string, fuente: string, seleccionada: bool}>
     */
    public function competencias(int $idSeccion): array
    {
        return DB::table('investigacion_formativa.competencia_seccion')
            ->where('id_seccion', $idSeccion)
            ->orderBy('codigo')
            ->get(['id_competencia', 'codigo', 'descripcion', 'fuente', 'seleccionada'])
            ->map(static fn (object $row): object => (object) [
                'id_competencia' => (int) $row->id_competencia,
                'codigo' => (string) $row->codigo,
                'descripcion' => (string) $row->descripcion,
                'fuente' => (string) $row->fuente,
                'seleccionada' => (bool) $row->seleccionada,
            ])
            ->all();
    }

    public function rubrica(int $idSeccion): ?object
    {
        $config = $this->configuracionDeSeccion($idSeccion);

        if ($config === null || $config->id_rubrica === null) {
            return null;
        }

        $rubrica = DB::table('investigacion_formativa.rubrica')
            ->where('id_rubrica', $config->id_rubrica)
            ->first();

        if ($rubrica === null) {
            return null;
        }

        $criterios = DB::table('investigacion_formativa.criterio')
            ->where('id_rubrica', $rubrica->id_rubrica)
            ->orderBy('orden')
            ->get();

        $niveles = $criterios->isEmpty()
            ? collect()
            : DB::table('investigacion_formativa.criterio_nivel')
                ->whereIn('id_criterio', $criterios->pluck('id_criterio')->all())
                ->orderBy('nivel')
                ->get()
                ->groupBy('id_criterio');

        return (object) [
            'id_rubrica' => (int) $rubrica->id_rubrica,
            'nombre' => (string) $rubrica->nombre,
            'descripcion' => $rubrica->descripcion,
            'criterios' => $criterios->map(static function (object $criterio) use ($niveles): object {
                $nivelesCriterio = $niveles->get($criterio->id_criterio, collect());

                return (object) [
                    'id_criterio' => (int) $criterio->id_criterio,
                    'descripcion' => (string) $criterio->descripcion,
                    'puntaje_maximo' => (float) $criterio->puntaje_maximo,
                    'orden' => (int) $criterio->orden,
                    'niveles' => $nivelesCriterio
                        ->map(static fn (object $nivel): object => (object) [
                            'nivel' => (int) $nivel->nivel,
                            'descripcion' => (string) $nivel->descripcion,
                            'puntaje' => (float) $nivel->puntaje,
                        ])
                        ->values()
                        ->all(),
                ];
            })->all(),
        ];
    }

    /**
     * @return list<object{id_entregable: int|null, orden: int, nombre: string, fecha_limite_local: string|null}>
     */
    public function cronograma(int $idSeccion): array
    {
        $existentes = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', $idSeccion)
            ->whereIn('orden', [1, 2, 3])
            ->get()
            ->keyBy('orden');

        $unidades = [];

        foreach ([1, 2, 3] as $orden) {
            $fila = $existentes->get($orden);

            $unidades[] = (object) [
                'id_entregable' => $fila === null ? null : (int) $fila->id_entregable,
                'orden' => $orden,
                'nombre' => $fila === null ? "Unidad {$orden}" : (string) $fila->nombre,
                'fecha_limite_local' => $fila === null || $fila->fecha_limite === null
                    ? null
                    : CarbonImmutable::parse($fila->fecha_limite)
                        ->setTimezone(self::ZONA_HORARIA)
                        ->format('Y-m-d\TH:i'),
            ];
        }

        return $unidades;
    }

    public function cambiarHabilitacion(
        ActorInvestigacion $actor,
        int $idSeccion,
        bool $habilitada,
        int $version,
    ): void {
        $this->exigirDirector($actor, $idSeccion);
        $this->exigirSeccionActiva($idSeccion);

        DB::transaction(function () use ($actor, $idSeccion, $habilitada, $version): void {
            $config = $this->crearYBloquearConfiguracion($idSeccion);
            $this->exigirDirector($actor, $idSeccion);
            $this->verificarVersion($config, $version);

            if ((bool) $config->habilitada === $habilitada) {
                return;
            }

            DB::table('investigacion_formativa.seccion_configuracion')
                ->where('id_configuracion', $config->id_configuracion)
                ->update(['habilitada' => $habilitada]);

            $this->sellar($config, $idSeccion, $actor);
        });
    }

    /**
     * @param  list<int>  $competenciaIds
     */
    public function guardarCompetencias(
        ActorInvestigacion $actor,
        int $idSeccion,
        array $competenciaIds,
        int $version,
    ): void {
        $this->exigirProfesor($actor, $idSeccion);
        $this->exigirSeccionActiva($idSeccion);

        $ids = array_values(array_unique(array_map('intval', $competenciaIds)));

        $this->ejecutarCambio($actor, $idSeccion, $version, function () use ($idSeccion, $ids): void {
            if ($this->seccionTieneEntregas($idSeccion)) {
                throw new RecursoBloqueado(
                    'Las competencias están congeladas porque la sección ya tiene entregas.',
                );
            }

            if ($ids !== []) {
                $pertenecen = DB::table('investigacion_formativa.competencia_seccion')
                    ->where('id_seccion', $idSeccion)
                    ->whereIn('id_competencia', $ids)
                    ->count();

                if ($pertenecen !== count($ids)) {
                    throw new EstadoInvalido('Una competencia no pertenece a la sección.');
                }
            }

            DB::table('investigacion_formativa.competencia_seccion')
                ->where('id_seccion', $idSeccion)
                ->update(['seleccionada' => false]);

            if ($ids !== []) {
                DB::table('investigacion_formativa.competencia_seccion')
                    ->where('id_seccion', $idSeccion)
                    ->whereIn('id_competencia', $ids)
                    ->update(['seleccionada' => true]);
            }
        });
    }

    /**
     * @param  array{nombre: string, descripcion?: string|null, criterios: list<array{descripcion: string, niveles: list<array{nivel: int, descripcion: string, puntaje: numeric-string|int|float}>}>}  $datos
     */
    public function guardarRubrica(
        ActorInvestigacion $actor,
        int $idSeccion,
        array $datos,
        int $version,
    ): void {
        $this->exigirProfesor($actor, $idSeccion);
        $this->exigirSeccionActiva($idSeccion);

        if ($actor->idDocente === null) {
            throw new AccesoDenegado('No tiene una identidad de docente activa.');
        }

        $this->validarRubrica($datos);

        $this->ejecutarCambio($actor, $idSeccion, $version, function (object $config) use ($actor, $idSeccion, $datos): void {
            if ($this->seccionTieneEntregas($idSeccion)) {
                throw new RecursoBloqueado('La rúbrica ya está congelada porque existen entregas.');
            }

            $idRubrica = $config->id_rubrica === null
                ? $this->crearRubrica($actor, $datos)
                : $this->actualizarRubrica((int) $config->id_rubrica, $datos);

            if ($config->id_rubrica === null) {
                DB::table('investigacion_formativa.seccion_configuracion')
                    ->where('id_configuracion', $config->id_configuracion)
                    ->update(['id_rubrica' => $idRubrica]);
            }

            DB::table('investigacion_formativa.entregable')
                ->where('id_seccion', $idSeccion)
                ->update(['id_rubrica' => $idRubrica]);
        });
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $fechas
     */
    public function guardarCronograma(
        ActorInvestigacion $actor,
        int $idSeccion,
        array $fechas,
        int $version,
    ): void {
        $this->exigirProfesor($actor, $idSeccion);
        $this->exigirSeccionActiva($idSeccion);

        $fechasUtc = $this->convertirFechas($fechas);

        $this->ejecutarCambio($actor, $idSeccion, $version, function (object $config) use ($idSeccion, $fechasUtc): void {
            if ($config->id_rubrica === null) {
                throw new EstadoInvalido('Configure la rúbrica antes de guardar el cronograma.');
            }

            if ($this->seccionTieneEntregas($idSeccion)) {
                $this->exigirExtensionDePlazos($idSeccion, $fechasUtc);
            }

            foreach ($fechasUtc as $indice => $fechaUtc) {
                $orden = $indice + 1;

                DB::table('investigacion_formativa.entregable')->updateOrInsert(
                    ['id_seccion' => $idSeccion, 'orden' => $orden],
                    [
                        'nombre' => "Unidad {$orden}",
                        'fecha_limite' => $fechaUtc->format('Y-m-d H:i:sP'),
                        'id_rubrica' => $config->id_rubrica,
                    ],
                );
            }
        });
    }

    private function configuracionDeSeccion(int $idSeccion): ?object
    {
        return DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $idSeccion)
            ->first();
    }

    private function crearYBloquearConfiguracion(int $idSeccion): object
    {
        $config = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $idSeccion)
            ->lockForUpdate()
            ->first();

        if ($config !== null) {
            return $config;
        }

        DB::table('investigacion_formativa.seccion_configuracion')->insertOrIgnore([
            'id_seccion' => $idSeccion,
            'habilitada' => false,
            'version' => 1,
            'actualizado_en' => $this->marcaTemporalUtc(),
        ]);

        return DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $idSeccion)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function verificarVersion(object $config, int $version): void
    {
        if ((int) $config->version !== $version) {
            throw new VersionDesactualizada(
                'La configuración cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
            );
        }
    }

    private function ejecutarCambio(
        ActorInvestigacion $actor,
        int $idSeccion,
        int $version,
        callable $mutate,
    ): void {
        DB::transaction(function () use ($actor, $idSeccion, $version, $mutate): void {
            $config = $this->crearYBloquearConfiguracion($idSeccion);
            $this->exigirProfesor($actor, $idSeccion);
            $this->verificarVersion($config, $version);

            if (! (bool) $config->habilitada) {
                throw new EstadoInvalido('La sección está deshabilitada. No se aceptan cambios de configuración.');
            }

            $mutate($config);
            $this->sellar($config, $idSeccion, $actor);
        });
    }

    private function sellar(
        object $config,
        int $idSeccion,
        ?ActorInvestigacion $actor,
    ): void {
        $actual = DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_configuracion', $config->id_configuracion)
            ->first() ?? $config;

        $estado = $this->calcularEstado($actual, $idSeccion);

        $configuradoEn = $actual->configurado_en;

        if ($estado->configurada && $configuradoEn === null) {
            $configuradoEn = $this->marcaTemporalUtc();
        }

        DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_configuracion', $config->id_configuracion)
            ->update([
                'version' => (int) $config->version + 1,
                'configurado_en' => $configuradoEn,
                'actualizado_por' => $actor?->user->id ?? $actual->actualizado_por,
                'actualizado_en' => $this->marcaTemporalUtc(),
            ]);
    }

    private function calcularEstado(object $config, int $idSeccion): object
    {
        $tieneCompetencias = DB::table('investigacion_formativa.competencia_seccion')
            ->where('id_seccion', $idSeccion)
            ->where('seleccionada', true)
            ->exists();

        $idRubrica = $config->id_rubrica === null ? null : (int) $config->id_rubrica;

        $tieneRubrica = false;

        if ($idRubrica !== null) {
            $totalCriterios = DB::table('investigacion_formativa.criterio')
                ->where('id_rubrica', $idRubrica)
                ->count();

            $completos = DB::table('investigacion_formativa.criterio as c')
                ->join('investigacion_formativa.criterio_nivel as cn', 'cn.id_criterio', '=', 'c.id_criterio')
                ->where('c.id_rubrica', $idRubrica)
                ->groupBy('c.id_criterio')
                ->havingRaw('count(*) = 4')
                ->pluck('c.id_criterio')
                ->count();

            $tieneRubrica = $totalCriterios > 0 && $completos === $totalCriterios;
        }

        $tieneCronograma = $idRubrica !== null
            && DB::table('investigacion_formativa.entregable')
                ->where('id_seccion', $idSeccion)
                ->whereIn('orden', [1, 2, 3])
                ->where('id_rubrica', $idRubrica)
                ->whereNotNull('fecha_limite')
                ->distinct()
                ->count('orden') === 3;

        $faltantes = [];

        if (! $tieneCompetencias) {
            $faltantes[] = 'Competencias seleccionadas';
        }

        if (! $tieneRubrica) {
            $faltantes[] = 'Rúbrica completa';
        }

        if (! $tieneCronograma) {
            $faltantes[] = 'Cronograma de tres unidades';
        }

        return (object) [
            'id_seccion' => $idSeccion,
            'id_configuracion' => (int) $config->id_configuracion,
            'habilitada' => (bool) $config->habilitada,
            'version' => (int) $config->version,
            'id_rubrica' => $idRubrica,
            'tiene_competencias' => $tieneCompetencias,
            'tiene_rubrica' => $tieneRubrica,
            'tiene_cronograma' => $tieneCronograma,
            'configurada' => $tieneCompetencias && $tieneRubrica && $tieneCronograma,
            'configurado_en' => $config->configurado_en,
            'faltantes' => $faltantes,
        ];
    }

    private function crearRubrica(ActorInvestigacion $actor, array $datos): int
    {
        $idRubrica = DB::table('investigacion_formativa.rubrica')->insertGetId([
            'nombre' => trim((string) $datos['nombre']),
            'descripcion' => isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== ''
                ? trim((string) $datos['descripcion'])
                : null,
            'id_docente_autor' => $actor->idDocente,
        ], 'id_rubrica');

        $this->reemplazarCriterios($idRubrica, $datos['criterios']);

        return $idRubrica;
    }

    private function actualizarRubrica(int $idRubrica, array $datos): int
    {
        DB::table('investigacion_formativa.rubrica')
            ->where('id_rubrica', $idRubrica)
            ->update([
                'nombre' => trim((string) $datos['nombre']),
                'descripcion' => isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== ''
                    ? trim((string) $datos['descripcion'])
                    : null,
            ]);

        DB::table('investigacion_formativa.criterio')
            ->where('id_rubrica', $idRubrica)
            ->delete();

        $this->reemplazarCriterios($idRubrica, $datos['criterios']);

        return $idRubrica;
    }

    /**
     * @param  array{nombre: string, descripcion?: string|null, criterios: list<array{descripcion: string, niveles: list<array{nivel: int, descripcion: string, puntaje: numeric-string|int|float}>}>}  $datos
     */
    private function validarRubrica(array $datos): void
    {
        $criterios = $datos['criterios'] ?? null;

        if (! is_array($criterios) || $criterios === []) {
            throw new EstadoInvalido('La rúbrica debe contener al menos un criterio.');
        }

        foreach (array_values($criterios) as $indice => $criterio) {
            $niveles = is_array($criterio['niveles'] ?? null)
                ? array_values($criterio['niveles'])
                : [];
            $puntajes = PuntajeRubrica::centavosPorNivel($niveles);

            if ($puntajes === null) {
                throw new EstadoInvalido(
                    "El criterio {$indice} debe contener los niveles 1, 2, 3 y 4 con puntajes válidos.",
                );
            }

            if ($puntajes[1] > $puntajes[2]
                || $puntajes[2] > $puntajes[3]
                || $puntajes[3] > $puntajes[4]) {
                throw new EstadoInvalido(
                    "Los puntajes del criterio {$indice} deben ser no decrecientes.",
                );
            }

            if ($puntajes[4] <= 0) {
                throw new EstadoInvalido(
                    "El puntaje máximo del criterio {$indice} debe ser mayor a cero.",
                );
            }
        }
    }

    /**
     * @param  list<array{descripcion: string, niveles: list<array{nivel: int, descripcion: string, puntaje: numeric-string|int|float}>}>  $criterios
     */
    private function reemplazarCriterios(int $idRubrica, array $criterios): void
    {
        foreach (array_values($criterios) as $indice => $criterio) {
            $nivelesPorNumero = collect($criterio['niveles'])
                ->keyBy(static fn (array $nivel): int => (int) $nivel['nivel']);
            $puntajes = PuntajeRubrica::centavosPorNivel(array_values($criterio['niveles']));

            if ($puntajes === null) {
                throw new EstadoInvalido('Cada criterio debe tener los cuatro niveles.');
            }

            $idCriterio = DB::table('investigacion_formativa.criterio')->insertGetId([
                'id_rubrica' => $idRubrica,
                'descripcion' => trim((string) $criterio['descripcion']),
                'puntaje_maximo' => PuntajeRubrica::formatear($puntajes[4]),
                'orden' => $indice + 1,
            ], 'id_criterio');

            foreach ([1, 2, 3, 4] as $nivel) {
                $fila = $nivelesPorNumero->get($nivel);

                if ($fila === null) {
                    throw new EstadoInvalido('Cada criterio debe tener los cuatro niveles.');
                }

                DB::table('investigacion_formativa.criterio_nivel')->insert([
                    'id_criterio' => $idCriterio,
                    'nivel' => $nivel,
                    'descripcion' => trim((string) $fila['descripcion']),
                    'puntaje' => PuntajeRubrica::formatear($puntajes[$nivel]),
                ]);
            }
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $fechas
     * @return list<CarbonImmutable>
     */
    private function convertirFechas(array $fechas): array
    {
        if (count($fechas) !== 3) {
            throw new EstadoInvalido('El cronograma debe tener exactamente tres fechas.');
        }

        $zona = new DateTimeZone(self::ZONA_HORARIA);
        $convertidas = [];

        foreach (array_values($fechas) as $fecha) {
            $local = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', (string) $fecha, $zona);
            $errores = DateTimeImmutable::getLastErrors();

            if ($local === false || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
                throw new EstadoInvalido('Una fecha del cronograma no es válida.');
            }

            $convertidas[] = CarbonImmutable::instance($local)->setTimezone('UTC');
        }

        if (! ($convertidas[0] < $convertidas[1] && $convertidas[1] < $convertidas[2])) {
            throw new EstadoInvalido('Las fechas deben respetar el orden de las tres unidades.');
        }

        return $convertidas;
    }

    /**
     * @param  list<CarbonImmutable>  $fechasUtc
     */
    private function exigirExtensionDePlazos(int $idSeccion, array $fechasUtc): void
    {
        $existentes = DB::table('investigacion_formativa.entregable')
            ->where('id_seccion', $idSeccion)
            ->whereIn('orden', [1, 2, 3])
            ->get()
            ->keyBy('orden');

        foreach ($fechasUtc as $indice => $fechaUtc) {
            $orden = $indice + 1;
            $actual = $existentes->get($orden);

            if ($actual === null || $actual->fecha_limite === null) {
                throw new RecursoBloqueado(
                    'La sección ya tiene entregas y no permite modificar un plazo que no existe.',
                );
            }

            if ($fechaUtc->lt(CarbonImmutable::parse($actual->fecha_limite)->utc())) {
                throw new RecursoBloqueado(
                    'La sección ya tiene entregas. Solo se pueden extender los plazos.',
                );
            }
        }
    }

    private function marcaTemporalUtc(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d H:i:sP');
    }

    private function seccionTieneEntregas(int $idSeccion): bool
    {
        return DB::table('investigacion_formativa.entrega as e')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'e.id_proyecto')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'p.id_grupo')
            ->where('g.id_seccion', $idSeccion)
            ->exists();
    }

    public function rubricaCongelada(int $idSeccion): bool
    {
        return $this->seccionTieneEntregas($idSeccion);
    }

    private function exigirDirector(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $actor->user->can('investigacion-formativa.courses.manage')
            || ! $this->acceso->puedeVerSeccion($actor->user, $idSeccion, 'director_escuela')) {
            throw new AccesoDenegado('No tiene alcance de dirección sobre la sección.');
        }
    }

    private function exigirProfesor(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $actor->user->can('investigacion-formativa.configuration.manage')
            || ! $this->acceso->puedeVerSeccion($actor->user, $idSeccion, 'profesor')) {
            throw new AccesoDenegado('No es el docente responsable de la sección.');
        }
    }

    private function exigirSeccionActiva(int $idSeccion): void
    {
        $seccion = $this->datos->seccionConEscuela($idSeccion);

        if ($seccion === null || $seccion->estado !== 'Activa') {
            throw new EstadoInvalido('La sección no está activa.');
        }
    }
}
