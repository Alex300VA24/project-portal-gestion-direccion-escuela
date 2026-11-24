<?php

namespace App\Modules\GestionPracticas\Support;

use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Lecturas de catálogos compartidos (core, comun) para listados y formularios del módulo. */
final class Catalogos
{
    public const TIPOS_PRACTICA = ['preprofesional' => 'Preprofesional', 'profesional' => 'Profesional'];

    public const MODALIDADES = ['presencial' => 'Presencial', 'remota' => 'Remota', 'hibrida' => 'Híbrida'];

    public const TIPOS_DOCUMENTO = [
        'carta_aceptacion' => 'Carta de aceptación',
        'plan_practica' => 'Plan de práctica',
        'informe_parcial' => 'Informe parcial',
        'informe_final' => 'Informe final',
        'constancia' => 'Constancia',
        'otro' => 'Otro',
    ];

    public const TIPOS_SEGUIMIENTO = [
        'reunion' => 'Reunión',
        'visita' => 'Visita',
        'llamada' => 'Llamada',
        'revision_avance' => 'Revisión de avance',
        'otro' => 'Otro',
    ];

    /** @param iterable<int|string|null> $ids @return array<int, string> */
    public static function empresas(iterable $ids): array
    {
        $ids = self::ids($ids);

        return $ids === [] ? [] : DB::table('comun.empresa')
            ->whereIn('id_empresa', $ids)->pluck('razon_social', 'id_empresa')->all();
    }

    /** @param iterable<int|string|null> $ids @return array<int, string> */
    public static function estudiantes(iterable $ids): array
    {
        $ids = self::ids($ids);

        return $ids === [] ? [] : DB::table('core.estudiante as e')
            ->join('core.persona as p', 'p.id_persona', '=', 'e.id_persona')
            ->whereIn('e.id_estudiante', $ids)
            ->selectRaw("e.id_estudiante, trim(p.apellido_paterno || ' ' || coalesce(p.apellido_materno, '')) || ', ' || p.nombres as nombre")
            ->pluck('nombre', 'id_estudiante')->map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n)))->all();
    }

    /** @param iterable<int|string|null> $ids @return array<int, string> */
    public static function docentes(iterable $ids): array
    {
        $ids = self::ids($ids);

        return $ids === [] ? [] : DB::table('core.docente as d')
            ->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
            ->whereIn('d.id_docente', $ids)
            ->selectRaw("d.id_docente, trim(p.apellido_paterno || ' ' || coalesce(p.apellido_materno, '')) || ', ' || p.nombres as nombre")
            ->pluck('nombre', 'id_docente')->map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n)))->all();
    }

    /** Docentes disponibles como supervisores. @return array<int, string> */
    public static function docentesActivos(): array
    {
        return DB::table('core.docente as d')
            ->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
            ->whereRaw("lower(d.estado) = 'activo'")
            ->orderBy('p.apellido_paterno')->orderBy('p.nombres')
            ->selectRaw("d.id_docente, trim(p.apellido_paterno || ' ' || coalesce(p.apellido_materno, '')) || ', ' || p.nombres as nombre")
            ->pluck('nombre', 'id_docente')->map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n)))->all();
    }

    /** @return array<int, string> */
    public static function periodos(): array
    {
        return PeriodoAcademico::query()->orderByDesc('fecha_inicio')->pluck('codigo', 'id_periodo')->all();
    }

    /** Documentos de una práctica con los datos del archivo. */
    public static function documentosDe(int $idPractica): Collection
    {
        return DB::table('gestion_practicas.documento_practica as d')
            ->join('comun.archivo as a', 'a.id_archivo', '=', 'd.id_archivo')
            ->where('d.id_practica', $idPractica)
            ->orderByDesc('d.subido_en')->orderByDesc('d.id_documento')
            ->get(['d.*', 'a.nombre_original', 'a.tamano_bytes']);
    }

    /** @param iterable<int|string|null> $ids @return list<int> */
    private static function ids(iterable $ids): array
    {
        $limpios = [];

        foreach ($ids as $id) {
            if ($id !== null && $id !== '') {
                $limpios[(int) $id] = (int) $id;
            }
        }

        return array_values($limpios);
    }
}
