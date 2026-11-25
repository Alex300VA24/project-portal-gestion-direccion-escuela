<?php

namespace App\Modules\GestionHorarios\Livewire;

use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ConsultaHorarios;
use App\Modules\GestionHorarios\Services\ExportacionHorarios;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HorariosRegistrados extends Component
{
    public string $periodo = '';

    public string $horario = '';

    public string $ciclo = '';

    public string $seccion = '';

    public string $errorDescarga = '';

    public function boot(): void
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        ConsultaLocalDemostracion::exigir(auth()->user());
    }

    public function mount(): void
    {
        $periodo = request()->query('periodo', session('gestion_horarios.periodo_seleccionado', ''));
        if ((is_string($periodo) || is_int($periodo)) && ctype_digit((string) $periodo) && strlen((string) $periodo) < 19) {
            $this->periodo = (string) $periodo;
        }
    }

    public function updatedPeriodo(): void
    {
        $this->horario = '';
    }

    public function descargar(string $formato): ?StreamedResponse
    {
        abort_unless(auth()->user() && (new RegistroPolicy)->viewAny(auth()->user()), 403);
        ConsultaLocalDemostracion::exigir(auth()->user());
        abort_unless(in_array($formato, ['pdf', 'xlsx'], true), 404);
        $this->errorDescarga = '';
        if (! ctype_digit($this->periodo) || strlen($this->periodo) >= 19 || ! ctype_digit($this->horario) || strlen($this->horario) >= 19 || ($this->ciclo !== '' && (! ctype_digit($this->ciclo) || strlen($this->ciclo) > 2)) || mb_strlen($this->seccion) > 30) {
            $this->errorDescarga = 'Seleccione un periodo, un horario y filtros válidos.';

            return null;
        }
        if ($formato === 'xlsx' && (! extension_loaded('gd') || ! extension_loaded('zip'))) {
            $this->errorDescarga = 'La exportación Excel no está disponible en este servidor. Comuníquese con soporte.';

            return null;
        }
        try {
            $servicio = new ExportacionHorarios(DB::connection());
            $datos = $servicio->consultar((int) $this->periodo, (int) $this->horario, $this->ciclo, $this->seccion);
            $contenido = $formato === 'pdf' ? $servicio->pdf($datos) : $servicio->xlsx($datos);
        } catch (QueryException $e) {
            report($e);
            $this->errorDescarga = 'No se pudo consultar el horario. Intente nuevamente o comuníquese con soporte.';

            return null;
        } catch (\RuntimeException $e) {
            $this->errorDescarga = 'No se pudo preparar la descarga. Compruebe que el horario pertenezca al periodo y tenga actividades.';

            return null;
        } catch (\Throwable $e) {
            report($e);
            $this->errorDescarga = 'No se pudo generar el archivo. Intente nuevamente o comuníquese con soporte.';

            return null;
        }

        return response()->streamDownload(fn () => print ($contenido), 'horario.'.$formato, ['Content-Type' => $formato === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    public function render(): View
    {
        try {
            $periodo = ctype_digit($this->periodo) && strlen($this->periodo) < 19 ? (int) $this->periodo : null;
            $horario = ctype_digit($this->horario) && strlen($this->horario) < 19 ? (int) $this->horario : null;
            $datos = (new ConsultaHorarios(DB::connection()))->consultar($periodo, $horario, mb_substr($this->ciclo, 0, 2), mb_substr($this->seccion, 0, 30));
            $datos['errorConsulta'] = false;
        } catch (QueryException $e) {
            report($e);
            $datos = ['errorConsulta' => true];
        }

        return view('modules.gestion-horarios.horarios-registrados', $datos);
    }
}
