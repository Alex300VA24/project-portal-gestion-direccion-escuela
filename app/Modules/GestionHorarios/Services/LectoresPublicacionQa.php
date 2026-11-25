<?php

namespace App\Modules\GestionHorarios\Services;

/** Configuración explícita local, sin inferir identidades por nombre ni aceptar IDs del navegador. */
final class LectoresPublicacionQa
{
    public static function autorizado(string $correo, string $lista): bool
    {
        $correo = strtolower(trim($correo));
        return $correo !== '' && in_array($correo, array_map(fn($v) => strtolower(trim($v)), explode(';', $lista)), true);
    }

    public static function docente(string $correo, string $mapa): ?int
    {
        $correo = strtolower(trim($correo));
        if ($correo === '') return null;
        $encontrados = [];
        foreach (explode(';', $mapa) as $entrada) {
            $partes = explode('=', $entrada, 2);
            if (count($partes) !== 2 || strtolower(trim($partes[0])) !== $correo) continue;
            $id = filter_var(trim($partes[1]), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($id === false) return null;
            $encontrados[] = $id;
        }
        return count($encontrados) === 1 ? $encontrados[0] : null;
    }
}
