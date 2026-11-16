<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Colaborador extends ModuleModel
{
    protected $table = 'tesis.colaborador';

    protected $primaryKey = 'id_colaborador';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_persona',
        'nombres_completos',
        'institucion',
        'rol_colaboracion',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}
