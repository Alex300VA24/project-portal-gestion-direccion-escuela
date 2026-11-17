<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CasoBienestar extends ModuleModel
{
    protected $table = 'bienestar.caso_bienestar';

    protected $primaryKey = 'id_caso';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_tipo_caso',
        'id_especialista_responsable',
        'id_solicitud_origen',
        'descripcion',
        'estado',
        'abierto_en',
        'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'descripcion' => 'encrypted',
            'abierto_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function tipoCaso(): BelongsTo
    {
        return $this->belongsTo(TipoCaso::class, 'id_tipo_caso', 'id_tipo_caso');
    }

    public function especialistaResponsable(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'id_especialista_responsable', 'id_especialista');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstadoCaso::class, 'id_caso', 'id_caso')->orderBy('cambiado_en', 'desc');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'id_caso', 'id_caso')->orderBy('inicio', 'asc');
    }

    public function derivaciones(): HasMany
    {
        return $this->hasMany(Derivacion::class, 'id_caso', 'id_caso')->orderBy('derivado_en', 'desc');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaRiesgo::class, 'id_caso', 'id_caso');
    }
}
