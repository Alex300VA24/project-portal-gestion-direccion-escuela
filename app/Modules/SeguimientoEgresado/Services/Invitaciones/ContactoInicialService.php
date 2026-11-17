<?php

namespace App\Modules\SeguimientoEgresado\Services\Invitaciones;

use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contacto inicial: invita a un egresado a dejar sus datos de contacto antes de cualquier campaña de seguimiento.
 * El enlace sirve para entrar a su expediente (CONTACTO_INICIAL, sin campaña); en la BD queda solo su SHA-256.
 */
final class ContactoInicialService
{
    public function __construct(private readonly EnvioDeEnlaces $envio) {}

    /**
     * Genera un enlace nuevo para el egresado y lo envía por correo. Si ya tenía un enlace de contacto inicial
     * pendiente, queda anulado: así «Enlace nuevo» también sirve cuando el anterior se perdió o se compartió.
     * El enlace vence al final de la fecha límite; sin fecha límite, a los días configurados desde hoy.
     *
     * @return array{egresado: string, matricula: string, url: string, correo: string, correo_texto: string, correo_insignia: string}
     */
    public function invitar(Egresado $egresado, ?CarbonInterface $fechaLimite = null): array
    {
        $tokenPlano = Str::random(48);
        $venceEn = ($fechaLimite ?? now()->addDays(max(1, (int) config('seguimiento-egresado.correo.dias_contacto_inicial'))))->copy()->endOfDay();

        $invitacion = DB::transaction(function () use ($egresado, $tokenPlano, $venceEn) {
            InvitacionEgresado::where('id_egresado', $egresado->id)
                ->where('proposito', 'CONTACTO_INICIAL')
                ->where('estado', 'PENDIENTE')
                ->update(['estado' => 'ANULADA']);

            return InvitacionEgresado::create([
                'id_campania' => null,
                'id_egresado' => $egresado->id,
                'proposito' => 'CONTACTO_INICIAL',
                'token_hash' => EnlaceInvitacion::hashDe($tokenPlano),
                'expira_en' => $venceEn,
                'recordatorios_enviados' => 0,
                'entrega_fallida' => false,
                'estado' => 'PENDIENTE',
            ]);
        });

        $resultado = $this->envio->enviar($invitacion, $tokenPlano);

        return [
            'egresado' => $egresado->nombre_completo,
            'matricula' => $egresado->codigo_matricula,
            'url' => url('seguimiento-egresado/acceso/'.$tokenPlano),
        ] + $resultado->paraPantalla();
    }

    /**
     * Invita a quienes de la promoción aún no dejaron su contacto ni tienen un enlace pendiente vigente.
     * Quien ya tiene un enlace pendiente se omite (su enlace no se puede recuperar: se le da uno nuevo individualmente).
     *
     * @return array{enlaces: list<array>, con_contacto: int, con_enlace: int}
     */
    public function invitarCohorte(Cohorte $cohorte, ?CarbonInterface $fechaLimite = null): array
    {
        $egresados = Egresado::where('id_cohorte', $cohorte->id)
            ->with('contactoVigente')
            ->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')
            ->get();

        $conEnlace = $this->idsConEnlacePendiente($egresados->pluck('id')->all());

        $enlaces = [];
        $conContacto = 0;
        $conEnlaceVigente = 0;
        foreach ($egresados as $egresado) {
            if ($egresado->contactoVigente !== null) {
                $conContacto++;
            } elseif (in_array($egresado->id, $conEnlace, true)) {
                $conEnlaceVigente++;
            } else {
                $enlaces[] = $this->invitar($egresado, $fechaLimite);
            }
        }

        return ['enlaces' => $enlaces, 'con_contacto' => $conContacto, 'con_enlace' => $conEnlaceVigente];
    }

    /**
     * Última invitación de contacto inicial de cada egresado, para mostrar su estado en la pantalla.
     *
     * @param  list<int>  $egresadoIds
     * @return Collection<int, InvitacionEgresado> indexada por id de egresado
     */
    public function ultimasInvitaciones(array $egresadoIds)
    {
        return InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')
            ->whereIn('id_egresado', $egresadoIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id_egresado');
    }

    /**
     * @param  list<int>  $egresadoIds
     * @return list<int>
     */
    private function idsConEnlacePendiente(array $egresadoIds): array
    {
        return InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')
            ->where('estado', 'PENDIENTE')
            ->where('expira_en', '>', now())
            ->whereIn('id_egresado', $egresadoIds)
            ->pluck('id_egresado')
            ->all();
    }

    /**
     * Estado del contacto inicial de un alumno: «completo» si ya dejó su contacto; si no, según su último enlace:
     * «pendiente» (vigente), «vencido» (pasó la fecha límite) o «sin_enlace».
     */
    public function estadoDe(Egresado $egresado, ?InvitacionEgresado $invitacion): string
    {
        if ($egresado->contactoVigente !== null) {
            return 'completo';
        }
        if ($invitacion === null || $invitacion->estado === 'ANULADA') {
            return 'sin_enlace';
        }

        return $invitacion->estado === 'PENDIENTE' && Carbon::parse($invitacion->expira_en)->isFuture() ? 'pendiente' : 'vencido';
    }

    /**
     * Avance de cada promoción por los cuatro pasos (promoción, alumnos, enlaces, contacto): cuántos alumnos tiene, cuántos ya
     * dejaron su contacto, cuántos siguen en plazo, cuántos dejaron vencer su enlace y cuántos no tienen enlace.
     * «paso» es el que toca hacer: 2 (cargar alumnos), 3 (enviar enlaces) o 4 (seguimiento del contacto);
     * «con_avance» dice si ya hay algún alumno con enlace o contacto, es decir, si hay algo que seguir.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function avancePorPromocion(?int $promocionId = null): Collection
    {
        $promociones = Cohorte::with('planCurricular')->when($promocionId, fn ($q) => $q->where('id', $promocionId))->get();
        $egresados = Egresado::with('contactoVigente')->whereIn('id_cohorte', $promociones->pluck('id'))->get(['id', 'id_cohorte'])->groupBy('id_cohorte');
        $invitaciones = $this->ultimasInvitaciones($egresados->flatten()->pluck('id')->all());

        return $promociones->map(function (Cohorte $promocion) use ($egresados, $invitaciones) {
            $fila = ['promocion' => $promocion, 'total' => 0, 'completaron' => 0, 'pendientes' => 0, 'vencidos' => 0, 'sin_enlace' => 0, 'fecha_limite' => null];

            foreach ($egresados->get($promocion->id, collect()) as $egresado) {
                $invitacion = $invitaciones->get($egresado->id);
                $estado = $this->estadoDe($egresado, $invitacion);
                $fila['total']++;
                $fila[['completo' => 'completaron', 'pendiente' => 'pendientes', 'vencido' => 'vencidos', 'sin_enlace' => 'sin_enlace'][$estado]]++;

                if (in_array($estado, ['pendiente', 'vencido'], true)) {
                    $vence = Carbon::parse($invitacion->expira_en);
                    $fila['fecha_limite'] = $fila['fecha_limite'] === null || $vence->greaterThan($fila['fecha_limite']) ? $vence : $fila['fecha_limite'];
                }
            }

            // Si quedan alumnos sin enlace (por ejemplo, se cargaron más después), toca volver a enviar enlaces.
            $fila['con_avance'] = ($fila['completaron'] + $fila['pendientes'] + $fila['vencidos']) > 0;
            $fila['paso'] = $fila['total'] === 0 ? 2 : ($fila['sin_enlace'] > 0 ? 3 : 4);
            $fila['terminada'] = $fila['total'] > 0 && $fila['completaron'] === $fila['total'];

            return $fila;
        })->sortByDesc(fn (array $fila) => $fila['promocion']->anio_ingreso)->values();
    }
}
