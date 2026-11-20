<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class MedicionLaboral extends ModuleModel
{
    protected $table = 'seguimiento_egresado.medicion_laboral';

    public $timestamps = false;

    protected $fillable = [
        'id_invitacion',
        'fecha_respuesta',
        'id_situacion_laboral',
        'trabajo_relacionado',
        'id_modalidad_laboral',
        'id_modalidad_trabajo',
        'id_sector_laboral',
        'id_area_profesional',
        'id_pais_residencia',
        'ciudad_residencia',
        'nombre_empleador_cliente',
        'id_pais_empleador',
        'region_empleo',
        'nivel_ingles',
        'otros_idiomas',
        'autoriza_contacto_empresa',
        'contacto_jefe',
    ];

    public function invitacion()
    {
        return $this->belongsTo(InvitacionEgresado::class, 'id_invitacion');
    }

    public function situacionLaboral()
    {
        return $this->belongsTo(CatSituacionLaboral::class, 'id_situacion_laboral');
    }

    public function areaProfesional()
    {
        return $this->belongsTo(CatAreaProfesional::class, 'id_area_profesional');
    }

    public function sectorLaboral()
    {
        return $this->belongsTo(CatSectorLaboral::class, 'id_sector_laboral');
    }

    public function satisfaccion()
    {
        return $this->hasOne(SatisfaccionEgresado::class, 'id_medicion');
    }
}
