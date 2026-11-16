<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Acta extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.acta';

    protected $primaryKey = 'id_acta';

    public $timestamps = false;

    protected $fillable = [
        'id_sustentacion',
        'numero',
        'nota_final',
        'calificacion',
        'id_archivo',
        'emitida_en',
        'tipo',
        'id_proyecto',
        'resultado',
        'condicion',
        'id_archivo_firmado',
        'registrado_por',
        'firmada_en',
        'informado_director_en',
    ];

    protected function casts(): array
    {
        return [
            'emitida_en' => 'datetime',
            'firmada_en' => 'datetime',
            'informado_director_en' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function sustentacion(): BelongsTo
    {
        return $this->belongsTo(Sustentacion::class, 'id_sustentacion', 'id_sustentacion');
    }
}
