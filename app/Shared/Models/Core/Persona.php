<?php

namespace App\Shared\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Persona extends Model
{
    protected $table = 'core.persona';

    protected $primaryKey = 'id_persona';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'sexo',
        'fecha_nacimiento',
        'correo_personal',
        'telefono',
        'id_pais_nacionalidad',
        'id_distrito_domicilio',
        'direccion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'creado_en' => 'datetime',
        ];
    }

    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class, 'id_persona', 'id_persona');
    }
}
