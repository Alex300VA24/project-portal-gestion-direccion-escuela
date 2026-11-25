<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;

final class Archivo extends ModuleModel
{
    protected $table = 'comun.archivo';

    protected $primaryKey = 'id_archivo';

    public $timestamps = false;

    protected $fillable = [
        'id_modulo',
        'nombre_original',
        'ruta_almacenamiento',
        'tipo_mime',
        'tamano_bytes',
        'hash_sha256',
        'subido_por',
        'subido_en',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'subido_en' => 'datetime',
        ];
    }
}
