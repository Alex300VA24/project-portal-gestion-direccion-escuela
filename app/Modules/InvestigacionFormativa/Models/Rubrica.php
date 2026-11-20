<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubrica extends ModuleModel
{
    protected $table = 'investigacion_formativa.rubrica';

    protected $primaryKey = 'id_rubrica';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'id_docente_autor',
    ];

    public function criterios(): HasMany
    {
        return $this->hasMany(Criterio::class, 'id_rubrica', 'id_rubrica')->orderBy('orden');
    }
}
