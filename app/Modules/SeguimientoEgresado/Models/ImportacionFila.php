<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class ImportacionFila extends ModuleModel
{
    protected $table = 'seguimiento_egresado.importacion_fila';

    public $timestamps = false;

    protected $fillable = [
        'id_importacion',
        'numero_fila',
        'datos_entrada',
        'estado',
        'detalle_error',
        'id_egresado',
    ];

    protected $casts = [
        'datos_entrada' => 'array',
    ];

    public function importacion()
    {
        return $this->belongsTo(ImportacionPadron::class, 'id_importacion');
    }

    public function egresado()
    {
        return $this->belongsTo(Egresado::class, 'id_egresado');
    }
}
