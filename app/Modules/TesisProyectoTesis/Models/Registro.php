<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;

/**
 * Modelo de referencia que usa la prueba compartida ModuleSchemaIsolationTest.
 * El dominio usa Proyecto (misma tabla).
 */
final class Registro extends ModuleModel
{
    protected $table = 'tesis.proyecto_tesis';

    protected $primaryKey = 'id_proyecto';

    public $timestamps = false;

    protected $fillable = [];
}
