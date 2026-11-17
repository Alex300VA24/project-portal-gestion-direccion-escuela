<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceNoGenerable;
use App\Modules\SeguimientoEgresado\Services\Empresas\ResultadosEmpresas;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/** Vista interna de Secretaría y Director: genera enlaces y consulta resultados agregados. */
class EmpresasController extends Controller
{
    public function index(Request $request, ResultadosEmpresas $resultados): Response
    {
        $campaniaId = filter_var($request->query('campania'), FILTER_VALIDATE_INT) ?: null;
        $contexto = in_array($request->query('contexto'), ['MERCADO', 'DESEMPENO'], true) ? $request->query('contexto') : null;

        $datos = [
            'campaniaId' => $campaniaId,
            'contexto' => $contexto,
            'pagina' => max(1, (int) $request->query('pagina', 1)),
            'puedeGenerar' => $this->puedeGenerar($request),
            'error' => false,
        ];

        try {
            $datos += [
                'resumen' => $resultados->resumen($campaniaId, $contexto),
                'campanias' => $resultados->campaniasDeEmpresas(),
                'evaluables' => $resultados->egresadosEvaluables(),
            ];
        } catch (Throwable $e) {
            report($e);
            $datos = ['resumen' => null, 'campanias' => collect(), 'evaluables' => collect(), 'error' => true] + $datos;
        }

        // La página puede llevar un enlace recién generado: no debe quedar en la caché del navegador.
        return response()->view('modules.seguimiento-egresado.paginas.empresas', $datos)->header('Cache-Control', 'no-store, private');
    }

    public function generarEnlace(Request $request, EnlaceEmpresa $enlaces): RedirectResponse
    {
        $datos = $request->validate([
            'id_campania' => ['required', 'integer'],
            'contexto' => ['required', Rule::in(['MERCADO', 'DESEMPENO'])],
            'id_egresado' => ['nullable', 'integer', 'required_if:contexto,DESEMPENO'],
            'organizacion' => ['nullable', 'string', 'max:180'],
            'contacto_empresa' => ['nullable', 'string', 'max:180'],
            'dias_validez' => ['required', 'integer', 'min:1', 'max:'.EnlaceEmpresa::DIAS_MAXIMOS],
        ], [
            'id_campania.required' => 'Selecciona una campaña.',
            'contexto.required' => 'Selecciona el tipo de encuesta.',
            'contexto.in' => 'Selecciona un tipo de encuesta válido.',
            'id_egresado.required_if' => 'Selecciona al egresado que se evaluará.',
            'organizacion.max' => 'El nombre de la organización admite hasta 180 caracteres.',
            'contacto_empresa.max' => 'El contacto admite hasta 180 caracteres.',
            'dias_validez.required' => 'Indica los días de validez.',
            'dias_validez.min' => 'El enlace debe valer al menos 1 día.',
            'dias_validez.max' => 'El enlace puede valer hasta '.EnlaceEmpresa::DIAS_MAXIMOS.' días.',
        ]);

        // Una encuesta de mercado nunca queda vinculada a un egresado.
        if ($datos['contexto'] === 'MERCADO') {
            $datos['id_egresado'] = null;
        }

        try {
            $enlace = $enlaces->generar($datos);
        } catch (EnlaceNoGenerable $e) {
            return back()->withInput()->withErrors([$e->campo => $e->getMessage()]);
        }

        return $this->conEnlaceGenerado($enlace);
    }

    public function renovarEnlace(Request $request, int $id, EnlaceEmpresa $enlaces): RedirectResponse
    {
        try {
            $enlace = $enlaces->renovar($id, $request->boolean('confirmar_reemplazo'));
            $this->auditarRenovacion($request, $id);
        } catch (EnlaceNoGenerable $e) {
            return redirect()->route('seguimiento-egresado.empresas')->with('aviso_enlace', $e->getMessage());
        }

        return $this->conEnlaceGenerado($enlace);
    }

    public function marcarEnviado(int $id, EnlaceEmpresa $enlaces): Response
    {
        $enlaces->marcarEnviado($id);

        return response()->noContent();
    }

    /** Registra quién reemplazó el enlace cuando el módulo de accesos identifica a la persona. */
    private function auditarRenovacion(Request $request, int $id): void
    {
        $usuario = class_exists(SesionAcceso::class) ? SesionAcceso::desde($request)->actual()?->usuarioId : null;

        if ($usuario !== null) {
            DB::table('seguimiento_egresado.auditoria')->insert([
                'id_usuario' => $usuario,
                'tabla_afectada' => 'invitacion_empresa',
                'id_registro' => $id,
                'accion' => 'REEMPLAZAR_ENLACE',
                'detalle' => json_encode(['confirmado' => $request->boolean('confirmar_reemplazo')]),
            ]);
        }
    }

    /** El token no se guarda: esta es la única vez que se puede copiar el enlace. */
    private function conEnlaceGenerado(array $enlace): RedirectResponse
    {
        return redirect()->route('seguimiento-egresado.empresas')->with('enlace_generado', [
            'id' => $enlace['id'],
            'url' => route('seguimiento-egresado.encuesta-empresa', ['token' => $enlace['token']]),
            'expira_en' => $enlace['expira_en']->setTimezone(FiltroIndicadores::ZONA)->format('d/m/Y H:i'),
        ]);
    }

    /** Sin el módulo de accesos integrado se muestra el formulario; con él, solo a quien administra campañas. */
    private function puedeGenerar(Request $request): bool
    {
        if (! class_exists(SesionAcceso::class)) {
            return true;
        }

        return (bool) SesionAcceso::desde($request)->actual()?->puede('campanias.administrar');
    }
}
