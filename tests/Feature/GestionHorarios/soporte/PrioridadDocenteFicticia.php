<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Persistencia de ensayo, sin registro ni acceso desde rutas institucionales. */
final class PrioridadDocenteFicticia
{
    public function __construct(private readonly ConnectionInterface $db) {}

    private function escuela(User $actor): int
    {
        $cfg = $this->db->getConfig();
        if (! app()->environment('testing') || $this->db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('Se requiere PostgreSQL aislado de pruebas.');
        }
        $real = $this->db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_27b2_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('Identidad PostgreSQL inválida.');
        }
        $escuela = $this->db->table('prueba.prioridad_actor')->where('usuario_id', $actor->id)->whereIn('rol', ['DIRECTOR', 'SECRETARIA'])->where('activo', true)->value('escuela');
        if (! $escuela) {
            throw new AuthorizationException;
        }

        return (int) $escuela;
    }

    public function consultar(User $actor): array
    {
        $escuela = $this->escuela($actor);

        return $this->db->transaction(function () use ($escuela): array {
            $config = $this->db->table('prueba.prioridad_config')->where('escuela', $escuela)->sharedLock()->first();
            if (! $config) {
                throw new AuthorizationException;
            }

            return ['escuela' => $escuela, 'version' => (int) $config->version, 'cola' => $this->db->table('prueba.prioridad_orden')->where('escuela', $escuela)->orderBy('posicion')->pluck('docente')->map(fn ($id) => (int) $id)->all(), 'docentes' => $this->db->table('prueba.prioridad_catalogo')->where('escuela', $escuela)->where('activo', true)->orderBy('nombre')->get()->all()];
        });
    }

    public function guardar(User $actor, int $version, array $ids): array
    {
        $escuela = $this->escuela($actor);
        if (! array_is_list($ids) || count($ids) > 500 || count($ids) !== count(array_unique($ids, SORT_REGULAR)) || count(array_filter($ids, fn ($id) => is_int($id) && $id > 0)) !== count($ids)) {
            throw ValidationException::withMessages(['cola' => 'La lista contiene identificadores inválidos o duplicados.']);
        }

        return $this->db->transaction(function () use ($actor, $escuela, $version, $ids): array {
            $config = $this->db->table('prueba.prioridad_config')->where('escuela', $escuela)->lockForUpdate()->first();
            if (! $config || (int) $config->version !== $version) {
                throw ValidationException::withMessages(['version' => 'Otra persona modificó la cola; recargue antes de guardar.']);
            }
            $validos = $this->db->table('prueba.prioridad_catalogo')->where('escuela', $escuela)->where('activo', true)->whereIn('docente', $ids)->count();
            if ($validos !== count($ids)) {
                throw ValidationException::withMessages(['cola' => 'Hay docentes no disponibles en esta escuela.']);
            }
            $anterior = $this->db->table('prueba.prioridad_orden')->where('escuela', $escuela)->orderBy('posicion')->pluck('docente')->map(fn ($id) => (int) $id)->all();
            if ($anterior === $ids) {
                return ['version' => $version, 'cola' => $ids];
            }
            $this->db->table('prueba.prioridad_orden')->where('escuela', $escuela)->delete();
            foreach ($ids as $i => $id) {
                $this->db->table('prueba.prioridad_orden')->insert(['escuela' => $escuela, 'docente' => $id, 'posicion' => $i + 1]);
            }
            $this->db->table('prueba.prioridad_config')->where('escuela', $escuela)->update(['version' => $version + 1]);
            $this->db->table('prueba.prioridad_historial')->insert(['escuela' => $escuela, 'version' => $version + 1, 'actor' => $actor->id, 'anterior' => json_encode($anterior, JSON_THROW_ON_ERROR), 'nuevo' => json_encode($ids, JSON_THROW_ON_ERROR)]);

            return ['version' => $version + 1, 'cola' => $ids];
        });
    }
}
