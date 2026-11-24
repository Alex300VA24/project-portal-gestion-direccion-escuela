<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class EvaluacionOe extends ModuleModel
{
    protected $table = 'seguimiento_egresado.evaluacion_oe';

    public $timestamps = false;

    protected $fillable = [
        'id_objetivo',
        'id_cohorte',
        'periodo',
        'id_nivel_logro',
        'estado',
        'meta_porcentaje',
        'logro_porcentaje',
        'observacion',
        'fuente_evidencia',
        'acciones_derivadas',
        'id_evidencia',
        'fecha_evaluacion',
        'id_usuario_registro',
    ];

    public function objetivo()
    {
        return $this->belongsTo(ObjetivoEducacional::class, 'id_objetivo');
    }

    public function cohorte()
    {
        return $this->belongsTo(Cohorte::class, 'id_cohorte');
    }

    public function nivelLogro()
    {
        return $this->belongsTo(CatNivelLogro::class, 'id_nivel_logro');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario_registro');
    }
}
