<?php

namespace App\Modules\GestionPracticas\Services;

use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\ObservacionConvocatoria;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de la convocatoria: borrador → en_revision → (observada ↔ en_revision) → publicada → cerrada.
 */
final class ConvocatoriaService
{
    public function __construct(private readonly EmpresaService $empresas) {}

    /** @param array<string, mixed> $datos */
    public function crear(Actor $actor, array $datos): Convocatoria
    {
        $idEmpresa = $actor->requiereEmpresa();

        if (! $this->empresas->estaActiva($idEmpresa)) {
            throw ValidationException::withMessages(['empresa' => 'Su empresa está inactiva: contacte al administrador.']);
        }

        return Convocatoria::create([
            ...$this->campos($datos),
            'id_empresa' => $idEmpresa,
            'registrada_por' => $actor->requiereUsuario(),
            'estado' => Convocatoria::BORRADOR,
        ])->refresh();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Convocatoria $convocatoria, array $datos): Convocatoria
    {
        $this->exigir($convocatoria, [Convocatoria::BORRADOR, Convocatoria::OBSERVADA], 'editar');

        $convocatoria->update($this->campos($datos));

        return $convocatoria;
    }

    /** La empresa envía (o reenvía, tras corregir) la convocatoria al administrador. */
    public function enviarARevision(Convocatoria $convocatoria): Convocatoria
    {
        $this->exigir($convocatoria, [Convocatoria::BORRADOR, Convocatoria::OBSERVADA], 'enviar a revisión');

        DB::transaction(function () use ($convocatoria): void {
            $convocatoria->observaciones()
                ->where('estado', ObservacionConvocatoria::PENDIENTE)
                ->update(['estado' => ObservacionConvocatoria::SUBSANADA, 'subsanada_en' => now()]);

            $convocatoria->update(['estado' => Convocatoria::EN_REVISION, 'enviada_en' => now()]);
        });

        return $convocatoria;
    }

    /** El administrador aprueba y publica. */
    public function aprobar(Convocatoria $convocatoria, Actor $admin): Convocatoria
    {
        $this->exigir($convocatoria, [Convocatoria::EN_REVISION], 'aprobar');

        $hoy = now('America/Lima')->toDateString();

        if ($convocatoria->fecha_cierre->toDateString() < $hoy) {
            throw ValidationException::withMessages([
                'estado' => 'La fecha de cierre ya pasó: la empresa debe corregirla antes de publicar.',
            ]);
        }

        $convocatoria->update([
            'estado' => Convocatoria::PUBLICADA,
            'fecha_publicacion' => now(),
            'publicada_por' => $admin->requiereUsuario(),
        ]);

        return $convocatoria;
    }

    /** El administrador devuelve la convocatoria con una observación. */
    public function observar(Convocatoria $convocatoria, Actor $admin, string $descripcion): Convocatoria
    {
        $this->exigir($convocatoria, [Convocatoria::EN_REVISION], 'observar');

        DB::transaction(function () use ($convocatoria, $admin, $descripcion): void {
            $convocatoria->observaciones()->create([
                'descripcion' => $descripcion,
                'registrado_por' => $admin->requiereUsuario(),
            ]);

            $convocatoria->update(['estado' => Convocatoria::OBSERVADA]);
        });

        return $convocatoria;
    }

    public function cerrar(Convocatoria $convocatoria): Convocatoria
    {
        $this->exigir($convocatoria, [Convocatoria::PUBLICADA], 'cerrar');

        $convocatoria->update(['estado' => Convocatoria::CERRADA]);

        return $convocatoria;
    }

    public function anular(Convocatoria $convocatoria): Convocatoria
    {
        $this->exigir($convocatoria, [Convocatoria::BORRADOR, Convocatoria::OBSERVADA], 'anular');

        $convocatoria->update(['estado' => Convocatoria::ANULADA]);

        return $convocatoria;
    }

    /** Convocatorias que un estudiante puede ver: publicadas y vigentes. */
    public function vigentes(): Builder
    {
        return Convocatoria::query()
            ->where('estado', Convocatoria::PUBLICADA)
            ->whereDate('fecha_cierre', '>=', now('America/Lima')->toDateString());
    }

    public function estaVigente(Convocatoria $convocatoria): bool
    {
        return $convocatoria->estado === Convocatoria::PUBLICADA
            && $convocatoria->fecha_cierre->toDateString() >= now('America/Lima')->toDateString();
    }

    /** @param list<string> $permitidos */
    private function exigir(Convocatoria $convocatoria, array $permitidos, string $accion): void
    {
        if (! in_array($convocatoria->estado, $permitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede {$accion} una convocatoria en estado «{$convocatoria->estado}».",
            ]);
        }
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    private function campos(array $datos): array
    {
        return array_intersect_key($datos, array_flip([
            'titulo', 'descripcion', 'requisitos', 'enlace_postulacion',
            'tipo_practica', 'modalidad', 'vacantes', 'fecha_cierre',
        ]));
    }
}
