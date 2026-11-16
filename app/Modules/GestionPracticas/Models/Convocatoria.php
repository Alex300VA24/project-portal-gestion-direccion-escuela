<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Convocatoria extends ModuleModel
{
    public const BORRADOR = 'borrador';

    public const EN_REVISION = 'en_revision';

    public const OBSERVADA = 'observada';

    public const PUBLICADA = 'publicada';

    public const CERRADA = 'cerrada';

    public const ANULADA = 'anulada';

    protected $table = 'gestion_practicas.convocatoria';

    protected $primaryKey = 'id_convocatoria';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha_publicacion' => 'datetime',
            'fecha_cierre' => 'date',
            'registrada_en' => 'datetime',
            'enviada_en' => 'datetime',
            'vacantes' => 'integer',
        ];
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(ObservacionConvocatoria::class, 'id_convocatoria', 'id_convocatoria');
    }

    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class, 'id_convocatoria', 'id_convocatoria');
    }
}
