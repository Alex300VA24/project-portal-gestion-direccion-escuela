<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Revision extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.revision';

    protected $primaryKey = 'id_revision';

    public $timestamps = false;

    protected $fillable = [
        'id_entrega',
        'id_docente',
        'id_rubrica',
        'dictamen',
        'comentario',
        'revisado_en',
        'tipo',
        'rol_revisor',
        'cumple_todo',
        'conforme',
        'referencial',
    ];

    protected function casts(): array
    {
        return [
            'revisado_en' => 'datetime',
            'cumple_todo' => 'boolean',
            'conforme' => 'boolean',
            'referencial' => 'boolean',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'id_entrega', 'id_entrega');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class, 'id_revision', 'id_revision');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleEvaluacion::class, 'id_revision', 'id_revision');
    }
}
