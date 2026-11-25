<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Derivacion extends ModuleModel
{
    protected $table = 'bienestar.derivacion';

    protected $primaryKey = 'id_derivacion';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_especialista_destino',
        'entidad_externa',
        'motivo',
        'estado',
        'derivado_en',
        'consentimiento_estudiante',
        'fecha_consentimiento',
        'resultado_especialista',
    ];

    protected function casts(): array
    {
        return [
            'derivado_en' => 'datetime',
            'consentimiento_estudiante' => 'boolean',
            'fecha_consentimiento' => 'datetime',
            'resultado_especialista' => 'encrypted',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoBienestar::class, 'id_caso', 'id_caso');
    }

    public function especialistaDestino(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'id_especialista_destino', 'id_especialista');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoDerivacion::class, 'id_derivacion', 'id_derivacion')->orderBy('fecha', 'desc');
    }

    public function getDestinoTextoAttribute(): string
    {
        if ($this->especialistaDestino) {
            $esp = $this->especialistaDestino;
            $nombre = $esp->nombre_completo;
            $especialidad = $esp->especialidad?->nombre ?? 'Especialista';
            $inst = $esp->institucion ? " ({$esp->institucion})" : '';

            return "{$especialidad}: {$nombre}{$inst}";
        }

        return $this->entidad_externa ?? 'Entidad Externa';
    }
}
