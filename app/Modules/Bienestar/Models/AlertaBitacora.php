<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AlertaBitacora extends ModuleModel
{
    protected $table = 'bienestar.alerta_bitacora';

    protected $primaryKey = 'id_bitacora';

    public $timestamps = false;

    protected $fillable = [
        'id_alerta',
        'id_usuario',
        'accion',
        'motivo',
        'creado_en',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'datetime',
        ];
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(AlertaRiesgo::class, 'id_alerta', 'id_alerta');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
