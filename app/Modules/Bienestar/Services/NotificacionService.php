<?php

namespace App\Modules\Bienestar\Services;

use App\Models\User;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\Notificacion;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\EstadoSolicitud;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Avisos internos del módulo. Nunca incluyen motivos, diagnósticos ni nombres de terceros. */
final class NotificacionService
{
    public function solicitudNueva(int $id, bool $subsanada = false): void
    {
        $this->aRol(BienestarPermissions::ROL_SECRETARIA, $subsanada ? 'solicitud_subsanada' : 'solicitud_nueva',
            $subsanada ? 'Solicitud subsanada' : 'Nueva solicitud', "La solicitud #{$id} está lista para tu revisión.", $id);
    }

    public function solicitudRemitida(int $id): void
    {
        $this->aRol(BienestarPermissions::ROL_BIENESTAR, 'solicitud_remitida', 'Solicitud remitida',
            "Secretaría remitió la solicitud #{$id} para dictamen.", $id);
    }

    public function solicitudCambioEstado(SolicitudApoyo $s, ?string $anterior): void
    {
        $this->aPersona($this->personaEstudiante($s->id_estudiante), 'solicitud_estado', 'Solicitud: '.mb_strtolower(EstadoSolicitud::texto($s->estado)),
            EstadoSolicitud::aviso($s->estado, $s->id_solicitud, $anterior), $s->id_solicitud);
    }

    public function reporteCambioEstado(AlertaRiesgo $alerta): void
    {
        $texto = match ($alerta->estado) {
            'en_revision' => 'está en revisión',
            'remitida_bienestar' => 'fue revisado y pasó a Bienestar',
            'en_atencion' => 'está en atención',
            'atendida' => 'fue atendido',
            'descartada' => 'fue descartado',
            default => 'cambió de estado',
        };
        $this->aPersona(DB::table('core.docente')->where('id_docente', $alerta->id_docente)->value('id_persona'),
            'reporte_estado', 'Estado de tu reporte', "Tu reporte #{$alerta->id_alerta} {$texto}.", $alerta->id_alerta);
    }

    public function alertaNueva(int $id): void
    {
        $this->aRol(BienestarPermissions::ROL_SECRETARIA, 'alerta_nueva', 'Alerta por revisar',
            "La alerta #{$id} ingresó a tu bandeja.", $id);
    }

    public function alertaRemitida(int $id): void
    {
        $this->aRol(BienestarPermissions::ROL_BIENESTAR, 'alerta_remitida', 'Alerta remitida',
            "Secretaría pasó la alerta #{$id} a Bienestar.", $id);
    }

    public function citaProgramada(int $idEstudiante, int $id): void
    {
        $this->aPersona($this->personaEstudiante($idEstudiante), 'cita_programada', 'Cita actualizada',
            'Hay novedades en tu agenda de citas. Revisa la fecha y el estado.', $id);
    }

    /** Consulta de las notificaciones propias del usuario (por su registro en seguridad.usuario). */
    public function propias(int $idUsuario): Builder
    {
        return Notificacion::query()
            ->where('id_usuario_destino', $idUsuario)
            ->where('id_modulo', $this->idModulo())
            ->orderByDesc('creada_en')->orderByDesc('id_notificacion');
    }

    public function sinLeer(int $idUsuario): int
    {
        return $this->propias($idUsuario)->whereNull('leida_en')->reorder()->count();
    }

    public function destino(Notificacion $n): string
    {
        $id = $n->referencia_id;

        return match ($n->tipo_evento) {
            'solicitud_nueva', 'solicitud_subsanada' => route('bienestar.secretaria.solicitudes.revisar', $id),
            'solicitud_remitida' => route('bienestar.panel.solicitudes.show', $id),
            'solicitud_estado', 'solicitud_observada', 'solicitud_dictaminada' => route('bienestar.alumno.solicitudes.show', $id),
            'reporte_estado' => route('bienestar.profesor.index'),
            'alerta_nueva' => route('bienestar.secretaria.alertas'),
            'alerta_remitida' => route('bienestar.panel.atender', $id),
            'cita_programada' => route('bienestar.alumno.citas.index'),
            default => route('bienestar.notificaciones.index'),
        };
    }

    private function aRol(string $rol, string $tipo, string $titulo, string $mensaje, int $id): void
    {
        if (! Role::where('name', $rol)->exists()) {
            return;
        }
        $usuarios = DB::table('seguridad.usuario')
            ->whereIn('correo_institucional', User::role($rol)->pluck('email'))
            ->pluck('id_usuario');
        foreach ($usuarios as $idUsuario) {
            $this->crear((int) $idUsuario, $tipo, $titulo, $mensaje, $id);
        }
    }

    private function personaEstudiante(int $idEstudiante): ?int
    {
        return DB::table('core.estudiante')->where('id_estudiante', $idEstudiante)->value('id_persona');
    }

    private function aPersona(?int $idPersona, string $tipo, string $titulo, string $mensaje, int $id): void
    {
        $idUsuario = $idPersona ? DB::table('seguridad.usuario')->where('id_persona', $idPersona)->value('id_usuario') : null;
        if ($idUsuario) {
            $this->crear((int) $idUsuario, $tipo, $titulo, $mensaje, $id);
        }
    }

    private function crear(int $idUsuario, string $tipo, string $titulo, string $mensaje, int $id): void
    {
        Notificacion::create([
            'id_modulo' => $this->idModulo(), 'id_usuario_destino' => $idUsuario, 'tipo_evento' => $tipo,
            'titulo' => $titulo, 'mensaje' => $mensaje, 'canal' => 'portal', 'creada_en' => now(), 'enviada_en' => now(),
            'referencia_tabla' => match (true) {
                str_starts_with($tipo, 'solicitud') => 'bienestar.solicitud_apoyo',
                str_starts_with($tipo, 'alerta'), str_starts_with($tipo, 'reporte') => 'bienestar.alerta_riesgo',
                default => 'bienestar.cita',
            },
            'referencia_id' => $id,
        ]);
    }

    private function idModulo(): int
    {
        return (int) DB::table('seguridad.modulo_sistema')->where('codigo', 'bienestar')->value('id_modulo');
    }
}
