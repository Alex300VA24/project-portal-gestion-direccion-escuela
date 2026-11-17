<?php

namespace App\Modules\SeguimientoEgresado\Support\Accesos;

/**
 * Capacidades del módulo por perfil, según la hoja «Permisos» del Sprint 3.
 * Es la única fuente de la matriz: el middleware, las Policies y el menú consultan aquí.
 * Ocultar una opción no restringe el acceso; la autorización se aplica en el servidor.
 */
final class Capacidades
{
    public const MATRIZ = [
        // Expediente y contacto
        'expediente.propio' => [Perfil::Egresado],
        'expediente.gestionar' => [Perfil::Secretaria],
        'expediente.consultar' => [Perfil::Director],

        // Padrón y cohortes
        'padron.consultar' => [Perfil::Secretaria, Perfil::Director],
        'padron.gestionar' => [Perfil::Secretaria],

        // Campañas e invitaciones
        'campanias.responder' => [Perfil::Egresado],
        'campanias.consultar' => [Perfil::Secretaria, Perfil::Director],
        'campanias.administrar' => [Perfil::Secretaria],

        // Seguimiento e historial
        'seguimiento.propio' => [Perfil::Egresado],
        'seguimiento.consultar' => [Perfil::Secretaria],
        'seguimiento.agregados' => [Perfil::Director],

        // Encuesta empresarial: la empresa responde con token y no pasa por aquí
        'empresas.resultados' => [Perfil::Secretaria, Perfil::Director],

        // Indicadores y reportes
        'indicadores.consultar' => [Perfil::Secretaria, Perfil::Director],
        'reportes.exportar' => [Perfil::Secretaria, Perfil::Director],

        // Objetivos educacionales, competencias, planes y mejoras
        'institucional.consultar' => [Perfil::Secretaria, Perfil::Director],
        'institucional.registrar' => [Perfil::Director],
        'institucional.validar' => [Perfil::Director],
    ];

    public static function existe(string $capacidad): bool
    {
        return array_key_exists($capacidad, self::MATRIZ);
    }

    /** Una capacidad desconocida se deniega siempre. */
    public static function permite(Perfil $perfil, string $capacidad): bool
    {
        return in_array($perfil, self::MATRIZ[$capacidad] ?? [], true);
    }

    /** @return list<string> */
    public static function de(Perfil $perfil): array
    {
        $capacidades = array_keys(array_filter(
            self::MATRIZ,
            fn (array $perfiles) => in_array($perfil, $perfiles, true),
        ));
        sort($capacidades);

        return $capacidades;
    }
}
