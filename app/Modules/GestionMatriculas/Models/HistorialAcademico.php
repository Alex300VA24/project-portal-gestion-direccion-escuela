<?php

namespace App\Modules\GestionMatriculas\Models;

use Illuminate\Database\Eloquent\Model;

final class HistorialAcademico extends Model
{
    protected $table = 'gestion_matriculas.v_historial_academico';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];
}
