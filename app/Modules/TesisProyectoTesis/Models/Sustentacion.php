<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Sustentacion extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.sustentacion';

    protected $primaryKey = 'id_sustentacion';

    public $timestamps = false;

    protected $fillable = [
        'id_proyecto',
        'fecha_hora',
        'modalidad',
        'id_ambiente',
        'enlace_virtual',
        'id_resolucion',
        'estado',
        'hora_fin',
        'numero_intento',
        'total_aprobatorios',
        'resultado',
        'modo_resultado',
        'mencion_honrosa',
        'programada_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'numero_intento' => 'integer',
            'total_aprobatorios' => 'integer',
            'mencion_honrosa' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoJurado::class, 'id_sustentacion', 'id_sustentacion');
    }

    public function acta(): HasOne
    {
        return $this->hasOne(Acta::class, 'id_sustentacion', 'id_sustentacion');
    }
}
