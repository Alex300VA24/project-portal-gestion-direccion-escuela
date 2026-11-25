<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Especialista extends ModuleModel
{
    protected $table = 'bienestar.especialista';

    protected $primaryKey = 'id_especialista';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_especialidad',
        'registro_profesional',
        'activo',
        'nombres',
        'apellidos',
        'contacto',
        'institucion',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad', 'id_especialidad');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function derivacionesDestino(): HasMany
    {
        return $this->hasMany(Derivacion::class, 'id_especialista_destino', 'id_especialista');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function getNombreCompletoAttribute(): string
    {
        if (! empty($this->nombres) || ! empty($this->apellidos)) {
            return trim("{$this->nombres} {$this->apellidos}");
        }

        if ($this->usuario && $this->usuario->persona) {
            return trim("{$this->usuario->persona->nombres} {$this->usuario->persona->apellidos}");
        }

        return "Especialista #{$this->id_especialista}";
    }
}
