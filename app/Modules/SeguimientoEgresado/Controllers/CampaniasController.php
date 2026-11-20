<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\CatCanalEnvio;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\CatalogosDeCampania;
use App\Modules\SeguimientoEgresado\Services\EstadoDeCampanias;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\EnvioDeEnlaces;
use App\Modules\SeguimientoEgresado\Support\Esquema;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Campañas e invitaciones. Una campaña se abre y se cierra sola según sus fechas (EstadoDeCampanias). Las de egresados
 * se envían solo al correo personal que cada egresado dejó. El detalle se ve en una ventana encima de la lista.
 */
class CampaniasController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = [
            'q' => $request->query('q'),
            'cohorte' => $request->query('cohorte'),
            'estado' => $request->query('estado'),
            'periodo' => $request->query('periodo'),
        ];

        $consulta = CampaniaSeguimiento::with(['cohortes', 'invitaciones.medicion'])->orderByDesc('fecha_inicio');
        if (! empty($filtros['q'])) {
            $consulta->where(fn ($q) => $q->where('nombre', 'like', "%{$filtros['q']}%")->orWhere('descripcion', 'like', "%{$filtros['q']}%")->orWhere('periodo', 'like', "%{$filtros['q']}%"));
        }
        if (! empty($filtros['cohorte']) && $filtros['cohorte'] !== 'todas') {
            $consulta->whereHas('cohortes', fn ($q) => $q->where('cohorte.id', $filtros['cohorte']));
        }
        if (! empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
            $consulta->where('estado', strtoupper($filtros['estado']));
        }
        if (! empty($filtros['periodo']) && $filtros['periodo'] !== 'todos') {
            $consulta->where('periodo', $filtros['periodo']);
        }

        $campanias = $consulta->get()->map(fn (CampaniaSeguimiento $c) => $this->resumen($c));

        $convocados = $campanias->sum('convocados');
        $respondieron = $campanias->sum('respondieron');

        $verId = (int) $request->query('ver');
        $detalle = $verId > 0 ? CampaniaSeguimiento::find($verId) : null;

        return view('modules.seguimiento-egresado.paginas.campanias', [
            'campanias' => $campanias,
            'resumenGlobal' => [
                'campanias' => $campanias->count(),
                'convocados' => $convocados,
                'respondieron' => $respondieron,
                'pendientes' => $campanias->sum('pendientes'),
                'cobertura' => $convocados > 0 ? round($respondieron * 100 / $convocados, 1) : 0.0,
            ],
            'promociones' => app(CatalogosDeCampania::class)->promociones(),
            'periodos' => app(CatalogosDeCampania::class)->periodos(),
            'filtros' => $filtros,
            'detalle' => $detalle ? $this->datosDeDetalle($detalle) : null,
        ]);
    }

    /** Contenido de la ventana de detalle de una campaña (se carga al pulsar «Ver detalle»). */
    public function detalle(int $id): View
    {
        return view('modules.seguimiento-egresado.partials.campania-detalle', $this->datosDeDetalle(CampaniaSeguimiento::findOrFail($id)));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, true);

        $campania = DB::transaction(function () use ($datos) {
            $campania = CampaniaSeguimiento::create($this->atributos($datos) + [
                'tipo' => $datos['tipo'],
                'estado' => EstadoDeCampanias::paraFechas($datos['fecha_inicio'], $datos['fecha_fin']),
                'id_usuario_creacion' => UsuarioInterno::idActual() ?? 1,
            ]);
            $campania->cohortes()->sync($datos['cohortes']);

            return $campania;
        });

        $destino = $request->input('volver_a') === 'empresas'
            ? redirect()->route('seguimiento-egresado.empresas', ['campania' => $campania->id])
            : redirect()->route('seguimiento-egresado.campanias', ['ver' => $campania->id]);

        return $destino
            ->with('success', "La campaña «{$campania->nombre}» quedó creada. Se abre y se cierra sola en las fechas que elegiste.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $campania = CampaniaSeguimiento::findOrFail($id);
        $datos = $this->validar($request, false);

        DB::transaction(function () use ($campania, $datos) {
            $campania->update($this->atributos($datos) + ['estado' => EstadoDeCampanias::paraFechas($datos['fecha_inicio'], $datos['fecha_fin'])]);
            $campania->cohortes()->sync($datos['cohortes']);
        });

        return redirect()->route('seguimiento-egresado.campanias', ['ver' => $campania->id])
            ->with('success', "La campaña «{$campania->nombre}» se actualizó.");
    }

    /**
     * Envía la invitación a los egresados confirmados de las promociones de la campaña que dejaron su correo personal.
     * Quien aún no lo dejó no se convoca: primero debe completar su contacto en el Padrón.
     */
    public function emitirInvitaciones(int $id, EnvioDeEnlaces $envio): RedirectResponse
    {
        $campania = CampaniaSeguimiento::with('cohortes')->findOrFail($id);

        if ($rechazo = $this->noSePuedeConvocar($campania)) {
            return $this->volver($campania)->withErrors(['general' => $rechazo]);
        }

        $egresados = Egresado::with('contactoVigente')->whereIn('id_cohorte', $campania->cohortes->pluck('id'))
            ->whereHas('estadoVigente.estadoAcademico', fn ($q) => $q->whereIn('codigo', CatalogosDeCampania::ETAPAS_EGRESADO))
            ->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')->get();

        if ($egresados->isEmpty()) {
            return $this->volver($campania)->withErrors(['general' => 'Todavía no hay egresados confirmados en las promociones de esta campaña. Confirma el egreso de los alumnos desde el Padrón.']);
        }

        $yaInvitados = InvitacionEgresado::where('id_campania', $campania->id)->pluck('id_egresado')->all();
        $canalPersonal = CatCanalEnvio::where('codigo', 'CORREO_PERSONAL')->value('id');
        $expiraEn = Carbon::parse($campania->fecha_fin)->endOfDay();

        $nuevas = 0;
        $repetidos = 0;
        $sinContacto = 0;
        $enlaces = [];
        $porEnviar = [];

        DB::transaction(function () use ($egresados, $yaInvitados, $campania, $canalPersonal, $expiraEn, &$nuevas, &$repetidos, &$sinContacto, &$enlaces, &$porEnviar) {
            foreach ($egresados as $egresado) {
                if (in_array($egresado->id, $yaInvitados, true)) {
                    $repetidos++;

                    continue;
                }
                if (! $egresado->contactoVigente?->correo_personal) {
                    $sinContacto++;

                    continue;
                }

                // El enlace en claro solo existe aquí y en pantalla; en la base queda su huella.
                $tokenPlano = Str::random(48);
                $invitacion = InvitacionEgresado::create([
                    'id_campania' => $campania->id, 'id_egresado' => $egresado->id, 'proposito' => 'SEGUIMIENTO',
                    'token_hash' => EnlaceInvitacion::hashDe($tokenPlano), 'expira_en' => $expiraEn, 'id_canal_envio' => $canalPersonal,
                    'enviado_en' => null, 'recordatorios_enviados' => 0, 'ultimo_recordatorio_en' => null,
                    'entrega_fallida' => false, 'respondido_en' => null, 'estado' => 'PENDIENTE',
                ]);
                $porEnviar[count($enlaces)] = [$invitacion, $tokenPlano];
                $enlaces[] = ['egresado' => $egresado->nombre_completo, 'matricula' => $egresado->codigo_matricula, 'url' => url('seguimiento-egresado/acceso/'.$tokenPlano)];
                $nuevas++;
            }
        });

        // El correo se envía con las invitaciones ya guardadas; si falla, el enlace sigue en pantalla.
        $enviados = 0;
        foreach ($porEnviar as $indice => [$invitacion, $tokenPlano]) {
            $resultado = $envio->enviar($invitacion, $tokenPlano);
            $enlaces[$indice] += $resultado->paraPantalla();
            $enviados += $resultado->enviado() ? 1 : 0;
        }

        $mensaje = $nuevas === 0 ? 'No había egresados nuevos por invitar.' : ($nuevas === 1 ? 'Se envió 1 invitación.' : "Se enviaron {$nuevas} invitaciones.");
        if ($nuevas > 0) {
            $mensaje .= $enviados === 0
                ? ' Entrega a cada egresado su enlace de abajo.'
                : ($enviados === $nuevas ? ' Todas salieron por correo.' : " {$enviados} salieron por correo; para el resto usa los enlaces de abajo.");
        }
        if ($repetidos > 0) {
            $mensaje .= " {$repetidos} ya tenían invitación y se omitieron.";
        }
        if ($sinContacto > 0) {
            $mensaje .= " {$sinContacto} ".($sinContacto === 1 ? 'no tiene contacto y no fue convocado' : 'no tienen contacto y no fueron convocados').': que lo completen desde su promoción en el Padrón.';
        }

        return $this->volver($campania)->with('success', $mensaje)->with('enlaces_emitidos', $enlaces);
    }

    /** Recordatorio a un egresado que aún no respondió: genera un enlace nuevo (el anterior deja de servir) y lo envía. */
    public function reenviarRecordatorio(int $id, EnvioDeEnlaces $envio): RedirectResponse
    {
        $invitacion = InvitacionEgresado::with(['egresado.contactoVigente', 'campania'])->findOrFail($id);

        if ($invitacion->estado !== 'PENDIENTE' || $invitacion->campania?->estado === 'CERRADA') {
            return redirect()->back()->withErrors(['general' => 'Solo se puede enviar recordatorio a quien aún no respondió, mientras la campaña siga abierta.']);
        }

        $enlace = $this->renovarYEnviar($invitacion, $envio);
        $nombre = $enlace['egresado'];
        $mensaje = $enlace['enviado']
            ? "Recordatorio #{$enlace['numero_recordatorio']} enviado por correo a {$nombre}."
            : "Recordatorio #{$enlace['numero_recordatorio']} generado para {$nombre}. Entrega el enlace de abajo al egresado.";

        return $this->volver($invitacion->campania)->with('success', $mensaje)->with('enlaces_emitidos', [$enlace]);
    }

    /** Recordatorio a todos los egresados de la campaña que aún no respondieron. */
    public function recordarPendientes(int $id, EnvioDeEnlaces $envio): RedirectResponse
    {
        $campania = CampaniaSeguimiento::findOrFail($id);

        if ($campania->estado === 'CERRADA') {
            return $this->volver($campania)->withErrors(['general' => 'La campaña ya cerró: no se pueden enviar recordatorios.']);
        }

        $pendientes = InvitacionEgresado::with('egresado.contactoVigente')->where('id_campania', $campania->id)
            ->where('proposito', 'SEGUIMIENTO')->where('estado', 'PENDIENTE')->where('expira_en', '>', now())->orderBy('id')->get();

        if ($pendientes->isEmpty()) {
            return $this->volver($campania)->with('info', 'No hay egresados pendientes: todos respondieron o su enlace ya venció.');
        }

        $enlaces = [];
        $omitidos = 0;
        foreach ($pendientes as $invitacion) {
            if (! $invitacion->egresado?->contactoVigente?->correo_personal) {
                $omitidos++;

                continue;
            }
            $enlaces[] = $this->renovarYEnviar($invitacion, $envio);
        }

        $total = count($enlaces);
        $enviados = count(array_filter($enlaces, fn (array $e) => $e['enviado']));
        $mensaje = $total === 1 ? 'Se envió 1 recordatorio.' : "Se enviaron {$total} recordatorios.";
        $mensaje .= $enviados === 0
            ? ' Entrega a cada egresado su enlace de abajo.'
            : ($enviados === $total ? ' Todos salieron por correo.' : " {$enviados} salieron por correo; para el resto usa los enlaces de abajo.");
        if ($omitidos > 0) {
            $mensaje .= " {$omitidos} sin correo personal se omitieron.";
        }

        return $this->volver($campania)->with('success', $mensaje)->with('enlaces_emitidos', $enlaces);
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function volver(CampaniaSeguimiento $campania): RedirectResponse
    {
        return redirect()->route('seguimiento-egresado.campanias', ['ver' => $campania->id]);
    }

    private function noSePuedeConvocar(CampaniaSeguimiento $campania): ?string
    {
        return match (true) {
            $campania->tipo !== 'EGRESADOS' => 'Esta campaña es para empleadores: sus enlaces se envían desde Encuesta empresarial.',
            $campania->estado === 'CERRADA' => 'La campaña ya cerró: no se pueden enviar invitaciones.',
            $campania->cohortes->isEmpty() => 'La campaña no tiene promociones. Edítala y elige al menos una.',
            default => null,
        };
    }

    /**
     * Nuevo enlace para una invitación pendiente y envío por correo. El enlace anterior deja de servir.
     *
     * @return array<string, mixed>
     */
    private function renovarYEnviar(InvitacionEgresado $invitacion, EnvioDeEnlaces $envio): array
    {
        $tokenPlano = Str::random(48);
        $invitacion->increment('recordatorios_enviados');
        $invitacion->update(['token_hash' => EnlaceInvitacion::hashDe($tokenPlano), 'ultimo_recordatorio_en' => now()]);

        $egresado = $invitacion->egresado;
        $resultado = $envio->enviar($invitacion, $tokenPlano);

        return [
            'egresado' => $egresado->nombre_completo,
            'matricula' => $egresado->codigo_matricula,
            'url' => url('seguimiento-egresado/acceso/'.$tokenPlano),
            'numero_recordatorio' => $invitacion->recordatorios_enviados,
            'enviado' => $resultado->enviado(),
        ] + $resultado->paraPantalla();
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $creando): array
    {
        $reglas = [
            'nombre' => 'required|string|max:160',
            'horizonte_anios' => 'nullable|integer|in:1,3,5,10',
            'periodo' => ['required', 'string', 'regex:/^\d{4}-(I|II)$/'],
            'fecha_inicio' => 'required|date',
            'fecha_fin' => $creando ? 'required|date|after_or_equal:fecha_inicio|after_or_equal:today' : 'required|date|after_or_equal:fecha_inicio',
            'cohortes' => 'required|array|min:1',
            'cohortes.*' => 'exists:'.Esquema::validar('cohorte').',id',
            'descripcion' => 'nullable|string|max:1000',
        ];
        if ($creando) {
            $reglas['tipo'] = 'required|in:EGRESADOS,EMPRESAS';
        }

        return $request->validate($reglas, [
            'nombre.required' => 'Escribe el nombre de la campaña.',
            'tipo.required' => 'Elige para quién es la campaña.',
            'tipo.in' => 'Elige si la campaña es para egresados o para empleadores.',
            'periodo.required' => 'Elige el periodo académico.',
            'periodo.regex' => 'Elige un periodo de la lista.',
            'fecha_inicio.required' => 'Indica desde cuándo está abierta la campaña.',
            'fecha_fin.required' => 'Indica hasta cuándo está abierta la campaña.',
            'fecha_fin.after_or_equal' => 'La campaña no puede terminar antes de empezar ni en una fecha que ya pasó.',
            'cohortes.required' => 'Elige al menos una promoción.',
            'cohortes.min' => 'Elige al menos una promoción.',
        ]);
    }

    /** @return array<string, mixed> */
    private function atributos(array $datos): array
    {
        return [
            'nombre' => $datos['nombre'],
            'finalidad' => 'SEGUIMIENTO',
            'horizonte_anios' => $datos['horizonte_anios'] ?? null,
            'periodo' => $datos['periodo'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'],
            'descripcion' => $datos['descripcion'] ?? null,
        ];
    }

    /** Fila de la lista: cuántos fueron convocados, cuántos respondieron y cuántos faltan. */
    private function resumen(CampaniaSeguimiento $c): array
    {
        $invitaciones = $c->invitaciones;
        $convocados = $invitaciones->count();
        $respondieron = $invitaciones->filter(fn ($i) => $i->estado === 'COMPLETADA' || $i->medicion !== null)->count();

        return [
            'id' => $c->id, 'nombre' => $c->nombre, 'tipo' => $c->tipo, 'periodo' => $c->periodo,
            'fecha_inicio' => $c->fecha_inicio, 'fecha_fin' => $c->fecha_fin, 'estado' => $c->estado,
            'promociones' => $c->cohortes->sortBy('anio_ingreso'),
            'convocados' => $convocados, 'respondieron' => $respondieron,
            'pendientes' => $invitaciones->where('estado', 'PENDIENTE')->count(),
            'cobertura' => $convocados > 0 ? round($respondieron * 100 / $convocados, 1) : 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function datosDeDetalle(CampaniaSeguimiento $campania): array
    {
        $campania->load('cohortes');
        $invitaciones = InvitacionEgresado::with(['egresado.cohorte', 'medicion'])->where('id_campania', $campania->id)->orderBy('id')->get();
        $resumen = $this->resumen($campania->load('invitaciones.medicion'));

        $empresas = DB::table('seguimiento_egresado.invitacion_empresa')->where('id_campania', $campania->id);

        return [
            'campania' => $campania,
            'resumen' => $resumen,
            'invitaciones' => $invitaciones,
            'puedeVerRespuestas' => (bool) SesionAcceso::desde(request())->actual()?->puede('seguimiento.consultar'),
            'pendientesConvocables' => $invitaciones->where('estado', 'PENDIENTE')->where('expira_en', '>', now())->count(),
            'empresas' => ['enlaces' => (clone $empresas)->count(), 'respondieron' => (clone $empresas)->where('estado', 'COMPLETADA')->count()],
            'promociones' => app(CatalogosDeCampania::class)->promociones(),
            'periodos' => app(CatalogosDeCampania::class)->periodos($campania->periodo),
        ];
    }
}
