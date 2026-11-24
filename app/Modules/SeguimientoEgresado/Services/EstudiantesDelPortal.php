<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Models\Cohorte;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estudiantes del portal (esquema compartido `core`) que se pueden agregar a una promoción del padrón.
 *
 * Es una segunda forma de cargar alumnos, opcional: la carga por archivo sigue siendo la principal. Se asume que
 * `core.estudiante` y `core.persona` los mantiene otro módulo; aquí solo se leen. Si todavía no tienen datos (o las
 * tablas no existen), la lista simplemente sale vacía y el resto del padrón funciona igual.
 *
 * Un estudiante es candidato de una promoción cuando su periodo de ingreso es del año de esa promoción y todavía no
 * está en el padrón (ni por matrícula ni por documento). El padrón exige un correo para enviarle el enlace de
 * contacto inicial: el portal solo guarda el correo personal, así que ese es el que se usa (el correo
 * institucional del padrón queda con él). Quien no tiene ningún correo no se puede agregar y se indica en la lista.
 */
class EstudiantesDelPortal
{
    /** Filas que se muestran a la vez; el buscador acota el resto. */
    public const LIMITE = 200;

    public function disponible(): bool
    {
        try {
            return Schema::hasTable('core.estudiante') && Schema::hasTable('core.persona') && Schema::hasTable('core.periodo_academico');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{filas: Collection<int, object>, total: int}
     */
    public function candidatos(Cohorte $promocion, ?string $buscar = null): array
    {
        if (! $this->disponible()) {
            return ['filas' => collect(), 'total' => 0];
        }

        $consulta = $this->consulta($promocion);

        $buscar = trim((string) $buscar);
        if ($buscar !== '') {
            $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $buscar).'%';
            $consulta->where(function (Builder $q) use ($patron) {
                $q->where('e.codigo_universitario', 'ilike', $patron)
                    ->orWhere('p.numero_documento', 'ilike', $patron)
                    ->orWhere('p.nombres', 'ilike', $patron)
                    ->orWhere('p.apellido_paterno', 'ilike', $patron)
                    ->orWhere('p.apellido_materno', 'ilike', $patron);
            });
        }

        $total = (clone $consulta)->count();
        $filas = $consulta
            ->orderBy('p.apellido_paterno')->orderBy('p.apellido_materno')->orderBy('p.nombres')
            ->limit(self::LIMITE)
            ->get()
            ->map(function (object $f) {
                $f->correo = trim((string) $f->correo_personal);
                $f->puede_agregarse = $f->correo !== '' && filter_var($f->correo, FILTER_VALIDATE_EMAIL) !== false;

                return $f;
            });

        return ['filas' => $filas, 'total' => $total];
    }

    /**
     * Agrega al padrón, dentro de la promoción, a los estudiantes elegidos. Todo o nada, con las mismas reglas y el
     * mismo historial de cargas que el archivo. Quien ya no es candidato (porque se agregó entre tanto) o no tiene
     * correo se omite y se cuenta aparte.
     *
     * @param  list<int>  $ids  ids de core.estudiante
     * @return array{resultado: array<string, mixed>|null, omitidos: int}
     */
    public function agregar(Cohorte $promocion, array $ids, PadronImportService $importador): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $this->disponible() || $ids === []) {
            return ['resultado' => null, 'omitidos' => count($ids)];
        }

        $candidatos = $this->consulta($promocion)->whereIn('e.id_estudiante', $ids)->get();

        $filas = [];
        foreach ($candidatos as $c) {
            $correo = trim((string) $c->correo_personal);
            if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $filas[] = [
                'matricula' => $c->codigo_universitario,
                'tipo_documento' => $c->tipo_documento ?: 'DNI',
                'numero_documento' => $c->numero_documento,
                'nombres' => $c->nombres,
                'apellido_paterno' => $c->apellido_paterno,
                'apellido_materno' => $c->apellido_materno ?? '',
                'correo_institucional' => $correo,
                'correo_personal' => $correo,
                'cohorte' => (string) $promocion->anio_ingreso,
            ];
        }

        $omitidos = count($ids) - count($filas);
        if ($filas === []) {
            return ['resultado' => null, 'omitidos' => $omitidos];
        }

        return [
            'resultado' => $importador->importarDesdeFilas($filas, $promocion->id, null, 'Estudiantes del portal'),
            'omitidos' => $omitidos,
        ];
    }

    private function consulta(Cohorte $promocion): Builder
    {
        return DB::table('core.estudiante as e')
            ->join('core.persona as p', 'p.id_persona', '=', 'e.id_persona')
            ->join('core.periodo_academico as pe', 'pe.id_periodo', '=', 'e.id_periodo_ingreso')
            ->where('pe.anio', (int) $promocion->anio_ingreso)
            ->whereNotExists(function (Builder $q) {
                $q->select(DB::raw(1))->from('seguimiento_egresado.egresado as g')
                    ->whereColumn('g.codigo_matricula', 'e.codigo_universitario');
            })
            ->whereNotExists(function (Builder $q) {
                $q->select(DB::raw(1))->from('seguimiento_egresado.egresado as g')
                    ->whereColumn('g.numero_documento', 'p.numero_documento')
                    ->whereColumn('g.tipo_documento', 'p.tipo_documento');
            })
            ->select([
                'e.id_estudiante as id', 'e.codigo_universitario', 'e.estado',
                'p.tipo_documento', 'p.numero_documento', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno', 'p.correo_personal',
            ]);
    }
}
