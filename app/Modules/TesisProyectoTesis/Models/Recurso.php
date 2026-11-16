<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Recurso extends ModuleModel
{
    protected $table = 'tesis.recurso';

    protected $primaryKey = 'id_recurso';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'tipo',
        'descripcion',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaPresupuesto::class, 'id_recurso', 'id_recurso');
    }
}
