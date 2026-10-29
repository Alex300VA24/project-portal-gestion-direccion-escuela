<?php

namespace App\Models\Seguridad;

use App\Models\Core\Persona;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class Usuario extends Authenticatable
{
    protected $table = 'seguridad.usuario';

    protected $primaryKey = 'id_usuario';

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_persona',
        'correo_institucional',
        'contrasena_hash',
        'debe_cambiar_contrasena',
        'ultimo_acceso',
        'estado',
    ];

    protected $hidden = [
        'contrasena_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'debe_cambiar_contrasena' => 'boolean',
            'ultimo_acceso' => 'datetime',
            'creado_en' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'contrasena_hash';
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }
}
