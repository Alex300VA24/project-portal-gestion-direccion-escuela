<?php

namespace App\Modules\SeguimientoEgresado\Services\Egresado;

enum ResultadoContacto: string
{
    case Creado = 'creado';
    case Actualizado = 'actualizado';
    case SinCambios = 'sin_cambios';
}
