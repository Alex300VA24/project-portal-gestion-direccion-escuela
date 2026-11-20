<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class ReporteGenerado extends ModuleModel
{
    protected $table = 'seguimiento_egresado.reporte_generado';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'codigo_formato',
        'periodo_inicio',
        'periodo_fin',
        'fecha_corte',
        'generado_en',
        'filtros',
        'ruta_archivo',
        'sha256_archivo',
        'id_usuario',
    ];

    protected $casts = [
        'filtros' => 'array',
        'fecha_corte' => 'datetime',
        'generado_en' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario');
    }

    public function indicadores()
    {
        return $this->hasMany(ReporteIndicador::class, 'id_reporte');
    }
}
