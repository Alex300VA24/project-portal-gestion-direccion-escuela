<?php

namespace App\Modules\Bienestar\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Normaliza los filtros de la URL: un valor malformado se ignora en vez de provocar un error 500. */
final class FiltrosConsulta
{
    /** @return array<string, string> Solo los parámetros de texto (descarta arreglos). */
    public static function texto(Request $request): array
    {
        return array_filter($request->all(), 'is_string');
    }

    public static function fecha(Request $request, string $campo): ?string
    {
        $valor = self::texto($request)[$campo] ?? '';

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? $valor
            : null;
    }

    public static function periodo(Request $request): string
    {
        $valor = self::texto($request)['periodo'] ?? '';

        return preg_match('/^[A-Za-z0-9_-]{1,20}$/', $valor) ? $valor : self::periodoActivo();
    }

    public static function periodoActivo(): string
    {
        return (string) (DB::table('core.periodo_academico')
            ->where('estado', 'ACTIVO')
            ->orderByDesc('fecha_inicio')
            ->value('codigo') ?? '2026-I');
    }
}
