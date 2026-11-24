<?php

namespace App\Modules\InvestigacionFormativa\Commands;

use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ImportarCompetenciasCommand extends AbstractImportCommand
{
    protected $signature = 'investigacion:importar-competencias
        {csv : Ruta del CSV}
        {--apply : Escribe los cambios}
        {--dry-run : Solo valida el archivo}';

    protected $description = 'Importa competencias oficiales por sección desde el sílabo aprobado.';

    public function handle(ConfiguracionService $configuracion): int
    {
        try {
            $apply = $this->applyRequested();
            $csv = $this->reader()->read((string) $this->argument('csv'), [
                'id_seccion',
                'codigo',
                'descripcion',
                'fuente',
                'seleccionada',
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $errors = [];
        $rows = [];
        $seen = [];

        foreach ($csv['rows'] as $row) {
            $line = $row['line'];
            $data = $row['data'];

            try {
                $idSeccion = $this->parseInt($data['id_seccion'], $line, 'id_seccion');
                $codigo = $this->requiredText($data['codigo'], $line, 'codigo', 60);
                $descripcion = $this->requiredText($data['descripcion'], $line, 'descripcion', 20000);
                $fuente = $this->requiredText($data['fuente'], $line, 'fuente', 250);
                $seleccionada = $this->parseBool($data['seleccionada'], $line, 'seleccionada');
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            if (! DB::table('core.seccion')->where('id_seccion', $idSeccion)->exists()) {
                $errors[] = "Línea {$line}: no existe core.seccion con id {$idSeccion}.";
            }

            $key = $idSeccion.':'.$codigo;

            if (isset($seen[$key])) {
                $errors[] = "Línea {$line}: la competencia {$codigo} aparece más de una vez en la sección {$idSeccion}.";
            }
            $seen[$key] = true;

            $rows[] = [
                'id_seccion' => $idSeccion,
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'fuente' => $fuente,
                'seleccionada' => $seleccionada,
            ];
        }

        if ($errors !== []) {
            return $this->reportErrors($errors);
        }

        if ($rows === []) {
            $this->components->error('El CSV no contiene filas de datos.');

            return self::FAILURE;
        }

        $filasPorSeccion = [];
        $secciones = [];

        foreach ($rows as $row) {
            $idSeccion = $row['id_seccion'];
            $filasPorSeccion[$idSeccion][] = $row;
            $secciones[$idSeccion] = true;
        }

        $secciones = array_map('intval', array_keys($secciones));
        sort($secciones, SORT_NUMERIC);

        $created = 0;
        $updated = 0;

        try {
            DB::transaction(function () use (
                $apply,
                $configuracion,
                $filasPorSeccion,
                $secciones,
                &$created,
                &$updated,
            ): void {
                foreach ($secciones as $idSeccion) {
                    if ($apply) {
                        $configuracion->bloquearParaEscritura($idSeccion);
                    } else {
                        DB::table('investigacion_formativa.seccion_configuracion')
                            ->where('id_seccion', $idSeccion)
                            ->lockForUpdate()
                            ->first();
                    }

                    if ($configuracion->rubricaCongelada($idSeccion)) {
                        throw new EstadoInvalido(
                            "La sección {$idSeccion} ya tiene entregas. No se pueden reemplazar competencias.",
                        );
                    }

                    if (! $apply) {
                        continue;
                    }

                    foreach ($filasPorSeccion[$idSeccion] as $row) {
                        $exists = DB::table('investigacion_formativa.competencia_seccion')
                            ->where('id_seccion', $row['id_seccion'])
                            ->where('codigo', $row['codigo'])
                            ->exists();

                        if ($exists) {
                            DB::table('investigacion_formativa.competencia_seccion')
                                ->where('id_seccion', $row['id_seccion'])
                                ->where('codigo', $row['codigo'])
                                ->update([
                                    'descripcion' => $row['descripcion'],
                                    'fuente' => $row['fuente'],
                                ]);
                            $updated++;
                        } else {
                            DB::table('investigacion_formativa.competencia_seccion')->insert([
                                'id_seccion' => $row['id_seccion'],
                                'codigo' => $row['codigo'],
                                'descripcion' => $row['descripcion'],
                                'fuente' => $row['fuente'],
                                'seleccionada' => $row['seleccionada'],
                            ]);
                            $created++;
                        }
                    }

                    $configuracion->sincronizarVersionPorImportacion($idSeccion);
                }
            });
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $apply) {
            $this->components->info(
                'Validación correcta: '.count($rows).' filas. No se escribió nada. Use --apply para aplicar.',
            );

            return self::SUCCESS;
        }

        $this->components->info(
            "Competencias importadas: {$created} creadas, {$updated} actualizadas (selección existente preservada).",
        );

        return self::SUCCESS;
    }
}
