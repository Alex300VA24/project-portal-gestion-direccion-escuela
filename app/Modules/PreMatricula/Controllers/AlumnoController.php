<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\AgregarCursoRequest;
use App\Modules\PreMatricula\Requests\AlumnoRequest;
use App\Modules\PreMatricula\Requests\CambiarTurnoRequest;
use App\Modules\PreMatricula\Services\IdentidadAcademica;
use App\Modules\PreMatricula\Services\PrematriculaAlumno;
use App\Modules\PreMatricula\Services\PrematriculaException;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AlumnoController extends Controller
{
    use RespondeAcciones;

    public function __construct(
        private readonly PrematriculaAlumno $prematricula,
        private readonly IdentidadAcademica $identidad,
    ) {}

    public function index(AlumnoRequest $request): View
    {
        $estudiante = $this->identidad->estudiante($request->user());
        if ($estudiante === null) {
            return view('modules.pre-matricula.alumno.sin-vinculo');
        }

        try {
            $datos = $this->prematricula->resumen($estudiante);
        } catch (PrematriculaException $error) {
            $datos = ['estudiante' => $estudiante, 'periodo' => null, 'aviso' => $error->getMessage()];
        }

        return view('modules.pre-matricula.alumno.index', $datos);
    }

    public function agregar(AgregarCursoRequest $request): RedirectResponse
    {
        return $this->accion(fn () => $this->prematricula->agregar(
            $this->estudiante($request),
            (int) $request->validated('id_curso_plan'),
            $request->validated('preferencia_turno') ?? 'SIN_PREFERENCIA',
        ), 'Curso agregado a tu selección.');
    }

    /** La preferencia de turno existe como en master, aunque su pantalla está oculta allí. */
    public function cambiarTurno(CambiarTurnoRequest $request, int $detalle): RedirectResponse
    {
        return $this->accion(fn () => $this->prematricula->cambiarTurno($this->estudiante($request), $detalle, $request->validated('preferencia_turno')), 'Preferencia de turno actualizada.');
    }

    public function retirar(AlumnoRequest $request, int $detalle): RedirectResponse
    {
        return $this->accion(fn () => $this->prematricula->retirar($this->estudiante($request), $detalle), 'Curso retirado de tu selección.');
    }

    public function registrar(AlumnoRequest $request): RedirectResponse
    {
        return $this->accion(fn () => $this->prematricula->registrar($this->estudiante($request)), 'Prematrícula registrada. Ya puedes descargar tu constancia.');
    }

    public function corregir(AlumnoRequest $request): RedirectResponse
    {
        return $this->accion(fn () => $this->prematricula->iniciarCorreccion($this->estudiante($request)), 'Corrección iniciada. Tu constancia anterior sigue vigente hasta que vuelvas a registrar.');
    }

    public function historial(AlumnoRequest $request): View
    {
        $estudiante = $this->identidad->estudiante($request->user());
        if ($estudiante === null) {
            return view('modules.pre-matricula.alumno.sin-vinculo');
        }

        return view('modules.pre-matricula.alumno.historial', [
            'estudiante' => $estudiante,
            'solicitudes' => $this->prematricula->historial($estudiante),
        ]);
    }

    private function estudiante(AlumnoRequest $request): Estudiante
    {
        return $this->identidad->estudiante($request->user())
            ?? throw new PrematriculaException('Tu cuenta no está vinculada a un estudiante.');
    }
}
