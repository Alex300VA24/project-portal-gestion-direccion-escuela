<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Egresado;

use App\Modules\SeguimientoEgresado\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEgresado\Requests\ContactoRequest;
use App\Modules\SeguimientoEgresado\Requests\EncuestaSeguimientoRequest;
use App\Modules\SeguimientoEgresado\Services\Egresado\EncuestaService;
use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use App\Modules\SeguimientoEgresado\Services\Egresado\InvitacionNoDisponible;
use App\Modules\SeguimientoEgresado\Services\Egresado\MotivoInvitacion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Encuesta de seguimiento profesional: responde únicamente la invitación con la que el egresado entró. */
final class EncuestaController extends EgresadoController
{
    public function __construct(private readonly HistorialService $historial) {}

    public function mostrar(Request $request): View|RedirectResponse
    {
        $identidad = $this->identidad($request);
        $invitacion = $this->invitacion($identidad->egresadoId, $this->invitacionElegida($request, $identidad->invitacionId));

        if ($invitacion === null || $invitacion->proposito !== 'SEGUIMIENTO') {
            return redirect()->route('seguimiento-egresado.egresado.inicio')->with('info', 'Elige la encuesta de seguimiento que quieres responder desde tu expediente.');
        }

        if ($invitacion->estado === 'COMPLETADA') {
            return redirect()->route('seguimiento-egresado.egresado.historial')->with('info', 'Ya respondiste esta encuesta. Tu respuesta se conserva en el historial.');
        }

        if (($motivo = $this->motivoNoDisponible($invitacion)) !== null) {
            return redirect()->route('seguimiento-egresado.egresado.inicio')->with('error', $this->mensaje($motivo));
        }

        return view('modules.seguimiento-egresado.egresado.encuesta', [
            'invitacion' => $invitacion,
            'contacto' => collect($this->historial->contactos($identidad->egresadoId))->firstWhere('vigente_hasta', null),
            'cat' => $this->catalogos(),
        ]);
    }

    public function responder(Request $request, EncuestaService $encuestas): RedirectResponse
    {
        $identidad = $this->identidad($request);
        abort_unless((new ExpedientePolicy)->responderEncuesta($identidad, $identidad->egresadoId), 403);
        $invitacionId = $this->invitacionElegida($request, $identidad->invitacionId);

        $datos = $request->all();
        $modelo = new EncuestaSeguimientoRequest;
        $reglasContacto = $mensajesContacto = $atributosContacto = [];
        foreach (ContactoRequest::reglas() as $campo => $reglas) {
            $reglasContacto["contacto.$campo"] = $reglas;
        }
        foreach ((new ContactoRequest)->attributes() as $campo => $nombre) {
            $atributosContacto["contacto.$campo"] = $nombre;
        }

        $validado = Validator::make(
            $datos,
            EncuestaSeguimientoRequest::reglasPara($datos) + $reglasContacto,
            $modelo->messages() + (new ContactoRequest)->messages(),
            $modelo->attributes() + $atributosContacto,
        )->validate();

        try {
            $encuestas->registrar($identidad->egresadoId, (int) $invitacionId, Arr::except($validado, 'contacto'), $validado['contacto']);
        } catch (InvitacionNoDisponible $e) {
            return redirect()->route('seguimiento-egresado.egresado.inicio')->with('error', $this->mensaje($e->motivo));
        }

        return redirect()->route('seguimiento-egresado.egresado.historial')->with('exito', 'Gracias por responder. Tu respuesta quedó guardada en tu historial.');
    }

    /**
     * Invitación que se responde: la elegida en el expediente (?invitacion=) o, si no se indica, la del enlace con el
     * que entró. Siempre se vuelve a comprobar que sea del propio egresado (invitacion() y EncuestaService lo exigen).
     */
    private function invitacionElegida(Request $request, ?int $invitacionDelEnlace): ?int
    {
        $elegida = filter_var($request->input('invitacion'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $elegida === false ? $invitacionDelEnlace : $elegida;
    }

    private function invitacion(int $egresadoId, ?int $invitacionId): ?object
    {
        return DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->leftJoin('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->where('i.id', $invitacionId)->where('i.id_egresado', $egresadoId)
            ->first(['i.id', 'i.proposito', 'i.estado', 'i.expira_en', 'c.nombre as campania', 'c.periodo', 'c.fecha_fin', 'c.estado as estado_campania']);
    }

    private function motivoNoDisponible(object $invitacion): ?MotivoInvitacion
    {
        return match (true) {
            $invitacion->estado === 'ANULADA' => MotivoInvitacion::Anulada,
            $invitacion->estado === 'EXPIRADA' => MotivoInvitacion::Expirada,
            CarbonImmutable::parse($invitacion->expira_en)->lessThanOrEqualTo(CarbonImmutable::now()) => MotivoInvitacion::Vencida,
            $invitacion->estado_campania !== 'ABIERTA' => MotivoInvitacion::CampaniaCerrada,
            default => null,
        };
    }

    private function mensaje(MotivoInvitacion $motivo): string
    {
        return match ($motivo) {
            MotivoInvitacion::YaRespondida => 'Ya respondiste esta encuesta. Tu respuesta se conserva en el historial.',
            MotivoInvitacion::Anulada => 'La Escuela anuló esta invitación.',
            MotivoInvitacion::Expirada, MotivoInvitacion::Vencida => 'El plazo para responder esta invitación venció.',
            MotivoInvitacion::CampaniaCerrada => 'La campaña de seguimiento ya no está abierta.',
            MotivoInvitacion::NoEncontrada => 'No encontramos esta invitación.',
        };
    }

    /** Opciones activas de cada catálogo: código => nombre. */
    private function catalogos(): array
    {
        $simple = fn (string $tabla, string $orden = 'nombre') => DB::table('seguimiento_egresado.'.$tabla)->where('activo', true)->orderBy($orden)->pluck('nombre', 'codigo')->all();

        $habilidades = DB::table('seguimiento_egresado.cat_habilidad as h')->join('seguimiento_egresado.cat_categoria_habilidad as c', 'c.id', '=', 'h.id_categoria')
            ->where('h.activo', true)
            ->orderByRaw("CASE WHEN h.codigo = 'OTRA' THEN 1 ELSE 0 END")->orderBy('c.nombre')->orderBy('h.nombre')
            ->pluck('h.nombre', 'h.codigo')->all();

        return [
            'situaciones' => $simple('cat_situacion_laboral', 'id'),
            'paises' => $simple('cat_pais'),
            'areas' => $simple('cat_area_profesional'),
            'vinculos' => $simple('cat_modalidad_laboral'),
            'modalidades' => $simple('cat_modalidad_trabajo'),
            'sectores' => $simple('cat_sector_laboral'),
            'satisfaccion' => $simple('cat_nivel_satisfaccion', 'id'),
            'habilidades' => $habilidades,
            'tipos_formacion' => $simple('cat_tipo_formacion'),
            'estados_formacion' => $simple('cat_estado_formacion', 'id'),
            'niveles_ingles' => ['NINGUNO' => 'Ninguno', 'BASICO' => 'Básico', 'INTERMEDIO' => 'Intermedio', 'AVANZADO' => 'Avanzado'],
            'regiones' => ['LA_LIBERTAD' => 'La Libertad', 'OTRA_REGION' => 'Otra región del Perú'],
        ];
    }
}
