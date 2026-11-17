<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SeguimientoDerivacion extends ModuleModel
{
    protected $table = 'bienestar.seguimiento_derivacion';

    protected $primaryKey = 'id_seguimiento';

    public $timestamps = false;

    protected $fillable = [
        'id_derivacion',
        'fecha',
        'descripcion',
        'registrado_por',
        'registrado_por_usuario',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'descripcion' => 'encrypted',
        ];
    }

    public function derivacion(): BelongsTo
    {
        return $this->belongsTo(Derivacion::class, 'id_derivacion', 'id_derivacion');
    }

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'registrado_por', 'id_especialista');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario', 'id_usuario');
    }
}
