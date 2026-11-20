<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class ContactoEgresado extends ModuleModel
{
    protected $table = 'seguimiento_egresado.contacto_egresado';

    public $timestamps = false;

    protected $fillable = [
        'id_egresado',
        'correo_personal',
        'celular_whatsapp',
        'telefono_fijo',
        'direccion',
        'vigente_desde',
        'vigente_hasta',
        'origen_registro',
    ];

    public function egresado()
    {
        return $this->belongsTo(Egresado::class, 'id_egresado');
    }

    public function scopeVigente($query)
    {
        return $query->whereNull('vigente_hasta');
    }
}
