<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EncuestaRespuesta extends ModuleModel
{
    protected $table = 'investigacion_formativa.encuesta_respuesta';

    protected $primaryKey = 'id_respuesta';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_encuesta',
        'id_estudiante',
        'comentario',
        'respondida_en',
    ];

    protected $casts = [
        'respondida_en' => 'datetime',
    ];

    public function encuesta(): BelongsTo
    {
        return $this->belongsTo(Encuesta::class, 'id_encuesta', 'id_encuesta');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EncuestaRespuestaItem::class, 'id_respuesta', 'id_respuesta');
    }
}
