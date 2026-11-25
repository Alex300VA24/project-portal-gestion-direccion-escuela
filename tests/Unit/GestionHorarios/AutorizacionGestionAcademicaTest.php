<?php

namespace Tests\Unit\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Services\AutorizacionGestionAcademica;
use RuntimeException;
use Tests\TestCase;

final class AutorizacionGestionAcademicaTest extends TestCase
{
    public function test_ausencia_denegacion_fallo_y_revocacion_cierra_el_acceso(): void
    {
        $usuario = new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }

            public function can($abilities, $arguments = []): bool
            {
                return true;
            }
        };
        $control = new AutorizacionGestionAcademica;
        self::assertFalse($control->permite($usuario));
        $permiso = new class implements ProveedorPermisoGestionAcademica
        {
            public bool $permitido = false;

            public function puedeGestionar(User $usuario): bool
            {
                return $this->permitido;
            }
        };
        app()->instance(ProveedorPermisoGestionAcademica::class, $permiso);
        self::assertFalse($control->permite($usuario));
        $permiso->permitido = true;
        self::assertTrue($control->permite($usuario));
        $permiso->permitido = false;
        self::assertFalse($control->permite($usuario));
        app()->instance(ProveedorPermisoGestionAcademica::class, new class implements ProveedorPermisoGestionAcademica
        {
            public function puedeGestionar(User $usuario): bool
            {
                throw new RuntimeException('Proveedor de prueba no disponible.');
            }
        });
        self::assertFalse($control->permite($usuario));
    }

    public function test_el_permiso_no_sustituye_la_autorizacion_de_consulta(): void
    {
        app()->instance(ProveedorPermisoGestionAcademica::class, new class implements ProveedorPermisoGestionAcademica
        {
            public function puedeGestionar(User $usuario): bool
            {
                return true;
            }
        });
        $usuario = new class extends User
        {
            public function hasAnyRole(...$roles): bool
            {
                return false;
            }

            public function can($abilities, $arguments = []): bool
            {
                return false;
            }
        };
        self::assertFalse((new AutorizacionGestionAcademica)->permite($usuario));
    }
}
