<?php

namespace App\Shared\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CursoPlan extends Model
{
    protected $table = 'core.curso_plan';

    protected $primaryKey = 'id_curso_plan';

    public $timestamps = false;

    protected $fillable = [
        'id_plan',
        'id_curso',
        'ciclo',
        'tipo',
        'id_area',
        'creditos_requeridos',
    ];

    protected function casts(): array
    {
        return ['creditos_requeridos' => 'decimal:2'];
    }

    public function planEstudios(): BelongsTo
    {
        return $this->belongsTo(PlanEstudios::class, 'id_plan', 'id_plan');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'id_curso', 'id_curso');
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'id_curso_plan', 'id_curso_plan');
    }
}
