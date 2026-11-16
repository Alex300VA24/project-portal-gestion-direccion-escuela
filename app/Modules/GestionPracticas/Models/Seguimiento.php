<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Seguimiento extends ModuleModel
{
    protected $table = 'gestion_practicas.seguimiento';

    protected $primaryKey = 'id_seguimiento';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['fecha' => 'date', 'registrado_en' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'id_practica', 'id_practica');
    }
}
