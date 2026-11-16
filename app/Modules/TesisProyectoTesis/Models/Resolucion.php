<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Resolucion extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.resolucion';

    protected $primaryKey = 'id_resolucion';

    public $timestamps = false;

    protected $fillable = [
        'numero',
        'fecha',
        'asunto',
        'id_solicitud',
        'id_archivo',
        'organo_emisor',
        'dirigido_a',
        'detalle',
        'registrado_por',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'registrado_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud', 'id_solicitud');
    }
}
