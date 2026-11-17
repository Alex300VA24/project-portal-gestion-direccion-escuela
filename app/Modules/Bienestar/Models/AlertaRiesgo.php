<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AlertaRiesgo extends ModuleModel
{
    protected $table = 'bienestar.alerta_riesgo';

    protected $primaryKey = 'id_alerta';

    public $timestamps = true;

    protected $fillable = [
        'id_estudiante',
        'periodo',
        'curso',
        'regla',
        'origen',
        'prioridad',
        'estado',
        'id_caso',
        'id_docente',
        'motivo_reporte',
        'descripcion_reporte',
        'detalles',
        'fecha_deteccion',
        'fecha_resolucion',
        'atendido_por',
    ];

    protected function casts(): array
    {
        return [
            'descripcion_reporte' => 'encrypted',
            'detalles' => 'array',
            'fecha_deteccion' => 'datetime',
            'fecha_resolucion' => 'datetime',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'id_docente', 'id_docente');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoBienestar::class, 'id_caso', 'id_caso');
    }

    public function atendidoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendido_por', 'id_usuario');
    }

    public function scopeAutomaticas(Builder $query): Builder
    {
        return $query->where('origen', 'automatica');
    }

    public function scopeDocentes(Builder $query): Builder
    {
        return $query->where('origen', 'reporte_docente');
    }

    public function scopeManuales(Builder $query): Builder
    {
        return $query->where('origen', 'manual_bienestar');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->whereIn('estado', ['pendiente', 'en_atencion']);
    }

    public function scopePorPeriodo(Builder $query, string $periodo): Builder
    {
        return $query->where('periodo', $periodo);
    }
}
