<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Services\ConvocatoriaService;
use App\Modules\GestionPracticas\Support\Acceso;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\View\View;

final class PanelController extends Controller
{
    public function __invoke(ConvocatoriaService $convocatorias): View
    {
        $actor = Actor::actual();
        $user = $actor->user;
        $resumen = [];

        if ($user->can(Acceso::EMPRESAS)) {
            $resumen['Convocatorias por revisar'] = Convocatoria::where('estado', Convocatoria::EN_REVISION)->count();
            $resumen['Prácticas por validar'] = Practica::where('estado', Practica::EN_REVISION)->count();
            $resumen['Prácticas en curso'] = Practica::where('estado', Practica::EN_CURSO)->count();
        }

        if ($user->can(Acceso::CONVOCATORIAS_GESTIONAR) && $actor->empresaId !== null) {
            $propias = Convocatoria::where('id_empresa', $actor->empresaId);
            $resumen['Mis convocatorias'] = (clone $propias)->count();
            $resumen['Publicadas'] = (clone $propias)->where('estado', Convocatoria::PUBLICADA)->count();
            $resumen['Con observaciones'] = (clone $propias)->where('estado', Convocatoria::OBSERVADA)->count();
        }

        if ($user->can(Acceso::POSTULAR) && $actor->estudianteId !== null) {
            $resumen['Convocatorias vigentes'] = $convocatorias->vigentes()->count();
            $resumen['Mis postulaciones'] = Postulacion::where('id_estudiante', $actor->estudianteId)->count();
            $resumen['Mis prácticas'] = Practica::where('id_estudiante', $actor->estudianteId)->count();
        }

        if ($user->can(Acceso::PRACTICAS_SUPERVISAR) && $actor->docenteId !== null) {
            $asignadas = Practica::where('id_docente_supervisor', $actor->docenteId);
            $resumen['Prácticas asignadas'] = (clone $asignadas)->count();
            $resumen['En curso'] = (clone $asignadas)->where('estado', Practica::EN_CURSO)->count();
        }

        return view('modules.gestion-practicas.panel', [
            'actor' => $actor,
            'resumen' => $resumen,
            'vinculada' => $actor->usuarioId !== null,
        ]);
    }
}
