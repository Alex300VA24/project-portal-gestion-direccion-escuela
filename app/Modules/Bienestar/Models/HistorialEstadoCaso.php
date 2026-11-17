<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HistorialEstadoCaso extends ModuleModel
{
    protected $table = 'bienestar.historial_estado_caso';

    protected $primaryKey = 'id_historial';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'estado_anterior',
        'estado_nuevo',
        'cambiado_por',
        'cambiado_en',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cambiado_en' => 'datetime',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoBienestar::class, 'id_caso', 'id_caso');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cambiado_por', 'id_usuario');
    }
}
