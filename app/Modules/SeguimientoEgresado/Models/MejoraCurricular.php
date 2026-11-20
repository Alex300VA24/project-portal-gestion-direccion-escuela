<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class MejoraCurricular extends ModuleModel
{
    protected $table = 'seguimiento_egresado.mejora_curricular';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'id_plan_curricular',
        'titulo',
        'tipo',
        'origen_seguimiento',
        'descripcion',
        'estado',
        'dice',
        'debe_decir',
        'fecha_registro',
        'fecha_implementacion',
        'periodo',
        'responsable',
        'meta_esperada',
        'origen_evidencia',
        'grado_cumplimiento',
        'documento_referencia',
        'id_evidencia',
        'id_usuario_registro',
    ];

    public function planCurricular()
    {
        return $this->belongsTo(PlanCurricular::class, 'id_plan_curricular');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario_registro');
    }
}
