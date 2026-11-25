<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\ExportacionHorarioTrabajoDemo;
use App\Modules\GestionHorarios\Services\ExportacionHorarios;
use App\Modules\GestionHorarios\Services\HorariosVersionadosDemo;
use App\Modules\GestionHorarios\Services\HorarioUnicoDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RevisionHorarioTrabajoDemoController
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        abort_unless(ConsultaLocalDemostracion::permitida($usuario), 403);
        $servicio = new HorariosVersionadosDemo;
        $actual = $servicio->disponible() ? $servicio->leer($usuario, HorarioUnicoDemo::id()) : null;
        $versiones = $actual ? DB::table(HorariosVersionadosDemo::TABLA)->where('propuesta', $actual['id'])->orderByDesc('version')->get(['version','nombre','creado_en','actor_qa']) : collect();
        // Derive the only authorised context from the guarded QA database.
        $plan = (string)DB::table('core.plan_estudios')->where('codigo','QA-HORARIOS')->value('id_plan');
        $periodo = (string)DB::table('core.periodo_academico')->where('codigo','2026-II')->value('id_periodo');
        $cicloDisponible=\App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo::disponible();
        $eventos=$cicloDisponible && $actual ? \App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo::eventos($actual['id']) : collect();
        $aprobada=$eventos->firstWhere('evento','APROBADO');
        $publicada=$eventos->first(fn($e)=>in_array($e->evento,['PUBLICADO','RETIRADO'],true));
        if ($publicada && $publicada->evento!=='PUBLICADO') $publicada=null;
        return view('modules.gestion-horarios.revision-trabajo-qa', compact('actual','versiones','plan','periodo','cicloDisponible','eventos','aprobada','publicada'));
    }

    public function descargar(Request $request, int $version, string $formato): StreamedResponse
    {
        abort_unless(ConsultaLocalDemostracion::permitida($request->user()), 403);
        abort_unless($version > 0 && in_array($formato, ['pdf','xlsx'], true), 404);
        if ($formato === 'xlsx') {
            abort_unless(class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) && extension_loaded('gd') && extension_loaded('zip'), 503, 'La descarga Excel no está disponible. Comuníquese con soporte.');
        } else {
            abort_unless(class_exists(\Dompdf\Dompdf::class), 503, 'La descarga PDF no está disponible. Comuníquese con soporte.');
        }
        $filtros = $request->validate(['vista'=>'nullable|in:ciclo,docente,ambiente','ciclo'=>'nullable|integer|min:1|max:12','seccion'=>'nullable|string|max:50','curso'=>'nullable|string|max:80','docente'=>'nullable|string|max:100','ambiente'=>'nullable|string|max:100']);
        $datos = (new ExportacionHorarioTrabajoDemo)->consultar($request->user(), $version, $filtros);
        abort_if($datos['sesiones']->isEmpty(), 422, 'El horario no tiene sesiones programadas.');
        $servicio = new ExportacionHorarios(DB::connection());
        try { $contenido = $formato === 'pdf' ? $servicio->pdf($datos) : $servicio->xlsx($datos); }
        catch (\Throwable $e) { report($e); abort(422, 'No se pudo preparar el archivo. Revise el horario o comuníquese con soporte.'); }
        return response()->streamDownload(fn () => print($contenido), 'horario-borrador-2026-II-V'.$version.'.'.$formato, ['Content-Type'=>$formato === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control'=>'private, no-store']);
    }
}
