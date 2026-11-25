<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;

final class Notificacion extends ModuleModel
{
    protected $table = 'comun.notificacion';

    protected $primaryKey = 'id_notificacion';

    public $timestamps = false;

    protected $fillable = [
        'id_modulo', 'id_usuario_destino', 'tipo_evento', 'titulo', 'mensaje',
        'referencia_tabla', 'referencia_id', 'canal', 'creada_en', 'enviada_en', 'leida_en',
    ];

    protected function casts(): array
    {
        return ['creada_en' => 'datetime', 'enviada_en' => 'datetime', 'leida_en' => 'datetime'];
    }
}
