<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TipoCaso extends ModuleModel
{
    protected $table = 'bienestar.tipo_caso';

    protected $primaryKey = 'id_tipo_caso';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function casos(): HasMany
    {
        return $this->hasMany(CasoBienestar::class, 'id_tipo_caso', 'id_tipo_caso');
    }
}
