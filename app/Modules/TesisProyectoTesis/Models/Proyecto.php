<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Modules\TesisProyectoTesis\Models\Concerns\UsaUuid;
use App\Modules\TesisProyectoTesis\Policies\ProyectoPolicy;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[UsePolicy(ProyectoPolicy::class)]
final class Proyecto extends ModuleModel
{
    use UsaUuid;

    protected $table = 'tesis.proyecto_tesis';

    protected $primaryKey = 'id_proyecto';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'titulo',
        'resumen',
        'id_linea',
        'id_periodo_registro',
        'etapa',
        'estado',
        'enfoque',
        'tipo_investigacion',
        'diseno',
        'lugar_ejecucion',
        'institucion',
        'duracion_meses',
        'financiamiento',
        'fecha_aprobacion_proyecto',
        'fecha_aprobacion_tesis',
        'id_periodo_tesis',
        'id_archivo_documento',
    ];

    protected function casts(): array
    {
        return [
            'registrado_en' => 'datetime',
            'fecha_aprobacion_proyecto' => 'date',
            'fecha_aprobacion_tesis' => 'date',
            'duracion_meses' => 'integer',
        ];
    }

    public function integrantes(): HasMany
    {
        return $this->hasMany(ProyectoEstudiante::class, 'id_proyecto', 'id_proyecto')->orderBy('orden_autor');
    }

    public function integrantesActivos(): HasMany
    {
        return $this->integrantes()->where('estado', 'Activo');
    }

    public function asesores(): HasMany
    {
        return $this->hasMany(AsesorProyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function asesorActivo(): HasOne
    {
        return $this->hasOne(AsesorProyecto::class, 'id_proyecto', 'id_proyecto')->where('estado', 'Activo');
    }

    public function jurados(): HasMany
    {
        return $this->hasMany(JuradoProyecto::class, 'id_proyecto', 'id_proyecto');
    }

    public function juradosActivos(): HasMany
    {
        return $this->jurados()->where('estado', 'Activo');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'id_proyecto', 'id_proyecto');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'id_proyecto', 'id_proyecto');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadCronograma::class, 'id_proyecto', 'id_proyecto')->orderBy('orden');
    }

    public function recursos(): HasMany
    {
        return $this->hasMany(Recurso::class, 'id_proyecto', 'id_proyecto');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaPresupuesto::class, 'id_proyecto', 'id_proyecto');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'id_proyecto', 'id_proyecto');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstado::class, 'id_proyecto', 'id_proyecto')
            ->orderBy('cambiado_en')
            ->orderBy('id_historial');
    }

    public function sustentaciones(): HasMany
    {
        return $this->hasMany(Sustentacion::class, 'id_proyecto', 'id_proyecto')->orderBy('numero_intento');
    }

    public function actas(): HasMany
    {
        return $this->hasMany(Acta::class, 'id_proyecto', 'id_proyecto');
    }

    public function esActivo(): bool
    {
        return ! in_array($this->estado, Estados::FINALES, true);
    }

    public function enEtapaTesis(): bool
    {
        return $this->etapa === Estados::ETAPA_TESIS;
    }
}
