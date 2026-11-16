<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado aprobatorio / desaprobatorio de un miembro del jurado (sin nota). Clave compuesta (id_sustentacion, id_jurado).
 */
final class ResultadoJurado extends ModuleModel
{
    protected $table = 'tesis.resultado_jurado';

    protected $primaryKey = 'id_sustentacion';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_sustentacion',
        'id_jurado',
        'nota',
        'dictamen',
        'resultado',
        'propone_mencion',
        'registrado_en',
    ];

    protected function casts(): array
    {
        return [
            'propone_mencion' => 'boolean',
            'registrado_en' => 'datetime',
        ];
    }

    public function jurado(): BelongsTo
    {
        return $this->belongsTo(JuradoProyecto::class, 'id_jurado', 'id_jurado');
    }
}
