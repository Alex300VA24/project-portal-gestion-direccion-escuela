<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Especialidad extends ModuleModel
{
    protected $table = 'bienestar.especialidad';

    protected $primaryKey = 'id_especialidad';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function especialistas(): HasMany
    {
        return $this->hasMany(Especialista::class, 'id_especialidad', 'id_especialidad');
    }
}
