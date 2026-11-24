<?php

namespace App\Modules\SeguimientoEgresado\Services\Egresado;

enum MotivoInvitacion: string
{
    case NoEncontrada = 'no_encontrada';
    case YaRespondida = 'ya_respondida';
    case Anulada = 'anulada';
    case Expirada = 'expirada';
    case Vencida = 'vencida';
    case CampaniaCerrada = 'campania_cerrada';
}
