<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncuestaRespuestaItem extends ModuleModel
{
    protected $table = 'investigacion_formativa.encuesta_respuesta_item';

    public $incrementing = false;

    protected $primaryKey = null;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_respuesta',
        'id_pregunta',
        'id_encuesta',
        'valor',
    ];

    protected $casts = [
        'valor' => 'integer',
    ];

    public function respuesta(): BelongsTo
    {
        return $this->belongsTo(EncuestaRespuesta::class, 'id_respuesta', 'id_respuesta');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(EncuestaPregunta::class, 'id_pregunta', 'id_pregunta');
    }
}
