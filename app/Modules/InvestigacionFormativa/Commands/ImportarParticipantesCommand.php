<?php

namespace App\Modules\InvestigacionFormativa\Commands;

use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ImportarParticipantesCommand extends AbstractImportCommand
{
    protected $signature = 'investigacion:importar-participantes
        {csv : Ruta del CSV}
        {--apply : Escribe los cambios}
        {--dry-run : Solo valida el archivo}';

    protected $description = 'Importa la instantánea autorizada de participantes por sección.';

    public function handle(DatosAcademicosService $datos, ConfiguracionService $configuracion): int
    {
        try {
            $apply = $this->applyRequested();
            $csv = $this->reader()->read((string) $this->argument('csv'), [
                'id_seccion',
                'id_estudiante',
                'estado',
                'fuente',
                'referencia_origen',
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
                $idEstudiante = $this->parseInt($data['id_estudiante'], $line, 'id_estudiante');
                $estado = mb_strtolower(trim($data['estado']));
                $fuente = $this->requiredText($data['fuente'], $line, 'fuente', 120);
                $referencia = $this->requiredText(
                    $data['referencia_origen'],
                    $line,
                    'referencia_origen',
                    250,
                );
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            if (! in_array($estado, ['activo', 'retirado'], true)) {
                $errors[] = "Línea {$line}: estado debe ser 'activo' o 'retirado'.";
            }

            $key = $idSeccion.':'.$idEstudiante;

            if (isset($seen[$key])) {
                $errors[] = "Línea {$line}: la combinación id_seccion/id_estudiante aparece más de una vez.";
            }
            $seen[$key] = true;

            $seccion = $datos->seccionConEscuela($idSeccion);

            if ($seccion === null) {
                $errors[] = "Línea {$line}: no existe core.seccion con id {$idSeccion}.";
            } elseif ($seccion->id_escuela === null) {
                $errors[] = "Línea {$line}: la sección {$idSeccion} no tiene una escuela configurada.";
            }

            $estudiante = DB::table('core.estudiante')
                ->where('id_estudiante', $idEstudiante)
                ->select(['id_estudiante', 'id_escuela'])
                ->first();

            if ($estudiante === null) {
                $errors[] = "Línea {$line}: no existe core.estudiante con id {$idEstudiante}.";
            } elseif ($seccion !== null && $seccion->id_escuela !== null
                && (int) $estudiante->id_escuela !== $seccion->id_escuela) {
                $errors[] = "Línea {$line}: el estudiante {$idEstudiante} no pertenece a la escuela de la sección.";
            }

            $rows[] = [
                'id_seccion' => $idSeccion,
                'id_estudiante' => $idEstudiante,
                'estado' => $estado,
                'fuente' => $fuente,
                'referencia_origen' => $referencia,
            ];
        }

        if ($errors !== []) {
            return $this->reportErrors($errors);
        }

        if ($rows === []) {
            $this->components->error('El CSV no contiene filas de datos.');

            return self::FAILURE;
        }

        if (! $apply) {
            $this->components->info(
                'Validación correcta: '.count($rows).' filas. No se escribió nada. Use --apply para aplicar.',
            );

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $configuracion, &$created, &$updated): void {
            $secciones = array_values(array_unique(array_column($rows, 'id_seccion')));
            sort($secciones, SORT_NUMERIC);

            foreach ($secciones as $idSeccion) {
                $configuracion->bloquearParaEscritura($idSeccion);
            }

            foreach ($rows as $row) {
                $exists = DB::table('investigacion_formativa.participante_seccion')
                    ->where('id_seccion', $row['id_seccion'])
                    ->where('id_estudiante', $row['id_estudiante'])
                    ->exists();

                DB::table('investigacion_formativa.participante_seccion')->updateOrInsert(
                    [
                        'id_seccion' => $row['id_seccion'],
                        'id_estudiante' => $row['id_estudiante'],
                    ],
                    [
                        'estado' => $row['estado'],
                        'fuente' => $row['fuente'],
                        'referencia_origen' => $row['referencia_origen'],
                        'sincronizado_en' => now('UTC')->format('Y-m-d H:i:sP'),
                    ],
                );

                $exists ? $updated++ : $created++;
            }
        });

        $this->components->info("Participantes importados: {$created} creados, {$updated} actualizados.");

        return self::SUCCESS;
    }
}
