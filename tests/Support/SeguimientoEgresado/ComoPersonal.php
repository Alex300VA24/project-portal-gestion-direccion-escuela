<?php

namespace Tests\Support\SeguimientoEgresado;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Usuarios del portal con el rol de Secretaría o de Dirección, como los crea DatabaseSeeder del portal
 * (`secretaria` y `director_escuela`). Los roles y usuarios se crean dentro de la transacción de la prueba.
 */
trait ComoPersonal
{
    protected function secretaria(): User
    {
        return $this->personal('secretaria');
    }

    protected function director(): User
    {
        return $this->personal('director_escuela');
    }

    /** Usuario del portal sin rol en el módulo (por ejemplo, un profesor). */
    protected function profesor(): User
    {
        return $this->personal('profesor');
    }

    private function personal(string $rol): User
    {
        Role::findOrCreate($rol, 'web');

        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    }
}
