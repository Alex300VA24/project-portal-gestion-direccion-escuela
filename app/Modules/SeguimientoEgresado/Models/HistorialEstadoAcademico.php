<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class HistorialEstadoAcademico extends ModuleModel
{
    protected $table = 'seguimiento_egresado.historial_estado_academico';

    public $timestamps = false;

    protected $fillable = [
        'id_egresado',
        'id_estado_academico',
        'apto_graduarse',
        'creditos_aprobados',
        'fecha_bachiller',
        'resolucion_bachiller',
        'fecha_titulo',
        'resolucion_titulo',
        'vigente_desde',
        'vigente_hasta',
        'id_usuario_registro',
    ];

    public function egresado()
    {
        return $this->belongsTo(Egresado::class, 'id_egresado');
    }

    public function estadoAcademico()
    {
        return $this->belongsTo(CatEstadoAcademico::class, 'id_estado_academico');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario_registro');
    }

    public function scopeVigente($query)
    {
        return $query->whereNull('vigente_hasta');
    }
}
