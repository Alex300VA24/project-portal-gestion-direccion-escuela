<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class PlanAccion extends ModuleModel
{
    protected $table = 'seguimiento_egresado.plan_accion';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'titulo',
        'tipo',
        'estado',
        'periodo',
        'actividad',
        'meta_esperada',
        'responsable',
        'origen_evidencia',
        'documento_referencia',
        'id_competencia',
        'id_objetivo',
        'grado_cumplimiento',
        'fecha_limite',
        'observacion',
        'id_evidencia',
        'id_usuario_registro',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'id_competencia');
    }

    public function objetivo()
    {
        return $this->belongsTo(ObjetivoEducacional::class, 'id_objetivo');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario_registro');
    }
}
