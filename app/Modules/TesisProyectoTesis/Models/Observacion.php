<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Observacion extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.observacion';

    protected $primaryKey = 'id_observacion';

    public $timestamps = false;

    protected $fillable = [
        'id_revision',
        'descripcion',
        'estado',
        'subsanada_en',
        'id_criterio',
        'seccion_documento',
        'respuesta_estudiante',
        'id_entrega_correccion',
        'respondida_en',
    ];

    protected function casts(): array
    {
        return [
            'subsanada_en' => 'datetime',
            'respondida_en' => 'datetime',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'id_revision', 'id_revision');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'id_criterio', 'id_criterio');
    }

    public function entregaCorreccion(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'id_entrega_correccion', 'id_entrega');
    }
}
