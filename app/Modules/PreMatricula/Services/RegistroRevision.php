<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\LimiteCreditosEstudiante;
use App\Modules\PreMatricula\Models\RevisionCurso;
use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guarda cada envío como una revisión inmutable con su snapshot y su constancia PDF.
 * La constancia no se reconstruye con el catálogo actual: conserva lo registrado.
 */
final class RegistroRevision
{
    public const VERSION_ESQUEMA = 2;

    public function __construct(
        private readonly CatalogoAcademico $catalogo,
        private readonly ConstanciaPdf $constancia,
    ) {}

    public function registrar(SolicitudPrematricula $solicitud, CronogramaPrematricula $ventana): RevisionSolicitud
    {
        $numero = ((int) $solicitud->revisiones()->max('numero_revision')) + 1;
        $codigo = 'PRE-'.Str::upper((string) Str::uuid());
        $enviadaEn = now();
        $snapshot = $this->snapshot($solicitud) + [
            'numero_revision' => $numero,
            'codigo_constancia' => $codigo,
            'enviada_en' => $enviadaEn->toIso8601String(),
            'ventana' => [
                'id' => $ventana->id_cronograma,
                'tipo' => $ventana->tipo,
                'fecha_inicio' => $ventana->fecha_inicio->toIso8601String(),
                'fecha_fin' => $ventana->fecha_fin->toIso8601String(),
            ],
        ];
        $pdf = $this->constancia->generar($snapshot);

        $revision = RevisionSolicitud::query()->create([
            'id_solicitud' => $solicitud->id_solicitud,
            'id_cronograma' => $ventana->id_cronograma,
            'motivo' => RevisionSolicitud::MOTIVO_ENVIO,
            'numero_revision' => $numero,
            'codigo_constancia' => $codigo,
            'snapshot' => $snapshot,
            'version_esquema' => self::VERSION_ESQUEMA,
            'pdf_base64' => base64_encode($pdf),
            'pdf_sha256' => hash('sha256', $pdf),
            'enviada_en' => $enviadaEn,
        ]);

        foreach ($snapshot['detalles'] as $detalle) {
            RevisionCurso::query()->create([
                'id_revision' => $revision->id_revision,
                'id_curso_plan' => $detalle['id_curso_plan'],
                'preferencia_turno' => $detalle['preferencia_turno'],
            ]);
        }

        return $revision;
    }

    /** @return array<string, mixed> */
    private function snapshot(SolicitudPrematricula $solicitud): array
    {
        $solicitud->loadMissing(['estudiante.persona', 'periodo', 'detalles']);
        $estudiante = $solicitud->estudiante;
        $persona = $estudiante->persona;
        $cursos = $this->catalogo->cursos($solicitud->detalles->pluck('id_curso_plan')->map(fn ($id): int => (int) $id)->all());
        $escuela = DB::table('core.escuela_profesional as e')
            ->leftJoin('core.facultad as f', 'f.id_facultad', '=', 'e.id_facultad')
            ->where('e.id_escuela', $estudiante->id_escuela)
            ->first(['e.nombre as escuela', 'f.nombre as facultad']);

        $detalles = $solicitud->detalles->map(function ($detalle) use ($cursos): array {
            $curso = $cursos->get($detalle->id_curso_plan);

            return [
                'id_detalle' => $detalle->id_detalle,
                'id_curso_plan' => (int) $detalle->id_curso_plan,
                'codigo' => $curso->codigo,
                'nombre' => $curso->nombre,
                'ciclo' => $curso->ciclo,
                'creditos' => $curso->creditos,
                'preferencia_turno' => $detalle->preferencia_turno,
                'prerrequisitos' => array_column($curso->prerrequisitos, 'codigo'),
            ];
        })->sortBy([['ciclo', 'asc'], ['codigo', 'asc']])->values()->all();

        return [
            'version_esquema' => self::VERSION_ESQUEMA,
            'institucion' => [
                'universidad' => 'Universidad Nacional de Trujillo',
                'facultad' => $escuela?->facultad,
                'escuela' => $escuela?->escuela,
            ],
            'id_solicitud' => $solicitud->id_solicitud,
            'estudiante' => [
                'id' => $estudiante->id_estudiante,
                'codigo_universitario' => $estudiante->codigo_universitario,
                'nombres' => $persona?->nombres,
                'apellidos' => trim(($persona?->apellido_paterno ?? '').' '.($persona?->apellido_materno ?? '')),
            ],
            'periodo' => [
                'id' => $solicitud->periodo->id_periodo,
                'codigo' => $solicitud->periodo->codigo,
                'fecha_inicio' => $solicitud->periodo->fecha_inicio->toDateString(),
            ],
            'zona_horaria' => VentanaPrematricula::ZONA_HORARIA,
            'creditos_totales' => array_sum(array_column($detalles, 'creditos')),
            'creditos_maximos' => LimiteCreditosEstudiante::creditosPara($estudiante->id_estudiante),
            'detalles' => $detalles,
        ];
    }
}
