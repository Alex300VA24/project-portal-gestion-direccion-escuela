<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends ModuleModel
{
    protected $table = 'investigacion_formativa.proyecto';

    protected $primaryKey = 'id_proyecto';

    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'id_linea',
        'id_tipo_producto',
        'titulo',
        'resumen',
        'estado',
        'version',
        'registrado_en',
    ];

    protected $casts = [
        'version' => 'integer',
        'registrado_en' => 'datetime',
    ];

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionTitulo::class, 'id_proyecto', 'id_proyecto');
    }
}
