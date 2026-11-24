<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class ReporteIndicador extends ModuleModel
{
    protected $table = 'seguimiento_egresado.reporte_indicador';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = ['id_reporte', 'codigo_indicador'];

    protected $fillable = [
        'id_reporte',
        'codigo_indicador',
        'numerador',
        'denominador',
        'total_elegibles',
        'total_respondieron',
        'resultado',
    ];

    public function reporte()
    {
        return $this->belongsTo(ReporteGenerado::class, 'id_reporte');
    }
}
