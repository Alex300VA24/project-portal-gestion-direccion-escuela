<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CriterioRubrica extends ModuleModel
{
    protected $table = 'tesis.criterio_rubrica';

    protected $primaryKey = 'id_criterio';

    public $timestamps = false;

    protected $fillable = [
        'id_rubrica',
        'descripcion',
        'puntaje_maximo',
        'orden',
        'seccion',
        'aplica_a',
        'estado',
        'permite_no_aplica',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'permite_no_aplica' => 'boolean',
        ];
    }

    public function rubrica(): BelongsTo
    {
        return $this->belongsTo(Rubrica::class, 'id_rubrica', 'id_rubrica');
    }
}
