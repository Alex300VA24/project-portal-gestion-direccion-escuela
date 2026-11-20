<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncuestaPregunta extends ModuleModel
{
    protected $table = 'investigacion_formativa.encuesta_pregunta';

    protected $primaryKey = 'id_pregunta';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $fillable = [
        'id_encuesta',
        'texto',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function encuesta(): BelongsTo
    {
        return $this->belongsTo(Encuesta::class, 'id_encuesta', 'id_encuesta');
    }
}
