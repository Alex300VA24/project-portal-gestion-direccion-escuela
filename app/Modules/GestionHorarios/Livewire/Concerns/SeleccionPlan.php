<?php

namespace App\Modules\GestionHorarios\Livewire\Concerns;

use App\Modules\GestionHorarios\Services\AmbitoPlanes;
use App\Shared\Models\Core\CursoPlan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait SeleccionPlan
{
    public string $plan = '';

    public string $avisoPlan = '';

    public function updatedPlan(): void
    {
        $this->avisoPlan = '';
        if (property_exists($this, 'verPreparacion')) {
            $this->verPreparacion = false;
        }
        if (property_exists($this, 'cursoFormulario')) {
            $this->cursoFormulario = '';
        }
        if (property_exists($this, 'ofertaEditar')) {
            $this->ofertaEditar = null;
            $this->formularioAbierto = false;
            $this->mensaje = '';
            $this->resetValidation();
        }
        try {
            $contexto = $this->contextoPlan();
        } catch (QueryException $e) {
            report($e);
            $this->avisoPlan = 'No se pudo consultar el plan. Intente nuevamente o comuníquese con soporte.';

            return;
        }
        if ($this->ciclo !== '' && ! $contexto['ciclos']->contains((int) $this->ciclo)) {
            $this->ciclo = '';
            $this->avisoPlan = 'El ciclo seleccionado no existe en el plan elegido; se limpió el filtro.';
        }
    }

    private function contextoPlan(): array
    {
        $conexion = DB::connection()->getName();
        $planes = app(AmbitoPlanes::class)->consultar(auth()->user())->orderBy('id_escuela')->orderBy('codigo')->get(['id_plan', 'id_escuela', 'codigo', 'nombre']);
        $id = ctype_digit($this->plan) && strlen($this->plan) < 19 ? (int) $this->plan : null;
        $valido = $id !== null && $planes->contains('id_plan', $id);
        $ciclos = $valido ? CursoPlan::on($conexion)->where('id_plan', $id)->whereNotNull('ciclo')->distinct()->orderBy('ciclo')->pluck('ciclo') : collect();

        return ['planes' => $planes, 'ciclos' => $ciclos, 'planValido' => $valido, 'planId' => $valido ? $id : 0];
    }
}
