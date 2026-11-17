<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class ImportacionPadron extends ModuleModel
{
    protected $table = 'seguimiento_egresado.importacion_padron';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'nombre_archivo',
        'hash_archivo',
        'origen',
        'fecha_importacion',
        'estado',
    ];

    public function usuario()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario');
    }

    public function filas()
    {
        return $this->hasMany(ImportacionFila::class, 'id_importacion')->orderBy('numero_fila');
    }

    public function getFilasAceptadasCountAttribute(): int
    {
        return $this->filas()->where('estado', 'ACEPTADA')->count();
    }

    public function getFilasRechazadasCountAttribute(): int
    {
        return $this->filas()->where('estado', 'RECHAZADA')->count();
    }
}
