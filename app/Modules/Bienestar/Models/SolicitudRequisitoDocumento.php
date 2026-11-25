<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SolicitudRequisitoDocumento extends ModuleModel
{
    protected $table = 'bienestar.solicitud_requisito_documento';

    protected $primaryKey = 'id_solicitud_doc';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_requisito',
        'id_archivo',
        'estado_revision',
        'comentario_revision',
        'revisado_por',
        'revisado_en',
        'subido_en',
        'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'revisado_en' => 'datetime',
            'subido_en' => 'datetime',
            'actualizado_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudApoyo::class, 'id_solicitud', 'id_solicitud');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitudRequisito::class, 'id_requisito', 'id_requisito');
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class, 'id_archivo', 'id_archivo');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por', 'id_usuario');
    }
}
