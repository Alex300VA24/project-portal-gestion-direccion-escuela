<?php

namespace App\Models\Seguridad;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class Usuario extends Authenticatable
{
    protected $table = 'seguridad.usuario';

    protected $primaryKey = 'id_usuario';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'correo_institucional',
        'contrasena_hash',
        'estado',
    ];

    protected $hidden = [
        'contrasena_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return ['creado_en' => 'datetime'];
    }

    public function getAuthPasswordName(): string
    {
        return 'contrasena_hash';
    }
}
