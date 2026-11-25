<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TipoSolicitud extends ModuleModel
{
    protected $table = 'bienestar.tipo_solicitud';

    protected $primaryKey = 'id_tipo_solicitud';

    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'descripcion',
        'unidad_responsable',
        'plazo_dias_habiles',
        'base_normativa_fuente',
        'estado_verificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'plazo_dias_habiles' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function requisitos(): HasMany
    {
        return $this->hasMany(TipoSolicitudRequisito::class, 'id_tipo_solicitud', 'id_tipo_solicitud')
            ->orderBy('orden', 'asc');
    }

    public function requisitosActivos(): HasMany
    {
        return $this->requisitos()->where('activo', true);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudApoyo::class, 'id_tipo_solicitud', 'id_tipo_solicitud');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
