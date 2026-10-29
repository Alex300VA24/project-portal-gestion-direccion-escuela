<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

final class Estudiante extends Model
{
    protected $table = 'core.estudiante';

    protected $primaryKey = 'id_estudiante';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'codigo_estudiante',
        'estado',
    ];

    protected function casts(): array
    {
        return ['creado_en' => 'datetime'];
    }
}
