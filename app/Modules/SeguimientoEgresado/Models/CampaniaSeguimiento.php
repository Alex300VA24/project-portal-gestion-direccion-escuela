<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CampaniaSeguimiento extends ModuleModel
{
    protected $table = 'seguimiento_egresado.campania_seguimiento';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tipo',
        'finalidad',
        'horizonte_anios',
        'descripcion',
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'id_usuario_creacion',
    ];

    public function cohortes()
    {
        return $this->belongsToMany(Cohorte::class, 'seguimiento_egresado.campania_cohorte', 'id_campania', 'id_cohorte');
    }

    public function canales()
    {
        return $this->belongsToMany(CatCanalEnvio::class, 'seguimiento_egresado.campania_canal', 'id_campania', 'id_canal');
    }

    public function invitaciones()
    {
        return $this->hasMany(InvitacionEgresado::class, 'id_campania');
    }

    public function usuarioCreacion()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario_creacion');
    }
}
