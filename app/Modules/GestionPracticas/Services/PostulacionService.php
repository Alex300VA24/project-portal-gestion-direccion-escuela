<?php

namespace App\Modules\GestionPracticas\Services;

use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Validation\ValidationException;

final class PostulacionService
{
    public function __construct(
        private readonly ConvocatoriaService $convocatorias,
        private readonly PracticaService $practicas,
    ) {}

    public function postular(Actor $actor, Convocatoria $convocatoria): Postulacion
    {
        $idEstudiante = $actor->requiereEstudiante();

        if (! $this->convocatorias->estaVigente($convocatoria)) {
            throw ValidationException::withMessages(['convocatoria' => 'Esta convocatoria ya no admite postulaciones: está cerrada o venció su fecha de cierre.']);
        }

        $this->practicas->exigirSinPracticaActiva($idEstudiante, 'convocatoria', 'postular a otra convocatoria');

        $yaPostulo = Postulacion::where('id_convocatoria', $convocatoria->id_convocatoria)
            ->where('id_estudiante', $idEstudiante)->exists();

        if ($yaPostulo) {
            throw ValidationException::withMessages(['convocatoria' => 'Ya registró su postulación a esta convocatoria.']);
        }

        return Postulacion::create([
            'id_convocatoria' => $convocatoria->id_convocatoria,
            'id_estudiante' => $idEstudiante,
            'estado' => Postulacion::POSTULADO,
        ])->refresh();
    }

    /** El estudiante informa si fue seleccionado, no seleccionado o si desiste. */
    public function registrarResultado(Postulacion $postulacion, Actor $actor, string $resultado): Postulacion
    {
        if (! in_array($resultado, [Postulacion::SELECCIONADO, Postulacion::NO_SELECCIONADO, Postulacion::DESISTIDO], true)) {
            throw ValidationException::withMessages(['resultado' => 'Elija una de las opciones: fui seleccionado, no fui seleccionado o desistí.']);
        }

        if ($postulacion->estado !== Postulacion::POSTULADO) {
            throw ValidationException::withMessages(['resultado' => 'El resultado de esta postulación ya fue registrado.']);
        }

        if ($resultado === Postulacion::SELECCIONADO) {
            $this->practicas->exigirSinPracticaActiva((int) $postulacion->id_estudiante, 'resultado', 'registrarse como seleccionado en otra convocatoria');
        }

        $postulacion->update([
            'estado' => $resultado,
            'resultado_en' => now(),
            'resultado_registrado_por' => $actor->requiereUsuario(),
        ]);

        return $postulacion;
    }
}
