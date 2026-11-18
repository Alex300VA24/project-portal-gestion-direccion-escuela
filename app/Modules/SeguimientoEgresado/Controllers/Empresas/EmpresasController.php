<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\CatalogosDeCampania;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceNoGenerable;
use App\Modules\SeguimientoEgresado\Services\Empresas\ResultadosEmpresas;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Encuesta empresarial. Se elige una campaña de empleadores; la tabla muestra a los egresados que autorizaron contactar a su
 * empresa (con la empresa y el contacto que ellos mismos dejaron) y desde ahí se generan los enlaces, uno o todos los que faltan.
 * El sistema no envía nada a la empresa: la Secretaría copia el enlace y lo entrega. Cada enlace se muestra una sola vez.
 */
class EmpresasController extends Controller
{
    private const MAXIMO_MERCADO = 50;

    public function index(Request $request, ResultadosEmpresas $resultados, CatalogosDeCampania $catalogos): Response
    {
        $puedeGenerar = $this->puedeGenerar($request);

        try {
            $campanias = $resultados->campaniasDeEmpresas();
            $pedida = filter_var($request->query('campania'), FILTER_VALIDATE_INT) ?: null;
            $campania = $campanias->firstWhere('id', $pedida)
                ?? $campanias->firstWhere('estado', 'ABIERTA')
                ?? $campanias->first();
            $campaniaId = $campania ? (int) $campania->id : null;

            $datos = [
                'campanias' => $campanias,
                'campania' => $campania,
                'campaniaId' => $campaniaId,
                'resumen' => $resultados->resumen($campaniaId),
                'evaluables' => $campaniaId ? $resultados->evaluablesDeCampania($campaniaId) : collect(),
                'mercado' => $campaniaId ? $resultados->enlacesDeMercado($campaniaId) : collect(),
                'promociones' => $catalogos->promociones(),
                'periodos' => $catalogos->periodos(),
                'error' => false,
            ];
        } catch (Throwable $e) {
            report($e);
            $datos = ['campanias' => collect(), 'campania' => null, 'campaniaId' => null, 'resumen' => null, 'evaluables' => collect(), 'mercado' => collect(), 'promociones' => collect(), 'periodos' => collect(), 'error' => true];
        }

        // La página puede llevar enlaces recién generados: no deben quedar en la caché del navegador.
        return response()->view('modules.seguimiento-egresado.paginas.empresas', $datos + ['puedeGenerar' => $puedeGenerar])->header('Cache-Control', 'no-store, private');
    }

    /** Un enlace: la evaluación de desempeño de un egresado (la empresa y el contacto salen de su respuesta) o uno de mercado. */
    public function generarEnlace(Request $request, EnlaceEmpresa $enlaces): RedirectResponse
    {
        $datos = $request->validate([
            'id_campania' => ['required', 'integer'],
            'contexto' => ['required', Rule::in(['MERCADO', 'DESEMPENO'])],
            'id_egresado' => ['nullable', 'integer', 'required_if:contexto,DESEMPENO'],
            'organizacion' => ['nullable', 'string', 'max:180'],
            'contacto_empresa' => ['nullable', 'string', 'max:180'],
            'dias_validez' => ['nullable', 'integer', 'min:1', 'max:'.EnlaceEmpresa::DIAS_MAXIMOS],
        ], [
            'id_campania.required' => 'Elige una campaña.',
            'contexto.required' => 'Elige el tipo de encuesta.',
            'contexto.in' => 'Elige un tipo de encuesta válido.',
            'id_egresado.required_if' => 'Elige al egresado que se evaluará.',
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

        return $this->conEnlaces((int) $datos['id_campania'], [$this->describir($enlace)], 'Se generó el enlace.');
    }

    /** Varios a la vez: los enlaces de todos los egresados que faltan, o una cantidad de enlaces de mercado. */
    public function generarLote(Request $request, EnlaceEmpresa $enlaces, ResultadosEmpresas $resultados): RedirectResponse
    {
        $datos = $request->validate([
            'id_campania' => ['required', 'integer'],
            'contexto' => ['required', Rule::in(['MERCADO', 'DESEMPENO'])],
            'cantidad' => ['required_if:contexto,MERCADO', 'nullable', 'integer', 'min:1', 'max:'.self::MAXIMO_MERCADO],
        ], [
            'id_campania.required' => 'Elige una campaña.',
            'cantidad.required_if' => 'Indica cuántos enlaces necesitas.',
            'cantidad.min' => 'Indica al menos 1 enlace.',
            'cantidad.max' => 'Puedes generar hasta '.self::MAXIMO_MERCADO.' enlaces a la vez.',
        ]);
        $campaniaId = (int) $datos['id_campania'];
        $generados = [];
        $fallos = [];

        try {
            if ($datos['contexto'] === 'MERCADO') {
                for ($i = 0; $i < (int) $datos['cantidad']; $i++) {
                    $generados[] = $this->describir($enlaces->generar(['id_campania' => $campaniaId, 'contexto' => 'MERCADO']));
                }
            } else {
                foreach ($resultados->evaluablesDeCampania($campaniaId) as $fila) {
                    if (! in_array($fila->estado, ['sin_enlace', 'vencida'], true)) {
                        continue;
                    }
                    try {
                        // Quien ya tenía un enlace vencido recibe uno nuevo; quien no tenía, su primer enlace.
                        $generados[] = $this->describir($fila->invitacion
                            ? $enlaces->renovar((int) $fila->invitacion->id)
                            : $enlaces->generar(['id_campania' => $campaniaId, 'contexto' => 'DESEMPENO', 'id_egresado' => (int) $fila->id]));
                    } catch (EnlaceNoGenerable $e) {
                        $fallos[] = $e->getMessage();
                    }
                }
            }
        } catch (EnlaceNoGenerable $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        if ($generados === []) {
            return redirect()->route('seguimiento-egresado.empresas', ['campania' => $campaniaId])
                ->with($fallos === [] ? 'info' : 'error', $fallos === [] ? 'No hay egresados por invitar: todos ya tienen un enlace vigente o respondieron.' : $fallos[0]);
        }

        return $this->conEnlaces($campaniaId, $generados, count($generados) === 1 ? 'Se generó 1 enlace.' : 'Se generaron '.count($generados).' enlaces.');
    }

    /** Enlace nuevo: el anterior deja de funcionar, así que si ya se entregó se pide confirmación. */
    public function renovarEnlace(Request $request, int $id, EnlaceEmpresa $enlaces): RedirectResponse
    {
        $campaniaId = (int) DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $id)->value('id_campania');

        try {
            $enlace = $enlaces->renovar($id, $request->boolean('confirmar_reemplazo'));
            $this->auditarRenovacion($request, $id);
        } catch (EnlaceNoGenerable $e) {
            return redirect()->route('seguimiento-egresado.empresas', array_filter(['campania' => $campaniaId]))->with('error', $e->getMessage());
        }

        return $this->conEnlaces($campaniaId, [$this->describir($enlace)], 'Se generó un enlace nuevo. El anterior dejó de funcionar.');
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

    /**
     * Fila de la ventana de enlaces: a quién corresponde y el enlace. El token no se guarda: esta es la única vez que se ve.
     *
     * @param  array{id:int, token:string, expira_en:CarbonImmutable}  $enlace
     * @return array<string, mixed>
     */
    private function describir(array $enlace): array
    {
        $fila = DB::table('seguimiento_egresado.invitacion_empresa as ie')
            ->leftJoin('seguimiento_egresado.egresado as e', 'e.id', '=', 'ie.id_egresado')
            ->where('ie.id', $enlace['id'])
            ->first(['ie.organizacion', 'ie.contacto_empresa', 'e.nombres', 'e.apellido_paterno', 'e.apellido_materno']);

        return [
            'id' => $enlace['id'],
            'egresado' => $fila?->nombres ? trim($fila->apellido_paterno.' '.$fila->apellido_materno.', '.$fila->nombres) : 'Encuesta general de mercado',
            'empresa' => $fila?->organizacion,
            'contacto' => $fila?->contacto_empresa,
            'url' => route('seguimiento-egresado.encuesta-empresa', ['token' => $enlace['token']]),
            'expira_en' => $enlace['expira_en']->setTimezone(FiltroIndicadores::ZONA)->format('d/m/Y'),
        ];
    }

    /** @param  list<array<string, mixed>>  $enlaces */
    private function conEnlaces(int $campaniaId, array $enlaces, string $mensaje): RedirectResponse
    {
        return redirect()->route('seguimiento-egresado.empresas', ['campania' => $campaniaId])
            ->with('enlaces_empresa', $enlaces)
            ->with('enlaces_titulo', $mensaje);
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
