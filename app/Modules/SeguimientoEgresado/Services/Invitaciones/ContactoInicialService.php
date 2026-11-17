<?php

namespace App\Modules\SeguimientoEgresado\Services\Invitaciones;

use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use Illuminate\Support\Collection;
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
     *
     * @return array{egresado: string, matricula: string, url: string, correo: string, correo_texto: string, correo_insignia: string}
     */
    public function invitar(Egresado $egresado): array
    {
        $tokenPlano = Str::random(48);

        $invitacion = DB::transaction(function () use ($egresado, $tokenPlano) {
            InvitacionEgresado::where('id_egresado', $egresado->id)
                ->where('proposito', 'CONTACTO_INICIAL')
                ->where('estado', 'PENDIENTE')
                ->update(['estado' => 'ANULADA']);

            return InvitacionEgresado::create([
                'id_campania' => null,
                'id_egresado' => $egresado->id,
                'proposito' => 'CONTACTO_INICIAL',
                'token_hash' => EnlaceInvitacion::hashDe($tokenPlano),
                'expira_en' => now()->addDays(max(1, (int) config('seguimiento-egresado.correo.dias_contacto_inicial')))->endOfDay(),
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
     * Invita a quienes de la cohorte aún no dejaron su contacto ni tienen un enlace pendiente vigente.
     * Quien ya tiene un enlace pendiente se omite (su enlace no se puede recuperar: se le da uno nuevo individualmente).
     *
     * @return array{enlaces: list<array>, con_contacto: int, con_enlace: int}
     */
    public function invitarCohorte(Cohorte $cohorte): array
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
                $enlaces[] = $this->invitar($egresado);
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
}
