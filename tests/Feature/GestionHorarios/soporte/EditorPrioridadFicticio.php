<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Editor de ensayo sin ruta ni registro en producción. */
final class EditorPrioridadFicticio extends Component
{
    public array $cola = [];

    public array $docentes = [];

    public int $version = 0;

    public string $buscar = '';

    public string $mensaje = '';

    public string $periodo = '';

    public function boot(): void
    {
        $this->servicio()->consultar($this->actor());
    }

    private function servicio(): PrioridadDocenteFicticia
    {
        return new PrioridadDocenteFicticia(DB::connection());
    }

    private function actor(): User
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    public function mount(): void
    {
        $this->recargar();
    }

    public function recargar(): void
    {
        $datos = $this->servicio()->consultar($this->actor());
        $this->cola = $datos['cola'];
        $this->version = $datos['version'];
        $this->docentes = array_map(fn ($d) => ['id' => (int) $d->docente, 'nombre' => $d->nombre], $datos['docentes']);
    }

    public function agregar(int $id): void
    {
        if (! in_array($id, $this->cola, true) && in_array($id, array_column($this->docentes, 'id'), true)) {
            $this->cola[] = $id;
        }
    }

    public function retirar(int $id): void
    {
        $this->cola = array_values(array_filter($this->cola, fn ($actual) => $actual !== $id));
    }

    public function mover(int $id, int $destino): void
    {
        $origen = array_search($id, $this->cola, true);
        if ($origen === false || $destino < 0 || $destino >= count($this->cola)) {
            return;
        }
        array_splice($this->cola, $origen, 1);
        array_splice($this->cola, $destino, 0, [$id]);
    }

    public function guardar(): void
    {
        $resultado = $this->servicio()->guardar($this->actor(), $this->version, $this->cola);
        $this->version = $resultado['version'];
        $this->mensaje = 'Prioridad guardada';
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div>
            <h2>Prioridad docente — ensayo</h2>
            <label>Buscar docente <input wire:model.live="buscar"></label>
            <p role="status">{{ $mensaje }}</p>
            <ol>@foreach ($cola as $i => $id)
                @php($docente = collect($docentes)->firstWhere('id', $id))
                <li draggable="true" ondragstart="event.dataTransfer.setData('text/plain', '{{ $id }}')" ondragover="event.preventDefault()" ondrop="event.preventDefault(); $wire.mover(Number(event.dataTransfer.getData('text/plain')), {{ $i }})">
                    {{ $docente['nombre'] ?? 'Docente no disponible' }}
                    <button type="button" wire:click="mover({{ $id }}, {{ $i - 1 }})">Subir</button>
                    <button type="button" wire:click="mover({{ $id }}, {{ $i + 1 }})">Bajar</button>
                    <button type="button" wire:click="retirar({{ $id }})">Retirar</button>
                </li>
            @endforeach</ol>
            <ul>@foreach ($docentes as $docente)
                @if (! in_array($docente['id'], $cola, true) && str_contains(mb_strtolower($docente['nombre']), mb_strtolower($buscar)))
                    <li>{{ $docente['nombre'] }} <button type="button" wire:click="agregar({{ $docente['id'] }})">Agregar</button></li>
                @endif
            @endforeach</ul>
            @error('version') <p role="alert">{{ $message }}</p> @enderror
            @error('cola') <p role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="guardar">Guardar</button>
        </div>
        BLADE;
    }
}
