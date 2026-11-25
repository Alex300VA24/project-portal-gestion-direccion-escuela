<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;

final class SolicitudHistorial extends ModuleModel
{
    protected $table = 'bienestar.solicitud_historial';

    protected $primaryKey = 'id_historial';

    public $timestamps = false;

    protected $fillable = ['id_solicitud', 'estado_anterior', 'estado_nuevo', 'cambiado_por', 'cambiado_en'];

    protected function casts(): array
    {
        return ['cambiado_en' => 'datetime'];
    }
}
