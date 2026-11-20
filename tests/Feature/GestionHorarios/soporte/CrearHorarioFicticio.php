<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Modules\GestionHorarios\Engine\EnsambladorAcademicoPrueba;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Component;
use RuntimeException;

/** Componente de ensayo sin ruta ni registro en producción. */
final class CrearHorarioFicticio extends Component
{
    public string $periodo = '1';

    public int $tiempo = 150;

    public ?string $huella = null;

    public array $diagnosticos = [];

    public ?string $solicitud = null;

    public function boot(): void
    {
        $db = DB::connection();
        $cfg = $db->getConfig();
        if (! app()->environment('testing') || $db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('El ensayo requiere PostgreSQL aislado.');
        }
        if (config('queue.connections.database.driver') !== 'database' || config('queue.connections.database.connection') !== config('database.default')) {
            throw new RuntimeException('La cola del ensayo debe usar la misma conexión aislada.');
        }
        $identidad = $db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($identidad->base !== 'horarios_27b2_test' || $identidad->usuario !== 'horarios_test' || (int) $identidad->version < 170000 || (int) $identidad->version >= 180000) {
            throw new RuntimeException('Identidad de base no válida.');
        }
    }

    private function preparar(): array
    {
        $this->validate(['periodo' => 'required|integer|min:1|max:2147483647', 'tiempo' => 'required|integer|min:1|max:150']);
        $db = DB::connection();

        return (new EnsambladorAcademicoPrueba($db, new ProveedorComplementosPostgresFicticio($db, $this->tiempo)))
            ->construir((new InstantaneaGeneracion($db))->construir((int) $this->periodo), '1');
    }

    public function comprobar(): void
    {
        $preparada = $this->preparar();
        $this->diagnosticos = $preparada['diagnosticos'];
        $this->huella = $preparada['ejecutable'] ? $preparada['huella_sha256'] : null;
    }

    public function solicitar(): void
    {
        $preparada = $this->preparar();
        if (! $preparada['ejecutable'] || ! is_string($this->huella) || ! hash_equals($this->huella, $preparada['huella_sha256'])) {
            $this->huella = null;
            $this->diagnosticos = $preparada['diagnosticos'];
            $this->addError('preparacion', 'La preparación cambió o no está completa. Compruebe los datos nuevamente.');

            return;
        }
        $id = (string) Str::uuid();
        DB::transaction(function () use ($id, $preparada): void {
            DB::table('prueba.solicitud_cola')->insert(['id' => $id, 'estado' => 'EN_COLA', 'preparada' => json_encode($preparada, JSON_THROW_ON_ERROR), 'entrada_sha256' => $preparada['huella_sha256'], 'academica_sha256' => $preparada['huella_academica_sha256']]);
            Queue::connection('database')->pushOn('gh-27b5-prueba', new EjecutarPreparacionColaFicticia($id));
        });
        $this->solicitud = $id;
        $this->huella = null;
    }

    public function render()
    {
        $fila = $this->solicitud ? DB::table('prueba.solicitud_cola')->where('id', $this->solicitud)->first() : null;
        $propuesta = $fila && $fila->estado === 'FINALIZADO' ? DB::table('prueba.propuesta_cpsat')->where('solicitud_sha256', $fila->entrada_sha256)->first() : null;

        return view()->file(__DIR__.'/crear-horario-ficticio.blade.php', compact('fila', 'propuesta'));
    }
}
