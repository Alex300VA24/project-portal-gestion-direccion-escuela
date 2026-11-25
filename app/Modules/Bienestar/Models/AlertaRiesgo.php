<?php

namespace App\Modules\Bienestar\Models;

use App\Modules\Bienestar\Services\NotificacionService;
use App\Modules\Bienestar\Support\CategoriaMotivo;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'categoria_motivo',
        'descripcion_reporte',
        'detalles',
        'fecha_deteccion',
        'fecha_resolucion',
        'atendido_por',
        'filtrado_por',
        'fecha_filtro_secretaria',
    ];

    protected function casts(): array
    {
        return [
            'descripcion_reporte' => 'encrypted',
            'detalles' => 'array',
            'fecha_deteccion' => 'datetime',
            'fecha_resolucion' => 'datetime',
            'fecha_filtro_secretaria' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $alerta) {
            $alerta->categoria_motivo ??= CategoriaMotivo::porRegla((string) $alerta->regla);
        });
        // El docente sabe en qué estado va su reporte, nunca el motivo de la decisión
        self::updated(function (self $alerta) {
            if ($alerta->wasChanged('estado') && $alerta->id_docente) {
                app(NotificacionService::class)->reporteCambioEstado($alerta);
            }
        });
    }

    public function categoriaTexto(): string
    {
        return CategoriaMotivo::texto($this->categoria_motivo ?? CategoriaMotivo::porRegla((string) $this->regla));
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

    public function filtradoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'filtrado_por', 'id_usuario');
    }

    public function bitacoras(): HasMany
    {
        return $this->hasMany(AlertaBitacora::class, 'id_alerta', 'id_alerta')
            ->orderBy('creado_en', 'desc');
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

    public function scopeEnRevision(Builder $query): Builder
    {
        return $query->where('estado', 'en_revision');
    }

    public function scopeRemitidasBienestar(Builder $query): Builder
    {
        return $query->whereIn('estado', ['remitida_bienestar', 'en_atencion', 'atendida']);
    }

    public function scopeDescartadas(Builder $query): Builder
    {
        return $query->where('estado', 'descartada');
    }

    public function scopePorPeriodo(Builder $query, string $periodo): Builder
    {
        return $query->where('periodo', $periodo);
    }

    /**
     * Bienestar solo atiende lo que Secretaría le remitió y sigue abierto.
     */
    public function puedeAtenderse(): bool
    {
        return in_array($this->estado, ['remitida_bienestar', 'en_atencion'], true);
    }

    /**
     * Salvaguarda: Las alertas automáticas de riesgo alto (ej. tercera matrícula o prioridad alta)
     * no se pueden descartar por Secretaría. Deben pasarse a Bienestar obligatoriamente.
     */
    public function noPuedeDescartarse(): bool
    {
        if ($this->origen === 'automatica' && in_array(strtolower($this->prioridad), ['alta', 'urgente'], true)) {
            return true;
        }

        if ($this->origen === 'automatica' && str_contains(strtolower($this->regla), 'tercera')) {
            return true;
        }

        return false;
    }
}
