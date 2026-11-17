<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Models\User;
use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use App\Modules\TesisProyectoTesis\Support\Estados;

/**
 * Pantalla exacta (por uuid) a la que lleva cada aviso, según el evento y la relación del
 * usuario con el proyecto. Se calcula en el servidor: nunca se redirige a una URL recibida.
 */
final class EnlaceNotificacionService
{
    private const P = 'tesis-proyecto-tesis.';

    public function destino(User $user, string $evento, ?int $idProyecto): string
    {
        $proyecto = $idProyecto ? Proyecto::query()->find($idProyecto) : null;

        if ($proyecto === null) {
            return route(self::P.'index');
        }

        $url = match (true) {
            $user->can('verComoAlumno', $proyecto) => $this->alumno($evento, $proyecto),
            $user->can('verComoJurado', $proyecto) && $this->esDeJurado($evento) => $this->jurado($evento, $proyecto),
            $user->can('verComoAsesor', $proyecto) => $this->asesor($evento, $proyecto),
            $user->can('verComoJurado', $proyecto) => $this->jurado($evento, $proyecto),
            $user->hasRole('secretaria') => $this->secretaria($evento, $proyecto),
            $user->hasRole('director_escuela') => $this->director($evento, $proyecto),
            default => null,
        };

        return $url ?? route(self::P.'index');
    }

    private function esDeJurado(string $evento): bool
    {
        return str_starts_with($evento, 'jurado.') || str_starts_with($evento, 'sustentacion.') || $evento === 'entrega.subida' || $evento === 'observacion.respondida';
    }

    private function alumno(string $evento, Proyecto $p): string
    {
        return match ($evento) {
            'avance.revisado', 'rubrica.verificada', 'observacion.levantada' => route(self::P.'alumno.proyecto.observaciones', $p),
            'asesor.designado', 'similitud.no_apto', 'proyecto.aprobado' => route(self::P.'alumno.proyecto.avances', $p),
            default => route(self::P.'alumno.panel'),
        };
    }

    private function asesor(string $evento, Proyecto $p): string
    {
        if ($evento === 'entrega.subida') {
            $pendiente = $this->entregaPendiente($p);
            if ($pendiente !== null && $pendiente->tipo === 'Final' && in_array($p->estado, Estados::DESARROLLO, true)) {
                return route(self::P.'asesor.verificar', $p);
            }
            if ($pendiente !== null) {
                return route(self::P.'asesor.revisar', [$p, $pendiente]);
            }
            if (in_array($p->estado, Estados::DESARROLLO, true)) {
                return route(self::P.'asesor.verificar', $p);
            }
        }

        return route(self::P.'asesor.proyecto', $p);
    }

    private function jurado(string $evento, Proyecto $p): string
    {
        if (str_starts_with($evento, 'sustentacion.')) {
            $s = Sustentacion::query()->where('id_proyecto', $p->id_proyecto)->orderByDesc('numero_intento')->first();

            return $s ? route(self::P.'jurado.resultado', $s) : route(self::P.'jurado.sustentaciones');
        }
        if ($evento === 'jurado.reemplazado') {
            return route(self::P.'jurado.proyectos');
        }

        return route(self::P.'jurado.verificar', $p);
    }

    private function secretaria(string $evento, Proyecto $p): string
    {
        return match ($evento) {
            'asesor.solicitado' => ($s = Solicitud::query()->where('id_proyecto', $p->id_proyecto)->where('estado', 'Pendiente')->first())
                ? route(self::P.'secretaria.resoluciones', ['solicitud' => $s->uuid])
                : route(self::P.'secretaria.resoluciones'),
            'tesis.lista', 'sustentacion.resultado' => in_array($p->estado, [Estados::LISTO_SUSTENTAR, Estados::DESAPROBADO], true)
                ? route(self::P.'secretaria.sustentaciones.programar', $p)
                : route(self::P.'secretaria.sustentaciones'),
            'dictamen.generado', 'acta.generada', 'acta.informada' => ($a = Acta::query()->where('id_proyecto', $p->id_proyecto)
                ->when($evento !== 'acta.informada', fn ($q) => $q->where('tipo', $evento === 'dictamen.generado' ? 'Dictamen' : 'Sustentacion'))
                ->orderByDesc('emitida_en')->first())
                ? route(self::P.'secretaria.actas.ver', $a)
                : route(self::P.'secretaria.actas'),
            default => route(self::P.'secretaria.resoluciones'),
        };
    }

    private function director(string $evento, Proyecto $p): string
    {
        return match (true) {
            $p->estado === Estados::REGISTRADO => route(self::P.'director.asesor.solicitar', $p),
            $p->estado === Estados::VERIFICADO_ASESOR || str_starts_with($evento, 'jurado.') => route(self::P.'director.jurado', $p),
            default => route(self::P.'director.proyectos', ['buscar' => $p->codigo]),
        };
    }

    private function entregaPendiente(Proyecto $p): ?Entrega
    {
        return Entrega::query()->where('id_proyecto', $p->id_proyecto)->where('estado', 'Pendiente')->orderByDesc('version')->first();
    }
}
