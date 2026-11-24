<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class EvaluacionCompetencia extends ModuleModel
{
    protected $table = 'seguimiento_egresado.evaluacion_competencia';

    public $timestamps = false;

    protected $fillable = [
        'id_competencia',
        'id_cohorte',
        'periodo',
        'id_nivel_logro',
        'estado',
        'logro_porcentaje',
        'observacion',
        'fuente_evidencia',
        'id_evidencia',
        'fecha_evaluacion',
        'id_usuario_registro',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'id_competencia');
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
