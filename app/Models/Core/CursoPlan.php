<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CursoPlan extends Model
{
    protected $table = 'core.curso_plan';

    protected $primaryKey = 'id_curso_plan';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'ciclo',
        'creditos',
        'estado',
    ];

    protected function casts(): array
    {
        return ['creditos' => 'decimal:2'];
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'id_curso_plan', 'id_curso_plan');
    }
}
