<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Rubrica extends ModuleModel
{
    protected $table = 'tesis.rubrica';

    protected $primaryKey = 'id_rubrica';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'etapa',
        'version',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function criterios(): HasMany
    {
        return $this->hasMany(CriterioRubrica::class, 'id_rubrica', 'id_rubrica')->orderBy('orden');
    }
}
