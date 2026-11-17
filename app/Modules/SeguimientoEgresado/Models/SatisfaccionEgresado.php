<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class SatisfaccionEgresado extends ModuleModel
{
    protected $table = 'seguimiento_egresado.satisfaccion_egresado';

    public $timestamps = false;

    protected $primaryKey = 'id_medicion';

    public $incrementing = false;

    protected $fillable = [
        'id_medicion',
        'id_nivel_satisfaccion',
        'comentario',
    ];

    public function medicion()
    {
        return $this->belongsTo(MedicionLaboral::class, 'id_medicion');
    }
}
