<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Encuesta;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class EncuestaService
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly ConfiguracionService $configuracion,
        private readonly DatosAcademicosService $datos,
    ) {}

    /**
     * @return object{
     *     id_encuesta: int|null,
     *     id_seccion: int,
     *     estado: string,
     *     version: int,
     *     preguntas: list<object{id_pregunta: int, texto: string, orden: int}>,
     *     respuestas_count: int,
     *     publicada_en: string|null,
     *     cerrada_en: string|null
     * }
     */
    public function vistaDirector(int $idSeccion): object
    {
        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', $idSeccion)
            ->first();

        if ($encuesta === null) {
            return (object) [
                'id_encuesta' => null,
                'id_seccion' => $idSeccion,
                'estado' => 'borrador',
                'version' => 0,
                'preguntas' => [],
                'respuestas_count' => 0,
                'publicada_en' => null,
                'cerrada_en' => null,
            ];
        }

        return (object) [
            'id_encuesta' => (int) $encuesta->id_encuesta,
            'id_seccion' => $idSeccion,
            'estado' => (string) $encuesta->estado,
            'version' => (int) $encuesta->version,
            'preguntas' => $this->preguntas((int) $encuesta->id_encuesta),
            'respuestas_count' => DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', (int) $encuesta->id_encuesta)
                ->count(),
            'publicada_en' => $encuesta->publicada_en === null ? null : (string) $encuesta->publicada_en,
            'cerrada_en' => $encuesta->cerrada_en === null ? null : (string) $encuesta->cerrada_en,
        ];
    }

    /**
     * @param  list<string>  $preguntas
     * @return object{id_encuesta: int, version: int}
     */
    public function guardarBorrador(
        ActorInvestigacion $actor,
        int $idSeccion,
        array $preguntas,
        int $versionEsperada,
    ): object {
        $preguntas = $this->normalizarPreguntas($preguntas);
        $this->exigirDirector($actor, $idSeccion);

        return DB::transaction(function () use ($actor, $idSeccion, $preguntas, $versionEsperada): object {
            $config = $this->configuracion->bloquearParaEscritura($idSeccion);
            $this->exigirDirector($actor, $idSeccion);
            $this->exigirHabilitada($config);

            $encuesta = DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', $idSeccion)
                ->lockForUpdate()
                ->first();

            if ($encuesta === null) {
                if ($versionEsperada !== 0) {
                    throw $this->versionDesactualizada();
                }

                $idEncuesta = DB::table('investigacion_formativa.encuesta')->insertGetId([
                    'id_seccion' => $idSeccion,
                    'estado' => 'borrador',
                    'creada_por' => $actor->user->id,
                    'version' => 1,
                ], 'id_encuesta');
                $version = 1;
            } else {
                if ((string) $encuesta->estado !== 'borrador') {
                    throw new EstadoInvalido('Las preguntas no se pueden modificar después de publicar la encuesta.');
                }

                $this->verificarVersion((int) $encuesta->version, $versionEsperada);
                $version = $versionEsperada + 1;

                DB::table('investigacion_formativa.encuesta')
                    ->where('id_encuesta', (int) $encuesta->id_encuesta)
                    ->update(['version' => $version]);

                $idEncuesta = (int) $encuesta->id_encuesta;
            }

            DB::table('investigacion_formativa.encuesta_pregunta')
                ->where('id_encuesta', $idEncuesta)
                ->delete();

            foreach ($preguntas as $indice => $texto) {
                DB::table('investigacion_formativa.encuesta_pregunta')->insert([
                    'id_encuesta' => $idEncuesta,
                    'texto' => $texto,
                    'orden' => $indice + 1,
                ]);
            }

            return (object) [
                'id_encuesta' => $idEncuesta,
                'version' => $version,
            ];
        });
    }

    /**
     * @return object{id_encuesta: int, version: int, estado: string}
     */
    public function publicar(
        ActorInvestigacion $actor,
        Encuesta $encuesta,
        int $versionEsperada,
    ): object {
        $this->exigirDirector($actor, (int) $encuesta->id_seccion);

        return DB::transaction(function () use ($actor, $encuesta, $versionEsperada): object {
            $config = $this->configuracion->bloquearParaEscritura((int) $encuesta->id_seccion);
            $this->exigirDirector($actor, (int) $encuesta->id_seccion);
            $this->exigirHabilitada($config);

            $actual = $this->encuestaBloqueada((int) $encuesta->id_seccion, (int) $encuesta->id_encuesta);

            if ($actual === null) {
                throw new EstadoInvalido('La encuesta solicitada ya no existe.');
            }

            if ((string) $actual->estado !== 'borrador') {
                throw new EstadoInvalido('La encuesta ya fue publicada y no se puede publicar otra vez.');
            }

            $this->verificarVersion((int) $actual->version, $versionEsperada);

            if (DB::table('investigacion_formativa.encuesta_pregunta')
                ->where('id_encuesta', (int) $actual->id_encuesta)
                ->count() === 0) {
                throw new EstadoInvalido('Guarde al menos una pregunta antes de publicar la encuesta.');
            }

            $version = $versionEsperada + 1;

            DB::table('investigacion_formativa.encuesta')
                ->where('id_encuesta', (int) $actual->id_encuesta)
                ->update([
                    'estado' => 'publicada',
                    'publicada_en' => $this->marcaTemporal(),
                    'version' => $version,
                ]);

            return (object) [
                'id_encuesta' => (int) $actual->id_encuesta,
                'version' => $version,
                'estado' => 'publicada',
            ];
        });
    }

    /**
     * @return object{id_encuesta: int, version: int, estado: string}
     */
    public function cerrar(
        ActorInvestigacion $actor,
        Encuesta $encuesta,
        int $versionEsperada,
    ): object {
        $this->exigirDirector($actor, (int) $encuesta->id_seccion);

        return DB::transaction(function () use ($actor, $encuesta, $versionEsperada): object {
            $config = $this->configuracion->bloquearParaEscritura((int) $encuesta->id_seccion);
            $this->exigirDirector($actor, (int) $encuesta->id_seccion);
            $this->exigirHabilitada($config);

            $actual = $this->encuestaBloqueada((int) $encuesta->id_seccion, (int) $encuesta->id_encuesta);

            if ($actual === null) {
                throw new EstadoInvalido('La encuesta solicitada ya no existe.');
            }

            if ((string) $actual->estado !== 'publicada') {
                throw new EstadoInvalido('Solo se puede cerrar una encuesta publicada.');
            }

            $this->verificarVersion((int) $actual->version, $versionEsperada);
            $version = $versionEsperada + 1;

            DB::table('investigacion_formativa.encuesta')
                ->where('id_encuesta', (int) $actual->id_encuesta)
                ->update([
                    'estado' => 'cerrada',
                    'cerrada_en' => $this->marcaTemporal(),
                    'version' => $version,
                ]);

            return (object) [
                'id_encuesta' => (int) $actual->id_encuesta,
                'version' => $version,
                'estado' => 'cerrada',
            ];
        });
    }

    /**
     * @param  list<array{id_pregunta: mixed, valor: mixed}>  $respuestas
     * @return object{id_respuesta: int, ya_respondida: bool}
     */
    public function responder(
        ActorInvestigacion $actor,
        Encuesta $encuesta,
        array $respuestas,
        ?string $comentario,
    ): object {
        $comentario = $comentario === null ? null : trim($comentario);

        if ($comentario !== null && mb_strlen($comentario) > 2000) {
            throw new EstadoInvalido('El comentario no puede superar los 2000 caracteres.');
        }

        $this->exigirParticipante($actor, (int) $encuesta->id_seccion);

        return DB::transaction(function () use (
            $actor,
            $encuesta,
            $respuestas,
            $comentario,
        ): object {
            $config = $this->configuracion->bloquearParaEscritura((int) $encuesta->id_seccion);
            $this->exigirParticipante($actor, (int) $encuesta->id_seccion);

            $actual = $this->encuestaBloqueada((int) $encuesta->id_seccion, (int) $encuesta->id_encuesta);

            if ($actual === null) {
                throw new EstadoInvalido('La encuesta solicitada ya no existe.');
            }

            $idEstudiante = $this->idEstudianteParticipante($actor, (int) $encuesta->id_seccion);

            if ($idEstudiante === null) {
                throw new AccesoDenegado('No figura en el padrón activo de la sección.');
            }

            $existente = DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', (int) $actual->id_encuesta)
                ->where('id_estudiante', $idEstudiante)
                ->first();

            if ($existente !== null) {
                return (object) [
                    'id_respuesta' => (int) $existente->id_respuesta,
                    'ya_respondida' => true,
                ];
            }

            $this->exigirHabilitada($config);

            if ((string) $actual->estado !== 'publicada') {
                throw new EstadoInvalido(
                    (string) $actual->estado === 'cerrada'
                        ? 'La encuesta está cerrada y no admite nuevas respuestas.'
                        : 'La encuesta todavía no está publicada.',
                );
            }

            $respuestas = $this->normalizarRespuestas($respuestas);
            $preguntas = $this->preguntas((int) $actual->id_encuesta);
            $idsEsperados = array_map(
                static fn (object $pregunta): int => (int) $pregunta->id_pregunta,
                $preguntas,
            );
            $idsRecibidos = array_keys($respuestas);
            sort($idsEsperados);
            sort($idsRecibidos);

            if ($idsEsperados !== $idsRecibidos) {
                throw new EstadoInvalido(
                    'Debe responder exactamente una vez cada pregunta publicada de la encuesta.',
                );
            }

            $idRespuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
                'id_encuesta' => (int) $actual->id_encuesta,
                'id_estudiante' => $idEstudiante,
                'comentario' => $comentario,
                'respondida_en' => $this->marcaTemporal(),
            ], 'id_respuesta');

            foreach ($respuestas as $idPregunta => $valor) {
                DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                    'id_respuesta' => $idRespuesta,
                    'id_pregunta' => $idPregunta,
                    'id_encuesta' => (int) $actual->id_encuesta,
                    'valor' => $valor,
                ]);
            }

            return (object) [
                'id_respuesta' => $idRespuesta,
                'ya_respondida' => false,
            ];
        });
    }

    /**
     * @return object{
     *     id_seccion: int,
     *     estado: string,
     *     habilitada: bool,
     *     preguntas: list<object{id_pregunta: int, texto: string, orden: int}>,
     *     respuesta: object{respondida_en: string, comentario: string|null, respuestas: array<int, int>}|null
     * }
     */
    public function vistaEstudiante(ActorInvestigacion $actor, int $idSeccion): object
    {
        $this->exigirParticipante($actor, $idSeccion);

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', $idSeccion)
            ->first();

        $habilitada = (bool) DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', $idSeccion)
            ->value('habilitada');

        if ($encuesta === null || (string) $encuesta->estado === 'borrador') {
            return (object) [
                'id_seccion' => $idSeccion,
                'estado' => 'no_disponible',
                'habilitada' => $habilitada,
                'preguntas' => [],
                'respuesta' => null,
            ];
        }

        $idEstudiante = $this->idEstudianteParticipante($actor, $idSeccion);
        $respuesta = null;

        if ($idEstudiante !== null) {
            $encuestaRespuesta = DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', (int) $encuesta->id_encuesta)
                ->where('id_estudiante', $idEstudiante)
                ->first();

            if ($encuestaRespuesta !== null) {
                $items = DB::table('investigacion_formativa.encuesta_respuesta_item')
                    ->where('id_respuesta', (int) $encuestaRespuesta->id_respuesta)
                    ->orderBy('id_pregunta')
                    ->get()
                    ->mapWithKeys(static fn (object $item): array => [
                        (int) $item->id_pregunta => (int) $item->valor,
                    ])
                    ->all();

                $respuesta = (object) [
                    'respondida_en' => (string) $encuestaRespuesta->respondida_en,
                    'comentario' => $encuestaRespuesta->comentario === null
                        ? null
                        : (string) $encuestaRespuesta->comentario,
                    'respuestas' => $items,
                ];
            }
        }

        return (object) [
            'id_seccion' => $idSeccion,
            'estado' => (string) $encuesta->estado,
            'habilitada' => $habilitada,
            'preguntas' => $this->preguntas((int) $encuesta->id_encuesta),
            'respuesta' => $respuesta,
        ];
    }

    /**
     * @return list<object{id_pregunta: int, texto: string, orden: int}>
     */
    private function preguntas(int $idEncuesta): array
    {
        return DB::table('investigacion_formativa.encuesta_pregunta')
            ->where('id_encuesta', $idEncuesta)
            ->orderBy('orden')
            ->get(['id_pregunta', 'texto', 'orden'])
            ->map(static fn (object $pregunta): object => (object) [
                'id_pregunta' => (int) $pregunta->id_pregunta,
                'texto' => (string) $pregunta->texto,
                'orden' => (int) $pregunta->orden,
            ])
            ->all();
    }

    /**
     * @param  list<string>  $preguntas
     * @return list<string>
     */
    private function normalizarPreguntas(array $preguntas): array
    {
        if ($preguntas === [] || count($preguntas) > 50) {
            throw new EstadoInvalido('La encuesta debe contener entre 1 y 50 preguntas.');
        }

        $normalizadas = [];

        foreach ($preguntas as $pregunta) {
            if (! is_string($pregunta)) {
                throw new EstadoInvalido('Cada pregunta debe ser un texto.');
            }

            $pregunta = trim($pregunta);

            if ($pregunta === '' || mb_strlen($pregunta) > 1000) {
                throw new EstadoInvalido('Cada pregunta debe tener entre 1 y 1000 caracteres.');
            }

            $normalizadas[] = $pregunta;
        }

        return $normalizadas;
    }

    /**
     * @param  list<array{id_pregunta: mixed, valor: mixed}>  $respuestas
     * @return array<int, int>
     */
    private function normalizarRespuestas(array $respuestas): array
    {
        if ($respuestas === [] || count($respuestas) > 50) {
            throw new EstadoInvalido('La respuesta debe contener entre 1 y 50 respuestas.');
        }

        $normalizadas = [];

        foreach ($respuestas as $respuesta) {
            if (! is_array($respuesta)) {
                throw new EstadoInvalido('La selección de respuestas no es válida.');
            }

            $idPregunta = filter_var($respuesta['id_pregunta'] ?? null, FILTER_VALIDATE_INT);
            $valor = filter_var($respuesta['valor'] ?? null, FILTER_VALIDATE_INT);

            if ($idPregunta === false || $idPregunta <= 0) {
                throw new EstadoInvalido('La pregunta seleccionada no es válida.');
            }

            if ($valor === false || $valor < 1 || $valor > 5) {
                throw new EstadoInvalido('Cada respuesta debe ser un número entero entre 1 y 5.');
            }

            $idPregunta = (int) $idPregunta;

            if (array_key_exists($idPregunta, $normalizadas)) {
                throw new EstadoInvalido('No puede responder la misma pregunta más de una vez.');
            }

            $normalizadas[$idPregunta] = (int) $valor;
        }

        return $normalizadas;
    }

    private function encuestaBloqueada(int $idSeccion, int $idEncuesta): ?object
    {
        return DB::table('investigacion_formativa.encuesta')
            ->where('id_encuesta', $idEncuesta)
            ->where('id_seccion', $idSeccion)
            ->lockForUpdate()
            ->first();
    }

    private function exigirDirector(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $actor->tieneRol('director_escuela')
            || ! $actor->user->can('investigacion-formativa.surveys.manage')) {
            throw new AccesoDenegado('No tiene permiso para gestionar encuestas.');
        }

        if (! $this->acceso->puedeVerSeccion($actor->user, $idSeccion, 'director_escuela')) {
            throw new AccesoDenegado('No es responsable de una encuesta de la escuela solicitada.');
        }
    }

    private function exigirParticipante(ActorInvestigacion $actor, int $idSeccion): void
    {
        if (! $actor->tieneRol('alumno')
            || ! $actor->user->can('investigacion-formativa.surveys.respond')) {
            throw new AccesoDenegado('No tiene permiso para responder encuestas.');
        }

        if (! $this->acceso->puedeParticipar($actor->user, $idSeccion)) {
            throw new AccesoDenegado('No figura en el padrón activo de la sección.');
        }
    }

    private function idEstudianteParticipante(ActorInvestigacion $actor, int $idSeccion): ?int
    {
        $seccion = $this->datos->seccionConEscuela($idSeccion);

        if ($seccion === null || $seccion->id_escuela === null) {
            return null;
        }

        $idEstudiante = $this->acceso->idEstudianteEnEscuela($actor, (int) $seccion->id_escuela);

        if ($idEstudiante === null
            || ! $this->acceso->participanteActivo($idSeccion, $idEstudiante)) {
            return null;
        }

        return $idEstudiante;
    }

    private function exigirHabilitada(object $config): void
    {
        if (! (bool) $config->habilitada) {
            throw new EstadoInvalido('La sección está deshabilitada. No se aceptan cambios ni respuestas.');
        }
    }

    private function verificarVersion(int $versionActual, int $versionEsperada): void
    {
        if ($versionActual !== $versionEsperada) {
            throw $this->versionDesactualizada();
        }
    }

    private function versionDesactualizada(): VersionDesactualizada
    {
        return new VersionDesactualizada(
            'La encuesta cambió en otra sesión. Recargue la página y vuelva a intentarlo.',
        );
    }

    private function marcaTemporal(): string
    {
        return CarbonImmutable::now('UTC')->format('Y-m-d H:i:sP');
    }
}
