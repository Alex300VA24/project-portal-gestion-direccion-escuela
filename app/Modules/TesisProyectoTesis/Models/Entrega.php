<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Entrega extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.entrega';

    protected $primaryKey = 'id_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'etapa',
        'version',
        'id_archivo',
        'tipo',
        'numero_semana',
        'comentario',
        'entregado_por',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'entregado_en' => 'datetime',
            'version' => 'integer',
            'numero_semana' => 'integer',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(Revision::class, 'id_entrega', 'id_entrega');
    }

    public function reporteSimilitud(): HasOne
    {
        return $this->hasOne(ReporteSimilitud::class, 'id_entrega', 'id_entrega');
    }
}
