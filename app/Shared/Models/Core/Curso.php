<?php

namespace App\Shared\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Curso extends Model
{
    protected $table = 'core.curso';

    protected $primaryKey = 'id_curso';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'creditos',
        'horas_teoria',
        'horas_practica',
        'horas_laboratorio',
        'sumilla',
        'estado',
    ];

    protected function casts(): array
    {
        return ['creditos' => 'decimal:2'];
    }

    public function planes(): HasMany
    {
        return $this->hasMany(CursoPlan::class, 'id_curso', 'id_curso');
    }
}
