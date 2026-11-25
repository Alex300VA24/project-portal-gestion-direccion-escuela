<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Cita extends ModuleModel
{
    protected $table = 'bienestar.cita';

    protected $primaryKey = 'id_cita';

    public $timestamps = false;

    protected $fillable = [
        'id_caso',
        'id_especialista',
        'inicio',
        'fin',
        'modalidad',
        'estado',
        'notas_atencion',
        'id_usuario_atencion',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'notas_atencion' => 'encrypted',
        ];
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(CasoBienestar::class, 'id_caso', 'id_caso');
    }

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(Especialista::class, 'id_especialista', 'id_especialista');
    }

    public function usuarioAtencion(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_atencion', 'id_usuario');
    }
}
