<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Models\User;
use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use Illuminate\Support\Facades\Route;

/**
 * Arma el contexto común de las pantallas: usuario, rol activo, menú lateral y avisos.
 */
final class NavegacionService
{
    public const ROLES = [
        'alumno' => 'Estudiante',
        'asesor' => 'Asesor',
        'jurado' => 'Jurado',
        'director' => 'Director de Escuela',
        'secretaria' => 'Secretaría',
    ];

    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly NotificacionService $notificaciones,
    ) {}

    /**
     * Contextos de trabajo disponibles para el usuario.
     *
     * @return list<string>
     */
    public function contextos(User $user, ?Identidad $id): array
    {
        if ($id === null) {
            return [];
        }

        $contextos = [];
        if ($user->hasRole('alumno') && $id->idEstudiante !== null) {
            $contextos[] = 'alumno';
        }
        if ($user->hasRole('profesor') && $id->idDocente !== null) {
            if (AsesorProyecto::query()->where('id_docente', $id->idDocente)->where('estado', 'Activo')->exists()) {
                $contextos[] = 'asesor';
            }
            if (JuradoProyecto::query()->where('id_docente', $id->idDocente)->where('estado', 'Activo')->exists()) {
                $contextos[] = 'jurado';
            }
        }
        if ($user->hasRole('director_escuela')) {
            $contextos[] = 'director';
        }
        if ($user->hasRole('secretaria')) {
            $contextos[] = 'secretaria';
        }

        return $contextos;
    }

    /**
     * @param  array<string, int>  $insignias  contador por clave de menú
     * @return array<string, mixed>
     */
    public function contexto(User $user, string $rol, string $activo, array $insignias = []): array
    {
        $id = $this->identidad->actual($user);
        $contextos = $this->contextos($user, $id);

        return [
            'tesisNav' => [
                'rol' => $rol,
                'rolEtiqueta' => self::ROLES[$rol] ?? '',
                'activo' => $activo,
                'usuario' => $id?->nombre ?? (string) $user->name,
                'iniciales' => $id?->iniciales() ?? mb_strtoupper(mb_substr((string) $user->name, 0, 2)),
                'cambiarRol' => count($contextos) > 1,
                'menu' => $this->menu($rol, $insignias),
                'avisos' => $id ? $this->notificaciones->recientes($id->idUsuario) : [],
                'noLeidas' => $id ? $this->notificaciones->noLeidas($id->idUsuario) : 0,
            ],
        ];
    }

    /** @return list<array{clave: string, etiqueta: string, url: string, insignia: int}> */
    private function menu(string $rol, array $insignias): array
    {
        $p = 'tesis-proyecto-tesis.';
        $items = match ($rol) {
            'alumno' => [
                ['inicio', 'Inicio', $p.'alumno.panel'],
                ['proyecto', 'Mi proyecto', $p.'alumno.proyecto'],
                ['avances', 'Avances', $p.'alumno.avances'],
                ['observaciones', 'Observaciones', $p.'alumno.observaciones'],
            ],
            'asesor' => [
                ['proyectos', 'Mis proyectos', $p.'asesor.proyectos'],
            ],
            'jurado' => [
                ['proyectos', 'Proyectos asignados', $p.'jurado.proyectos'],
                ['sustentaciones', 'Sustentaciones', $p.'jurado.sustentaciones'],
            ],
            'director' => [
                ['panel', 'Panel general', $p.'director.panel'],
                ['proyectos', 'Todos los proyectos', $p.'director.proyectos'],
            ],
            'secretaria' => [
                ['resoluciones', 'Resoluciones', $p.'secretaria.resoluciones'],
                ['sustentaciones', 'Sustentaciones', $p.'secretaria.sustentaciones'],
                ['actas', 'Actas y dictámenes', $p.'secretaria.actas'],
            ],
            default => [],
        };

        $menu = [];
        foreach ($items as [$clave, $etiqueta, $ruta]) {
            if (Route::has($ruta)) {
                $menu[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'url' => route($ruta), 'insignia' => (int) ($insignias[$clave] ?? 0)];
            }
        }

        return $menu;
    }
}
