<?php

namespace App\Modules\InvestigacionFormativa\Commands;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ImportarIdentidadesCommand extends AbstractImportCommand
{
    protected $signature = 'investigacion:importar-identidades
        {csv : Ruta del CSV}
        {--apply : Escribe los cambios}
        {--dry-run : Solo valida el archivo}';

    protected $description = 'Importa la vinculación explícita entre usuarios de Laravel y usuarios académicos.';

    public function handle(): int
    {
        try {
            $apply = $this->applyRequested();
            $csv = $this->reader()->read((string) $this->argument('csv'), [
                'user_id',
                'id_usuario_seguridad',
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
        $seenUsers = [];
        $seenAcademicUsers = [];

        foreach ($csv['rows'] as $row) {
            $line = $row['line'];
            $data = $row['data'];

            try {
                $userId = $this->parseInt($data['user_id'], $line, 'user_id');
                $idUsuarioSeguridad = $this->parseInt(
                    $data['id_usuario_seguridad'],
                    $line,
                    'id_usuario_seguridad',
                );
                $activo = $this->parseBool($data['activo'], $line, 'activo');
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            if (isset($seenUsers[$userId])) {
                $errors[] = "Línea {$line}: user_id {$userId} aparece más de una vez.";
            }
            $seenUsers[$userId] = true;

            if (isset($seenAcademicUsers[$idUsuarioSeguridad])) {
                $errors[] = "Línea {$line}: id_usuario_seguridad {$idUsuarioSeguridad} aparece más de una vez.";
            }
            $seenAcademicUsers[$idUsuarioSeguridad] = true;

            if (! DB::table('users')->where('id', $userId)->exists()) {
                $errors[] = "Línea {$line}: no existe public.users con id {$userId}.";
            }

            if (! DB::table('seguridad.usuario')->where('id_usuario', $idUsuarioSeguridad)->exists()) {
                $errors[] = "Línea {$line}: no existe seguridad.usuario con id {$idUsuarioSeguridad}.";
            }

            $existingForUser = DB::table('investigacion_formativa.identidad_usuario')
                ->where('user_id', $userId)
                ->value('id_usuario_seguridad');

            if ($existingForUser !== null && (int) $existingForUser !== $idUsuarioSeguridad) {
                $errors[] = "Línea {$line}: el usuario {$userId} ya está vinculado a otro usuario académico.";
            }

            $existingForAcademic = DB::table('investigacion_formativa.identidad_usuario')
                ->where('id_usuario_seguridad', $idUsuarioSeguridad)
                ->value('user_id');

            if ($existingForAcademic !== null && (int) $existingForAcademic !== $userId) {
                $errors[] = "Línea {$line}: el usuario académico {$idUsuarioSeguridad} ya está vinculado a otro usuario.";
            }

            $rows[] = [
                'user_id' => $userId,
                'id_usuario_seguridad' => $idUsuarioSeguridad,
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
                $exists = DB::table('investigacion_formativa.identidad_usuario')
                    ->where('user_id', $row['user_id'])
                    ->exists();

                DB::table('investigacion_formativa.identidad_usuario')->updateOrInsert(
                    ['user_id' => $row['user_id']],
                    [
                        'id_usuario_seguridad' => $row['id_usuario_seguridad'],
                        'activo' => $row['activo'],
                    ],
                );

                $exists ? $updated++ : $created++;
            }
        });

        $this->components->info("Identidades importadas: {$created} creadas, {$updated} actualizadas.");

        return self::SUCCESS;
    }
}
