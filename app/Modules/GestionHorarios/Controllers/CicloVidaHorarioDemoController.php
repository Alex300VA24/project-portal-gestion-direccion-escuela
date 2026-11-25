<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Modules\GestionHorarios\Services\CicloVidaHorarioDemo as Ciclo;
use App\Modules\GestionHorarios\Services\ConsultaLocalDemostracion;
use App\Modules\GestionHorarios\Services\HorarioUnicoDemo;
use App\Modules\GestionHorarios\Services\EntornoEjecucionQa;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/** Acciones del director QA. Las rutas institucionales existentes no se modifican. */
final class CicloVidaHorarioDemoController
{
    public function calendario(Request $r): RedirectResponse
    {
        abort_unless(ConsultaLocalDemostracion::permitida($r->user()) && Ciclo::disponible(),403);
        $v=$r->validate(['version'=>'required|integer|min:0','dias'=>'required|array|min:1|max:5','dias.*'=>'required|distinct|in:LUNES,MARTES,MIERCOLES,JUEVES,VIERNES','sabados'=>'nullable|array|max:10','sabados.*'=>'integer|distinct|min:1|max:10']);
        try { Ciclo::guardarCalendario($r->user(),(int)$v['version'],$v['dias'],$v['sabados']??[]); }
        catch (\RuntimeException $e) { return back()->withErrors(['calendario'=>'No se pudo guardar. Compruebe que no haya una generación activa.']); }
        return back()->with('gh_aviso','Calendario guardado. Revise y reorganice el horario antes de aprobar otra versión.');
    }

    public function accion(Request $r): RedirectResponse
    {
        abort_unless(ConsultaLocalDemostracion::permitida($r->user()) && Ciclo::disponible(),403);
        $v=$r->validate(['accion'=>'required|in:aprobar,publicar,retirar','version'=>'required|integer|min:1','motivo'=>'required|string|max:300']);
        $id=HorarioUnicoDemo::id(); abort_unless($id,404);
        try {
            if ($v['accion']==='retirar') {
                DB::transaction(function() use($id,$v) {
                    Ciclo::sinCalculo();
                    DB::statement('LOCK TABLE '.HorarioUnicoDemo::REVISIONES.' IN SHARE ROW EXCLUSIVE MODE');
                    Ciclo::registrar($id,(int)$v['version'],'retirar',trim($v['motivo']));
                },3);
            } else {
                // La validación y la escritura se realizan juntas dentro del conector, con
                // comprobación de huella y versión; la vista jamás puede certificar por sí sola.
                $script = EntornoEjecucionQa::conector();
                if (DB::table('gestion_horarios.qa_generacion')->whereIn('estado',['PENDIENTE','EJECUTANDO'])->exists()) throw new \RuntimeException('Hay un cálculo activo.');
                $entorno = EntornoEjecucionQa::variablesProceso();
                $p=new Process([PHP_BINARY,$script,$v['accion'],'150',(string)$v['version'],trim($v['motivo'])],base_path(),$entorno);
                $p->setTimeout(90); $p->run();
                $resultado=json_decode(trim($p->getOutput()),true);
                if (!$p->isSuccessful() || ($resultado['estado']??'')!=='VALIDADO' || (int)($resultado['version']??0)!==(int)$v['version'] || !in_array($resultado['evento']??null, [$v['accion']==='aprobar'?'APROBADO':'PUBLICADO','SIN_CAMBIOS'], true)) {
                    // El conector puede devolver un diagnóstico JSON seguido de texto informativo.
                    $primera = strtok(trim($p->getOutput()), "\n");
                    $detalle = is_string($primera) ? json_decode($primera, true) : null;
                    $causa = self::diagnosticar(is_array($detalle) ? $detalle : [], $p->getErrorOutput().' '.$p->getOutput());
                    // Solo en el entorno QA ya autorizado: registrar un resumen del fallo,
                    // no el comando, el motivo del usuario ni la instantánea completa.
                    $resumen = self::resumenProceso($p->getErrorOutput(), $p->getOutput(), $entorno);
                    Log::warning('GH_QA_VALIDACION_FALLO', [
                        'accion'=>$v['accion'], 'version'=>(int)$v['version'],
                        'codigo_salida'=>$p->getExitCode(), 'diagnostico'=>$causa,
                        'detalle'=>$resumen,
                    ]);
                    // No traducir por segunda vez un mensaje ya destinado al usuario.
                    return back()->withErrors(['publicacion'=>$causa])->with('gh_accion_fallida',$v['accion']);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $mensaje = self::diagnosticar([], $e->getMessage());
            return back()->withErrors(['publicacion'=>$mensaje])->with('gh_accion_fallida',$v['accion']);
        }
        return back()->with('gh_aviso',match($v['accion']){'aprobar'=>'Versión aprobada en demostración.','publicar'=>'Publicación de demostración vigente.','retirar'=>'Publicación retirada.'});
    }

    private static function resumenProceso(string $stderr, string $stdout, array $entorno): string
    {
        // Buscar en TODA la salida. Los títulos de Symfony (Connection.php)
        // y los avisos DOSKEY pueden preceder al SQLSTATE real.
        $salida = $stderr."\n".$stdout;
        $resumen = [];
        if (preg_match_all('/SQLSTATE\[([A-Z0-9]{5})\]/i', $salida, $m)) {
            foreach (array_unique($m[1]) as $codigo) $resumen[] = 'SQLSTATE '.strtoupper($codigo);
        } elseif (preg_match('/Connection\.php|PDOException|QueryException/i', $salida)) {
            $resumen[] = 'Error de acceso a datos (sin SQLSTATE en la salida)';
        }
        if (preg_match('/DOSKEY/i', $salida)) $resumen[] = 'Aviso de entorno Windows: DOSKEY';
        // No guardar salida libre: puede incluir credenciales, SQL o datos personales.
        return $resumen ? implode(' | ', $resumen) : 'Sin diagnóstico de infraestructura reconocido; revisar código de salida.';
    }

    private static function diagnosticar(array $datos, string $texto): string
    {
        // Solo mensajes conocidos; nunca exponer trazas, rutas o credenciales.
        $mensajes = [
            'Hay un cálculo activo' => 'Hay un cálculo en espera o en ejecución. Espere a que termine antes de continuar.',
            'Conector local no configurado' => 'El servicio de validación no está configurado. Solicite revisar el entorno de generación.',
            'Se requiere el entorno aislado' => 'El servicio de validación no reconoce el entorno de demostración. Revise la configuración del servidor.',
            'Conexión no autorizada' => 'El servicio de validación no reconoce el entorno de demostración. Revise la configuración del servidor.',
            'Falta HORARIOS_DEMO_ARCHIVO' => 'Faltan los datos de referencia del servicio de validación. Revise la configuración del servidor.',
            'Los recursos o la programación cambiaron' => 'Los datos académicos cambiaron desde la última revisión. Reorganice o genere el horario antes de aprobarlo.',
            'Los recursos cambiaron durante la revisión' => 'Los datos académicos cambiaron durante la validación. Recargue la página y vuelva a intentarlo.',
            'La versión cambió' => 'La versión del horario cambió. Recargue la página y seleccione la versión vigente.',
            'Versión o motivo inválido' => 'La versión o el motivo no son válidos. Recargue la página y complete el motivo.',
            'Se requiere un horario validado' => 'La versión seleccionada no cuenta con una validación completa. Genere o reorganice el horario.',
            'Apruebe primero esta versión' => 'Debe aprobar esta versión antes de publicarla.',
            'La publicación cambió o ya fue retirada' => 'La publicación cambió o ya fue retirada. Recargue la página.',
            'Docente sin nombre conciliado' => 'No se pudo identificar el nombre completo de un docente. Revise la asignación docente.',
        ];
        if (preg_match('/Variable QA inválida: (APP_ENV|DB_CONNECTION|DB_DATABASE|HORARIOS_DEMO_PLAN|HORARIOS_QA_CONSULTAS)\b/u',$texto,$coincidencia)) {
            return 'La variable '.$coincidencia[1].' no está configurada para la demostración. Reinicie el servidor local con el entorno QA.';
        }
        foreach ($mensajes as $marca=>$mensaje) {
            if (str_contains($texto,$marca) || $texto===$mensaje) return $mensaje;
        }
        if (preg_match('/SQLSTATE\[([A-Z0-9]{5})\]/i', $texto, $m)) {
            $codigo = strtoupper($m[1]);
            return match (true) {
                str_starts_with($codigo, '08'), str_starts_with($codigo, '28') => 'El servicio no pudo conectar con la base de datos de demostración. Código '.$codigo.'.',
                default => 'El servicio encontró un error de base de datos. Código '.$codigo.'. Revise el registro antes de reintentar.',
            };
        }
        $rechazo = 'El horario no superó la validación de las reglas académicas vigentes. Revise los datos y reorganice el horario.';
        $preparacion = 'Hay datos académicos pendientes o bloqueados. Revise la preparación académica antes de continuar.';
        $timeout = 'El servicio de validación excedió el tiempo de espera. Revise el historial antes de volver a intentarlo.';
        if (($datos['estado']??'')==='MOVIMIENTO_RECHAZADO' || $texto===$rechazo) return $rechazo;
        if (!empty($datos['bloqueadas']) || $texto===$preparacion) return $preparacion;
        if (str_contains(strtolower($texto),'exceeded the timeout') || $texto===$timeout) return $timeout;
        return 'No se pudo verificar el resultado de la operación. Revise el historial antes de volver a intentarlo. Si persiste, consulte el registro de errores del servidor.';
    }

}
