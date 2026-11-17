<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encuesta extends ModuleModel
{
    protected $table = 'investigacion_formativa.encuesta';

    protected $primaryKey = 'id_encuesta';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_seccion',
        'estado',
        'creada_por',
        'publicada_en',
        'cerrada_en',
        'version',
    ];

    protected $casts = [
        'publicada_en' => 'datetime',
        'cerrada_en' => 'datetime',
        'version' => 'integer',
    ];

    public function preguntas(): HasMany
    {
        return $this->hasMany(EncuestaPregunta::class, 'id_encuesta', 'id_encuesta')
            ->orderBy('orden');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EncuestaRespuesta::class, 'id_encuesta', 'id_encuesta');
    }
}
