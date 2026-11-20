<?php

namespace App\Modules\InvestigacionFormativa\Commands;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ImportarDirectoresCommand extends AbstractImportCommand
{
    protected $signature = 'investigacion:importar-directores
        {csv : Ruta del CSV}
        {--apply : Escribe los cambios}
        {--dry-run : Solo valida el archivo}';

    protected $description = 'Importa el alcance de escuelas por usuario con rol de director.';

    public function handle(): int
    {
        try {
            $apply = $this->applyRequested();
            $csv = $this->reader()->read((string) $this->argument('csv'), [
                'user_id',
                'id_escuela',
                'activo',
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
                $userId = $this->parseInt($data['user_id'], $line, 'user_id');
                $idEscuela = $this->parseInt($data['id_escuela'], $line, 'id_escuela');
                $activo = $this->parseBool($data['activo'], $line, 'activo');
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            $key = $userId.':'.$idEscuela;

            if (isset($seen[$key])) {
                $errors[] = "Línea {$line}: la combinación user_id/id_escuela aparece más de una vez.";
            }
            $seen[$key] = true;

            if (! DB::table('users')->where('id', $userId)->exists()) {
                $errors[] = "Línea {$line}: no existe public.users con id {$userId}.";
            }

            if (! DB::table('core.escuela_profesional')->where('id_escuela', $idEscuela)->exists()) {
                $errors[] = "Línea {$line}: no existe core.escuela_profesional con id {$idEscuela}.";
            }

            $rows[] = [
                'user_id' => $userId,
                'id_escuela' => $idEscuela,
                'activo' => $activo,
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

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            foreach ($rows as $row) {
                $exists = DB::table('investigacion_formativa.director_escuela')
                    ->where('user_id', $row['user_id'])
                    ->where('id_escuela', $row['id_escuela'])
                    ->exists();

                DB::table('investigacion_formativa.director_escuela')->updateOrInsert(
                    ['user_id' => $row['user_id'], 'id_escuela' => $row['id_escuela']],
                    ['activo' => $row['activo']],
                );

                $exists ? $updated++ : $created++;
            }
        });

        $this->components->info("Alcances importados: {$created} creados, {$updated} actualizados.");

        return self::SUCCESS;
    }
}
