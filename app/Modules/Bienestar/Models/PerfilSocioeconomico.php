<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PerfilSocioeconomico extends ModuleModel
{
    protected $table = 'bienestar.perfil_socioeconomico';

    protected $primaryKey = 'id_perfil';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'clasificacion_sisfoh',
        'quintil_ingreso',
        'evaluado_por',
        'evaluado_en',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'evaluado_en' => 'date',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }
}
