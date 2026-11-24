<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class Egresado extends ModuleModel
{
    protected $table = 'seguimiento_egresado.egresado';

    public $timestamps = false;

    protected $fillable = [
        'id_cohorte',
        'codigo_matricula',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'programa',
        'correo_institucional',
        'sede',
        'fecha_ingreso',
        'fecha_egreso',
        'periodo_egreso',
        'creado_en',
    ];

    public function cohorte()
    {
        return $this->belongsTo(Cohorte::class, 'id_cohorte');
    }

    public function contactos()
    {
        return $this->hasMany(ContactoEgresado::class, 'id_egresado');
    }

    public function contactoVigente()
    {
        return $this->hasOne(ContactoEgresado::class, 'id_egresado')->whereNull('vigente_hasta');
    }

    public function historialEstados()
    {
        return $this->hasMany(HistorialEstadoAcademico::class, 'id_egresado')->orderByDesc('vigente_desde');
    }

    public function estadoVigente()
    {
        return $this->hasOne(HistorialEstadoAcademico::class, 'id_egresado')->whereNull('vigente_hasta');
    }

    public function invitaciones()
    {
        return $this->hasMany(InvitacionEgresado::class, 'id_egresado');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    public function scopeBuscar($query, ?string $termino)
    {
        if (empty($termino)) {
            return $query;
        }

        $termino = strtolower(trim($termino));

        return $query->where(function ($q) use ($termino) {
            $q->whereRaw('LOWER(codigo_matricula) LIKE ?', ["%{$termino}%"])
                ->orWhereRaw('LOWER(numero_documento) LIKE ?', ["%{$termino}%"])
                ->orWhereRaw('LOWER(nombres) LIKE ?', ["%{$termino}%"])
                ->orWhereRaw('LOWER(apellido_paterno) LIKE ?', ["%{$termino}%"])
                ->orWhereRaw('LOWER(apellido_materno) LIKE ?', ["%{$termino}%"])
                ->orWhereRaw("LOWER(CONCAT(nombres, ' ', apellido_paterno, ' ', COALESCE(apellido_materno, ''))) LIKE ?", ["%{$termino}%"]);
        });
    }

    public function scopePorCohorte($query, $cohorteId)
    {
        if (! empty($cohorteId) && $cohorteId !== 'todas') {
            return $query->where('id_cohorte', $cohorteId);
        }

        return $query;
    }

    public function scopePorEstado($query, ?string $codigoEstado)
    {
        if (! empty($codigoEstado) && $codigoEstado !== 'todos') {
            return $query->whereHas('estadoVigente.estadoAcademico', function ($q) use ($codigoEstado) {
                $q->where('codigo', strtoupper($codigoEstado));
            });
        }

        return $query;
    }
}
