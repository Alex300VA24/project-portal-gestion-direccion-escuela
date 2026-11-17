<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SolicitudApoyo extends ModuleModel
{
    protected $table = 'bienestar.solicitud_apoyo';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_beneficio',
        'motivo',
        'estado',
        'evaluado_por',
        'solicitada_en',
        'resuelta_en',
    ];

    protected function casts(): array
    {
        return [
            'solicitada_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(Beneficio::class, 'id_beneficio', 'id_beneficio');
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'evaluado_por', 'id_especialista');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoBienestar::class, 'id_solicitud', 'id_solicitud_origen');
    }
}
