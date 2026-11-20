<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Grupo extends ModuleModel
{
    protected $table = 'investigacion_formativa.grupo';

    protected $primaryKey = 'id_grupo';

    public $timestamps = false;

    protected $fillable = [
        'id_seccion',
        'nombre',
    ];

    public function proyecto(): HasOne
    {
        return $this->hasOne(Proyecto::class, 'id_grupo', 'id_grupo');
    }
}
