<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use App\Modules\GestionHorarios\Repositories\HorariosPublicadosRepository;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\EntornoEjecucionQa;
use App\Modules\GestionHorarios\Services\ConsultaPublicadaPresentacion;
use App\Modules\GestionHorarios\Services\LectoresPublicacionQa;
use App\Modules\GestionHorarios\Services\DescargaConsultaPublicada;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MiHorarioDocenteController
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User && $usuario->hasRole('profesor', 'web'), 403);
        $f = $request->validate(['periodo'=>'nullable|integer|min:1','descargar'=>'nullable|in:pdf']);
        $publicacionQa = ConsultaLocalDemostracion::entornoAislado();
        $periodos = collect(); $periodo = null; $planos = []; $vinculado = false; $errorConsulta = false;
        try {
            if ($publicacionQa) {
                $id = LectoresPublicacionQa::docente((string)$usuario->email, (string)EntornoEjecucionQa::valor('HORARIOS_QA_DOCENTES'));
                if (ConsultaPublicacionDemoController::habilitada($request) && $id !== null
                    && DB::table('core.docente')->where('id_docente', $id)->exists()) {
                    $vinculado = true;
                    ['p'=>$p,'periodos'=>$periodos,'filas'=>$filas] = (new ConsultaPublicacionDemoController)->datos($request);
                    $periodo = $p && (!isset($f['periodo']) || (int)$p->id_periodo === (int)$f['periodo']) ? $p : null;
                    if ($periodo) $planos = ConsultaPublicadaPresentacion::cuadriculas($filas->filter(fn($s)=>(int)$s->id_docente === $id)->all(), 'docente', 'Mi horario docente');
                }
            } elseif (app()->bound(ProveedorIdentidadDocente::class)) {
                $id = app(ProveedorIdentidadDocente::class)->docenteId($usuario);
                if ($id !== null && $id > 0) {
                    $vinculado = true;
                    $repo = new HorariosPublicadosRepository(DB::connection());
                    // Solo periodos publicados con sesiones asignadas al docente acreditado.
                    $periodos = $repo->periodos()->filter(fn($p)=>$repo->sesiones((int)$p->id_periodo,'docente',$id,null)->isNotEmpty())->values();
                    $periodo = isset($f['periodo']) ? $periodos->firstWhere('id_periodo',(int)$f['periodo']) : $periodos->first();
                    if ($periodo) $planos = ConsultaPublicadaPresentacion::cuadriculas($repo->sesiones((int)$periodo->id_periodo,'docente',$id,null)->all(),'docente','Mi horario docente');
                }
            }
        } catch (Throwable $e) {
            report($e);
            $periodos = collect(); $periodo = null; $planos = []; $errorConsulta = true;
        }
        if (($f['descargar']??null)==='pdf') {
            abort_if($errorConsulta,503,'No se pudo consultar su horario.');
            abort_unless($vinculado,403);
            abort_unless($periodo,422,'Seleccione un periodo publicado.');
            return DescargaConsultaPublicada::pdf($planos,$periodo);
        }
        return response()->view('modules.gestion-horarios.mi-horario-docente',compact('periodos','periodo','planos','vinculado','publicacionQa','errorConsulta'),$errorConsulta?503:200)->header('Cache-Control','private, no-store');
    }
}
