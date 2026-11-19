<?php

namespace App\Modules\GestionPracticas\Support;

use App\Models\User;

/** Opciones de navegación del módulo: la barra lateral y el panel de inicio salen de esta misma lista. */
final class Menu
{
    /**
     * Opciones a las que el usuario tiene acceso, según sus permisos.
     *
     * @return list<array{ruta: string, texto: string, ayuda: string, patron: string, icono: string}>
     */
    public static function para(User $user): array
    {
        return array_values(array_filter(
            self::todas(),
            static fn (array $opcion): bool => $user->can($opcion['permiso']),
        ));
    }

    /** @return list<array{ruta: string, texto: string, ayuda: string, permiso: string, patron: string, icono: string}> */
    private static function todas(): array
    {
        return [
            [
                'ruta' => 'gestion-practicas.panel', 'texto' => 'Inicio', 'permiso' => 'gestion-practicas.view',
                'ayuda' => 'Resumen de su actividad en el módulo.', 'patron' => 'gestion-practicas.panel',
                'icono' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
            ],
            [
                'ruta' => 'gestion-practicas.empresas.index', 'texto' => 'Empresas', 'permiso' => Acceso::EMPRESAS,
                'ayuda' => 'Registre empresas y administre sus cuentas de acceso.', 'patron' => 'gestion-practicas.empresas.*',
                'icono' => '<path d="M4 21V5l8-3v19"/><path d="M12 8h8v13"/><path d="M8 7v.01M8 11v.01M8 15v.01M16 12v.01M16 16v.01M2 21h20"/>',
            ],
            [
                'ruta' => 'gestion-practicas.revision.convocatorias.index', 'texto' => 'Revisar convocatorias', 'permiso' => Acceso::CONVOCATORIAS_REVISAR,
                'ayuda' => 'Apruebe u observe las convocatorias que envían las empresas.', 'patron' => 'gestion-practicas.revision.convocatorias.*',
                'icono' => '<path d="M9 3h6l1 2h3v16H5V5h3z"/><path d="m9 13 2 2 4-4"/>',
            ],
            [
                'ruta' => 'gestion-practicas.revision.practicas.index', 'texto' => 'Validar prácticas', 'permiso' => Acceso::PRACTICAS_VALIDAR,
                'ayuda' => 'Valide la información de cada práctica y asigne al docente supervisor.', 'patron' => 'gestion-practicas.revision.practicas.*',
                'icono' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            ],
            [
                'ruta' => 'gestion-practicas.empresa.perfil.edit', 'texto' => 'Perfil de la empresa', 'permiso' => Acceso::CONVOCATORIAS_GESTIONAR,
                'ayuda' => 'Mantenga actualizados los datos de contacto de su empresa.', 'patron' => 'gestion-practicas.empresa.perfil.*',
                'icono' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
            ],
            [
                'ruta' => 'gestion-practicas.empresa.convocatorias.index', 'texto' => 'Mis convocatorias', 'permiso' => Acceso::CONVOCATORIAS_GESTIONAR,
                'ayuda' => 'Cree convocatorias, envíelas a revisión y corrija las observaciones.', 'patron' => 'gestion-practicas.empresa.convocatorias.*',
                'icono' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/>',
            ],
            [
                'ruta' => 'gestion-practicas.estudiante.convocatorias.index', 'texto' => 'Convocatorias', 'permiso' => Acceso::CONVOCATORIAS_VER,
                'ayuda' => 'Revise las convocatorias vigentes y registre su postulación.', 'patron' => 'gestion-practicas.estudiante.convocatorias.*',
                'icono' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            ],
            [
                'ruta' => 'gestion-practicas.estudiante.postulaciones.index', 'texto' => 'Mis postulaciones', 'permiso' => Acceso::POSTULAR,
                'ayuda' => 'Informe si fue seleccionado y continúe con el registro de su práctica.', 'patron' => 'gestion-practicas.estudiante.postulaciones.*',
                'icono' => '<path d="M4 12 20 4l-6 16-3-7z"/><path d="m11 13 9-9"/>',
            ],
            [
                'ruta' => 'gestion-practicas.estudiante.practicas.index', 'texto' => 'Mis prácticas', 'permiso' => Acceso::PRACTICAS_REGISTRAR,
                'ayuda' => 'Registre su práctica, suba documentos y consulte el seguimiento.', 'patron' => 'gestion-practicas.estudiante.practicas.*',
                'icono' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 13h18"/>',
            ],
            [
                'ruta' => 'gestion-practicas.supervisor.practicas.index', 'texto' => 'Prácticas asignadas', 'permiso' => Acceso::PRACTICAS_SUPERVISAR,
                'ayuda' => 'Haga el seguimiento, evalúe y cierre las prácticas que supervisa.', 'patron' => 'gestion-practicas.supervisor.practicas.*',
                'icono' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.6 3-6 7-6s7 2.4 7 6"/><path d="M17 5a3.5 3.5 0 0 1 0 6.5M18 14c2.4.7 4 2.6 4 6"/>',
            ],
        ];
    }
}
