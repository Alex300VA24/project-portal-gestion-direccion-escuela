<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdentidadUsuario extends ModuleModel
{
    protected $table = 'investigacion_formativa.identidad_usuario';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'id_usuario_seguridad',
        'activo',
        'creado_en',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
    ];

    public function usuarioSeguridad(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_seguridad', 'id_usuario');
    }
}
