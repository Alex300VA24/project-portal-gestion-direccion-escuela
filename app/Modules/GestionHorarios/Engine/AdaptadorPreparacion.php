<?php

namespace App\Modules\GestionHorarios\Engine;

use DomainException;

final class AdaptadorPreparacion
{
    /** La salida es intermedia; no es el contrato de entrada del motor Python. */
    public function convertir(array $instantanea): array
    {
        if (($instantanea['contrato'] ?? null) !== 'INSTANTANEA_INSTITUCIONAL_V1' || ! is_array($instantanea['datos'] ?? null)) {
            throw new DomainException('Instantánea incompatible.');
        }
        $datos = $instantanea['datos'];
        $json = json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($instantanea['huella_sha256'] ?? null) || ! hash_equals($instantanea['huella_sha256'], hash('sha256', $json))) {
            throw new DomainException('La huella de la instantánea no coincide.');
        }
        if (! isset($datos['actividades']) || ! is_array($datos['actividades'])) {
            throw new DomainException('Faltan actividades.');
        }
        foreach ($datos['actividades'] as &$actividad) {
            $inicio = $this->segundos($actividad['hora_inicio'] ?? null);
            $fin = $this->segundos($actividad['hora_fin'] ?? null);
            if ($fin <= $inicio || ($fin - $inicio) % 60 !== 0) {
                throw new DomainException('Duración de actividad no representable en minutos enteros.');
            }
            $actividad['duracion_minutos'] = intdiv($fin - $inicio, 60);
        }
        unset($actividad);
        $entrada = ['contrato' => 'HORARIOS_PREPARACION_V1', 'huella_instantanea' => $instantanea['huella_sha256'], 'configuracion' => ['tiempo_limite_segundos' => 150], 'listo_para_motor' => false, 'diagnosticos' => ['Las asociaciones de oferta y sección requieren conciliación.', 'Faltan calendario, población, patrones y sesiones candidatas.', 'Los parámetros de restricciones no se han interpretado.'], 'datos' => $datos];
        (new ValidarEntradaPreparacion)->validar($entrada);

        return $entrada;
    }

    public function paraMotor(array $entrada, ?array $complementos = null): array
    {
        if ($complementos === null) {
            throw new DomainException('Faltan complementos académicos para construir el contrato del motor.');
        }

        return (new TransformadorMotorAcademico)->convertir($entrada, $complementos);
    }

    /** El perfil institucional permanece cerrado aunque se completen datos parciales. */
    public function diagnosticarMotor(array $entrada, ?array $complementos = null): array
    {
        $mapeo = (new MapeoAcademicoParcial)->convertir($entrada);
        $diagnosticos = array_merge($mapeo['diagnosticos'], (new DiagnosticarFuentesMotor)->evaluar($mapeo, $complementos));
        $diagnosticos[] = ['entidad' => 'periodo', 'id' => (string) ($mapeo['periodo']['id_periodo'] ?? ''), 'codigo' => 'POLITICAS_INSTITUCIONALES_PENDIENTES'];
        $diagnosticos[] = ['entidad' => 'periodo', 'id' => (string) ($mapeo['periodo']['id_periodo'] ?? ''), 'codigo' => 'COHORTES_Y_SUBGRUPOS_NO_ACREDITADOS'];

        return ['compatible' => false, 'diagnosticos' => $diagnosticos, 'entrada' => null];
    }

    private function segundos(mixed $hora): int
    {
        if (! is_string($hora) || ! preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $hora, $m) || (int) $m[1] > 23 || (int) $m[2] > 59 || (int) $m[3] > 59) {
            throw new DomainException('Hora inválida.');
        }

        return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
    }
}
