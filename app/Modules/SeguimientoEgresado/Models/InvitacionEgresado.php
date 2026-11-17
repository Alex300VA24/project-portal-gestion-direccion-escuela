<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class InvitacionEgresado extends ModuleModel
{
    protected $table = 'seguimiento_egresado.invitacion_egresado';

    public $timestamps = false;

    protected $fillable = [
        'id_campania',
        'id_egresado',
        'proposito',
        'token_hash',
        'expira_en',
        'id_canal_envio',
        'enviado_en',
        'recordatorios_enviados',
        'ultimo_recordatorio_en',
        'entrega_fallida',
        'respondido_en',
        'estado',
    ];

    public function campania()
    {
        return $this->belongsTo(CampaniaSeguimiento::class, 'id_campania');
    }

    public function egresado()
    {
        return $this->belongsTo(Egresado::class, 'id_egresado');
    }

    public function canalEnvio()
    {
        return $this->belongsTo(CatCanalEnvio::class, 'id_canal_envio');
    }

    public function medicion()
    {
        return $this->hasOne(MedicionLaboral::class, 'id_invitacion');
    }
}
