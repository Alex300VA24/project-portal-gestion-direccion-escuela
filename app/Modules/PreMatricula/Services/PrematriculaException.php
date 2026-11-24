<?php

namespace App\Modules\PreMatricula\Services;

use DomainException;

/** Regla de negocio de Pre Matrícula que impide completar una acción. */
final class PrematriculaException extends DomainException {}
