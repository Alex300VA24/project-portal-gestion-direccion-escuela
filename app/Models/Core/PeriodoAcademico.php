<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PeriodoAcademico extends Model
{
    protected $table = 'core.periodo_academico';

    protected $primaryKey = 'id_periodo';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'id_periodo', 'id_periodo');
    }
}
