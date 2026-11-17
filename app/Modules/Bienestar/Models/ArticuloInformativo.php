<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\ModuleModel;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ArticuloInformativo extends ModuleModel
{
    protected $table = 'bienestar.articulo_informativo';

    protected $primaryKey = 'id_articulo';

    protected $fillable = [
        'titulo',
        'resumen',
        'detalle',
        'fuente_normativa',
        'fecha_ultima_revision',
        'estado',
        'autor_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ultima_revision' => 'date',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id', 'id_usuario');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado', 'publicado');
    }

    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', 'borrador');
    }
}
