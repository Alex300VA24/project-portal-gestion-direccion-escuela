<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceRechazado;
use App\Modules\SeguimientoEgresado\Services\Empresas\MotivoEnlace;
use App\Modules\SeguimientoEgresado\Services\Empresas\RespuestaEmpresaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Formulario público de la empresa: entra solo con su enlace y no ve el menú interno ni el padrón. */
class EncuestaEmpresaController extends Controller
{
    public function mostrar(string $token, EnlaceEmpresa $enlaces): Response
    {
        ['motivo' => $motivo, 'invitacion' => $invitacion] = $enlaces->comprobar($token);

        if ($motivo !== MotivoEnlace::Vigente) {
            return $this->rechazo($motivo);
        }

        return $this->privada(response()->view('modules.seguimiento-egresado.empresas.encuesta', [
            'token' => $token,
            'invitacion' => $invitacion,
            'habilidades' => DB::table('seguimiento_egresado.cat_habilidad as h')
                ->join('seguimiento_egresado.cat_categoria_habilidad as c', 'c.id', '=', 'h.id_categoria')
                ->where('h.activo', true)
                ->orderBy('c.id')->orderBy('h.nombre')
                ->get(['h.codigo', 'h.nombre', 'c.nombre as categoria']),
            'sectores' => DB::table('seguimiento_egresado.cat_sector_empresa')->where('activo', true)->orderBy('nombre')->get(['codigo', 'nombre']),
            'niveles' => DB::table('seguimiento_egresado.cat_nivel_satisfaccion')->where('activo', true)->orderBy('id')->get(['codigo', 'nombre']),
        ]));
    }

    public function responder(Request $request, string $token, EnlaceEmpresa $enlaces, RespuestaEmpresaService $respuestas): Response|RedirectResponse
    {
        ['motivo' => $motivo, 'invitacion' => $invitacion] = $enlaces->comprobar($token);

        if ($motivo !== MotivoEnlace::Vigente) {
            return $this->rechazo($motivo);
        }

        $request->merge([
            'demanda_otra' => in_array(RespuestaEmpresaService::CODIGO_OTRA, (array) $request->input('demandas', []), true) ? '1' : '0',
            'brecha_otra' => in_array(RespuestaEmpresaService::CODIGO_OTRA, (array) $request->input('brechas', []), true) ? '1' : '0',
        ]);

        $datos = $request->validate(RespuestaEmpresaService::reglas($invitacion->contexto), RespuestaEmpresaService::mensajes());

        try {
            $respuestas->registrar($token, $datos);
        } catch (EnlaceRechazado $rechazo) {
            return $this->rechazo($rechazo->motivo);
        }

        return redirect()->route('seguimiento-egresado.encuesta-empresa.gracias');
    }

    public function gracias(): Response
    {
        return $this->privada(response()->view('modules.seguimiento-egresado.empresas.estado', [
            'tipo' => 'exito',
            'titulo' => 'Gracias por responder',
            'mensaje' => 'Tu respuesta quedó registrada. Ya puedes cerrar esta página.',
        ]));
    }

    private function rechazo(MotivoEnlace $motivo): Response
    {
        return $this->privada(response()->view('modules.seguimiento-egresado.empresas.estado', [
            'tipo' => $motivo->tipo(),
            'titulo' => $motivo->titulo(),
            'mensaje' => $motivo->mensaje(),
        ], $motivo->codigoHttp()));
    }

    /** El token viaja en la URL: no debe quedar en cachés, buscadores ni cabeceras Referer. */
    private function privada(Response $respuesta): Response
    {
        $respuesta->headers->set('Cache-Control', 'no-store, private');
        $respuesta->headers->set('Referrer-Policy', 'no-referrer');
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }
}
