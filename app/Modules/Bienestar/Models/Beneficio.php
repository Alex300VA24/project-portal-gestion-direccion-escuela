<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;

final class Beneficio extends ModuleModel
{
    protected $table = 'bienestar.beneficio';

    protected $primaryKey = 'id_beneficio';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
