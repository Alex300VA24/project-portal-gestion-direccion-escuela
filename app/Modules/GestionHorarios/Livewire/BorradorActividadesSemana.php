<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\ConsultaSeccionesRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use App\Modules\GestionHorarios\Services\AmbitoPlanes;
use App\Modules\GestionHorarios\Services\BorradorSemana;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class BorradorActividadesSemana extends Component
{
    #[Locked] public int $plan;
    #[Locked] public int $periodo;
    #[Locked] public int $ciclo;
    #[Locked] public string $seccion;
    #[Locked] public array $sesiones = [];
    #[Locked] public array $bloques = [];
    #[Locked] public array $historial = [];
    #[Locked] public string $huella = '';

    public string $actividad = '';
    public string $dia = 'LUNES';
    public string $hora = '';

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
    }

    public function mount(int $plan, int $periodo, int $ciclo, string $seccion): void
    {
        $this->plan = $plan;
        $this->periodo = $periodo;
        $this->ciclo = $ciclo;
        $this->seccion = $seccion;
        $this->restablecer();
    }

    private function fuente(): array
    {
        abort_unless(app(AmbitoPlanes::class)->contiene(auth()->user(), $this->plan), 403);
        $db = DB::connection();
        abort_unless((new OfertaAcademicaRepository($db))->periodosParaPlan($this->plan)->contains('id_periodo', $this->periodo), 403);
        $sesiones = (new ConsultaSeccionesRepository($db))->semana($this->periodo, $this->plan, $this->ciclo, $this->seccion, true)->map(fn ($s) => (array) $s)->all();
        $bloques = $db->table('gestion_horarios.bloque_horario')->where('id_periodo', $this->periodo)->orderBy('hora_inicio')->orderBy('id_bloque')->get(['hora_inicio', 'hora_fin'])->map(fn ($b) => (array) $b)->all();
        abort_if(count($sesiones) > 150 || count($bloques) > 96, 422, 'Demasiados registros para una simulación semanal.');

        return [$sesiones, $bloques];
    }

    public function restablecer(): void
    {
        [$this->sesiones, $this->bloques] = $this->fuente();
        $this->huella = BorradorSemana::huella($this->sesiones, $this->bloques);
        $this->historial = [];
        $this->reset('actividad', 'dia', 'hora');
        $this->resetValidation();
    }

    private function cambioOrigen(): bool
    {
        return ! hash_equals($this->huella, BorradorSemana::huella(...$this->fuente()));
    }

    public function moverSeleccion(): void
    {
        $this->mover($this->actividad, $this->dia, $this->hora);
    }

    public function mover(string $id, string $dia, string $hora): void
    {
        $this->resetErrorBag('borrador');
        if ($this->cambioOrigen()) {
            $this->addError('borrador', 'Los datos de origen cambiaron. Restablezca el borrador antes de mover sesiones.');

            return;
        }
        try {
            $nuevas = BorradorSemana::mover($this->sesiones, $this->bloques, $id, $dia, $hora);
        } catch (\InvalidArgumentException $e) {
            $this->addError('borrador', $e->getMessage());

            return;
        }
        if ($nuevas !== $this->sesiones) {
            $this->historial[] = $this->sesiones;
            $this->historial = array_slice($this->historial, -5);
            $this->sesiones = $nuevas;
        }
    }

    public function deshacer(): void
    {
        $this->fuente(); // Revalidar ámbito también para acciones que no escriben en BD.
        if ($this->historial) {
            $this->sesiones = array_pop($this->historial);
        }
    }

    public function render(): View
    {
        return view('modules.gestion-horarios.borrador-actividades-semana', [
            'cambioOrigen' => $this->cambioOrigen(),
            'coincidencias' => BorradorSemana::coincidencias($this->sesiones),
            'dias' => BorradorSemana::DIAS,
        ]);
    }
}
