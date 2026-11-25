<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cola global versionada SOLO QA, sin efecto en el motor institucional. */
final class PrioridadDocenteDemo
{
    public const TABLA = 'gestion_horarios.qa_prioridad_docente_revision';

    public function disponible(): bool
    {
        return DB::connection()->getSchemaBuilder()->hasTable(self::TABLA);
    }

    private function exigir(User $usuario): void
    {
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        abort_unless($this->disponible(), 404, 'Prepare el almacenamiento local de prioridad.');
    }

    private function datos(): array
    {
        $docentes = DB::table('core.docente as d')->join('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
            ->whereRaw('upper(d.estado) = ?', ['ACTIVO'])->where('p.tipo_documento', 'QA')->where('d.codigo_docente', 'like', 'QA-%')
            ->orderBy('d.codigo_docente')->get(['d.id_docente', 'd.codigo_docente', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno']);
        $revision = DB::table(self::TABLA)->orderByDesc('version')->first();
        $orden = $revision ? json_decode($revision->orden, true, 512, JSON_THROW_ON_ERROR) : [];
        $version = (int) ($revision?->version ?? 0);
        $huella = hash('sha256', json_encode([$version, $orden, $docentes->pluck('id_docente')->all()], JSON_THROW_ON_ERROR));
        return compact('docentes', 'orden', 'version', 'huella');
    }

    public function leer(User $usuario): array
    {
        $this->exigir($usuario);
        return DB::transaction(function () use ($usuario) {
            DB::statement('LOCK TABLE core.docente, core.persona, '.self::TABLA.' IN SHARE MODE');
            $this->exigir($usuario);
            return $this->datos();
        });
    }

    public static function validarOrden(array $orden, array $permitidos): array
    {
        $permitidos = array_map('strval', $permitidos);
        $resultado = [];
        foreach ($orden as $id) {
            if ((! is_int($id) && ! is_string($id)) || ! in_array((string) $id, $permitidos, true) || in_array((int) $id, $resultado, true)) {
                throw new \InvalidArgumentException('La cola contiene un docente inválido, inactivo o duplicado.');
            }
            $resultado[] = (int) $id;
        }
        return $resultado;
    }

    public function guardar(User $usuario, string $huella, array $orden): void
    {
        $this->exigir($usuario);
        DB::transaction(function () use ($usuario, $huella, $orden) {
            DB::statement('LOCK TABLE core.docente, core.persona IN SHARE MODE');
            DB::statement('LOCK TABLE '.self::TABLA.' IN SHARE ROW EXCLUSIVE MODE');
            $this->exigir($usuario);
            $actual = $this->datos();
            if (! hash_equals($actual['huella'], $huella)) {
                throw ValidationException::withMessages(['ordenPrioridad' => 'La cola o el catálogo cambió. Recargue antes de guardar.']);
            }
            try {
                $orden = self::validarOrden($orden, $actual['docentes']->pluck('id_docente')->all());
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['ordenPrioridad' => $e->getMessage()]);
            }
            if ($orden === $actual['orden']) { return; }
            DB::table(self::TABLA)->insert(['version' => $actual['version'] + 1, 'orden' => json_encode($orden, JSON_THROW_ON_ERROR), 'actor_qa' => $usuario->email]);
        }, 3);
    }
}
