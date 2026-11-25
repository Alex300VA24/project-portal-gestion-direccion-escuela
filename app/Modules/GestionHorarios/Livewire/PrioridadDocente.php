<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\PrioridadDocenteDemo;
use Illuminate\Database\QueryException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class PrioridadDocente extends Component
{
    #[Locked] public array $orden = [];
    #[Locked] public string $huella = '';
    #[Locked] public int $version = 0;
    public string $candidato = '';
    public string $mensaje = '';

    public function boot(): void { abort_unless(ConsultaLocalDemostracion::permitida(auth()->user()), 403); }
    public function mount(): void { if ((new PrioridadDocenteDemo)->disponible()) { $this->recargar(); } }
    public function recargar(): void
    {
        $datos = (new PrioridadDocenteDemo)->leer(auth()->user());
        $this->orden = $datos['orden']; $this->version = $datos['version']; $this->huella = $datos['huella'];
        $this->candidato = ''; $this->mensaje = ''; $this->resetValidation();
    }
    public function agregar(): void
    {
        $this->validate(['candidato' => 'required|integer|min:1']);
        $datos = (new PrioridadDocenteDemo)->leer(auth()->user());
        $id = (int) $this->candidato;
        if (! $datos['docentes']->contains(fn ($d) => (int) $d->id_docente === $id) || in_array($id, $this->orden, true)) {
            $this->addError('ordenPrioridad', 'Seleccione un docente activo que no esté en la cola.'); return;
        }
        $this->orden[] = $id; $this->candidato = ''; $this->mensaje = '';
    }
    public function mover(int $id, int $paso): void
    {
        if (! in_array($paso, [-1, 1], true)) { return; }
        $i = array_search($id, $this->orden, true);
        if ($i === false || ! isset($this->orden[$i + $paso])) { return; }
        [$this->orden[$i], $this->orden[$i + $paso]] = [$this->orden[$i + $paso], $this->orden[$i]];
        $this->mensaje = '';
    }
    public function quitar(int $id): void { $this->orden = array_values(array_filter($this->orden, fn ($x) => $x !== $id)); $this->mensaje = ''; }
    public function guardar(): void
    {
        abort_unless($this->huella !== '', 422);
        try {
            (new PrioridadDocenteDemo)->guardar(auth()->user(), $this->huella, $this->orden);
            $this->recargar(); $this->mensaje = 'Orden de demostración guardado.';
        } catch (QueryException $e) {
            report($e); $this->addError('ordenPrioridad', 'No se pudo guardar la cola.');
        }
    }
    public function render(): View
    {
        $servicio = new PrioridadDocenteDemo;
        $disponible = $servicio->disponible();
        $datos = $disponible ? $servicio->leer(auth()->user()) : ['docentes' => collect(), 'orden' => [], 'huella' => ''];
        $docentes = $datos['docentes']->keyBy('id_docente');
        $candidatos = $docentes->reject(fn ($d) => in_array((int) $d->id_docente, $this->orden, true));
        $cambioRemoto = $disponible && $this->huella !== $datos['huella'];
        $modificado = $this->orden !== $datos['orden'];
        return view('modules.gestion-horarios.prioridad-docente', compact('disponible', 'docentes', 'candidatos', 'cambioRemoto', 'modificado'));
    }
}
