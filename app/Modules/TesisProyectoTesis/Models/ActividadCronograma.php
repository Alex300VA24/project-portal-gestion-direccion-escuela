<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActividadCronograma extends ModuleModel
{
    protected $table = 'tesis.actividad_cronograma';

    protected $primaryKey = 'id_actividad';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'orden',
        'horas_semanales',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'orden' => 'integer',
            'horas_semanales' => 'integer',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
}
