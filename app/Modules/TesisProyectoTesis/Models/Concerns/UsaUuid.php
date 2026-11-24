<?php

namespace App\Modules\TesisProyectoTesis\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Las URLs del módulo solo exponen el uuid público, nunca el id numérico.
 */
trait UsaUuid
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (! is_string($value) || ! self::esUuid($value)) {
            return null;
        }

        return $this->newQuery()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public static function esUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
