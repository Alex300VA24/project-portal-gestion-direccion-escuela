<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TipoSolicitudRequisito extends ModuleModel
{
    protected $table = 'bienestar.tipo_solicitud_requisito';

    protected $primaryKey = 'id_requisito';

    public $timestamps = true;

    protected $fillable = [
        'id_tipo_solicitud',
        'nombre',
        'descripcion',
        'es_obligatorio',
        'formatos_permitidos',
        'tamano_maximo_mb',
        'id_archivo_plantilla',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_obligatorio' => 'boolean',
            'tamano_maximo_mb' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function tipoSolicitud(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'id_tipo_solicitud', 'id_tipo_solicitud');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Archivo::class, 'id_archivo_plantilla', 'id_archivo');
    }
}
