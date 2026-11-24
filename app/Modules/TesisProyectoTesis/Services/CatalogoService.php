<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos compartidos (core / seguridad) que usa el módulo: líneas, periodos,
 * ambientes y el id del módulo. Solo lectura y con columnas explícitas.
 */
final class CatalogoService
{
    private ?int $idModulo = null;

    /** @return list<array{id: int, codigo: string, nombre: string}> */
    public function lineas(): array
    {
        return DB::table('core.linea_investigacion')
            ->where('estado', 'Activo')
            ->orderBy('nombre')
            ->get(['id_linea', 'codigo', 'nombre'])
            ->map(fn ($r) => ['id' => (int) $r->id_linea, 'codigo' => (string) $r->codigo, 'nombre' => (string) $r->nombre])
            ->all();
    }

    public function lineaActivaExiste(int $id): bool
    {
        return DB::table('core.linea_investigacion')->where('id_linea', $id)->where('estado', 'Activo')->exists();
    }

    /** Id de una línea activa a partir de su código (los formularios nunca envían ids). */
    public function idLineaPorCodigo(string $codigo): ?int
    {
        $id = DB::table('core.linea_investigacion')->where('codigo', $codigo)->where('estado', 'Activo')->value('id_linea');

        return $id !== null ? (int) $id : null;
    }

    /** @return list<string> */
    public function codigosLineas(): array
    {
        return array_column($this->lineas(), 'codigo');
    }

    /** @param list<int> $ids @return array<int, string> */
    public function nombresLineas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('core.linea_investigacion')
            ->whereIn('id_linea', array_values(array_unique($ids)))
            ->pluck('nombre', 'id_linea')
            ->mapWithKeys(fn ($n, $id) => [(int) $id => (string) $n])
            ->all();
    }

    /** @return list<int> docentes especialistas de una línea */
    public function docentesDeLinea(int $idLinea): array
    {
        return DB::table('core.docente_linea')->where('id_linea', $idLinea)->pluck('id_docente')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Periodo en curso (o el más reciente si hoy no cae en ninguno).
     */
    public function periodoActual(): ?PeriodoAcademico
    {
        $hoy = Carbon::today()->toDateString();

        return PeriodoAcademico::query()
            ->select(['id_periodo', 'codigo', 'fecha_inicio', 'fecha_fin', 'numero_semanas', 'estado'])
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy)
            ->orderByDesc('fecha_inicio')
            ->first()
            ?? PeriodoAcademico::query()
                ->select(['id_periodo', 'codigo', 'fecha_inicio', 'fecha_fin', 'numero_semanas', 'estado'])
                ->orderByDesc('fecha_inicio')
                ->first();
    }

    public function periodo(?int $id): ?PeriodoAcademico
    {
        return $id === null ? null : PeriodoAcademico::query()
            ->select(['id_periodo', 'codigo', 'fecha_inicio', 'fecha_fin', 'numero_semanas', 'estado'])
            ->find($id);
    }

    /**
     * Semana del ciclo (1..N) para la fecha dada dentro del periodo.
     */
    public function semanaDe(?PeriodoAcademico $periodo, ?Carbon $fecha = null): int
    {
        if ($periodo === null) {
            return 1;
        }

        $total = (int) ($periodo->numero_semanas ?: Parametros::SEMANAS_CICLO);
        $fecha ??= Carbon::today();
        $dias = $periodo->fecha_inicio->diffInDays($fecha, false);

        return (int) max(1, min($total, intdiv((int) floor($dias), 7) + 1));
    }

    /** @return list<array{id: int, codigo: string, nombre: string, capacidad: int}> */
    public function ambientes(): array
    {
        return DB::table('core.ambiente')
            ->where('estado', 'Activo')
            ->orderBy('codigo')
            ->get(['id_ambiente', 'codigo', 'nombre', 'capacidad'])
            ->map(fn ($r) => ['id' => (int) $r->id_ambiente, 'codigo' => (string) $r->codigo, 'nombre' => (string) $r->nombre, 'capacidad' => (int) $r->capacidad])
            ->all();
    }

    public function idAmbientePorCodigo(string $codigo): ?int
    {
        $id = DB::table('core.ambiente')->where('codigo', $codigo)->where('estado', 'Activo')->value('id_ambiente');

        return $id !== null ? (int) $id : null;
    }

    public function ambienteActivoExiste(int $id): bool
    {
        return DB::table('core.ambiente')->where('id_ambiente', $id)->where('estado', 'Activo')->exists();
    }

    public function nombreAmbiente(?int $id): string
    {
        if ($id === null) {
            return '—';
        }

        $a = DB::table('core.ambiente')->where('id_ambiente', $id)->first(['codigo', 'nombre']);

        return $a ? trim($a->codigo.' · '.$a->nombre) : '—';
    }

    /**
     * Id del módulo en seguridad.modulo_sistema (para comun.archivo y comun.notificacion).
     */
    public function idModulo(): int
    {
        return $this->idModulo ??= (int) DB::table('seguridad.modulo_sistema')
            ->where('esquema', Parametros::MODULO_ESQUEMA)
            ->value('id_modulo');
    }
}
