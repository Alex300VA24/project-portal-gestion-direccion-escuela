<?php

namespace App\Modules\InvestigacionFormativa\Models;

use App\Models\User;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevisionTitulo extends ModuleModel
{
    protected $table = 'investigacion_formativa.revision_titulo';

    protected $primaryKey = 'id_revision';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'titulo',
        'estado',
        'motivo',
        'user_id',
        'registrado_en',
    ];

    protected $casts = [
        'registrado_en' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
