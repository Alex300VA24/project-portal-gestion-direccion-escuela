<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Solicitud extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.solicitud';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'id_tipo_solicitud',
        'presentada_por',
        'id_archivo',
        'estado',
        'destinatario',
        'destinatario_area',
        'asunto',
        'detalle',
        'id_docente_sugerido',
        'atendida_en',
    ];

    protected function casts(): array
    {
        return [
            'presentada_en' => 'datetime',
            'atendida_en' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'id_tipo_solicitud', 'id_tipo_solicitud');
    }

    public function resolucion(): HasOne
    {
        return $this->hasOne(Resolucion::class, 'id_solicitud', 'id_solicitud');
    }
}
