<?php

namespace Tests\Feature\SeguimientoEgresado\Seguimiento;

use App\Modules\SeguimientoEgresado\Requests\ContactoRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidacionContactoTest extends TestCase
{
    private function validar(array $datos): \Illuminate\Validation\Validator
    {
        $request = new ContactoRequest;

        return Validator::make($datos, $request->rules(), $request->messages(), $request->attributes());
    }

    private function valido(array $cambios = []): array
    {
        return array_merge(['correo_personal' => 'persona@correo.test', 'celular_whatsapp' => '+51 900 123 456'], $cambios);
    }

    public function test_correo_y_celular_son_suficientes(): void
    {
        $this->assertTrue($this->validar($this->valido())->passes());
    }

    public function test_telefono_fijo_y_direccion_son_opcionales_pero_validos_si_se_envian(): void
    {
        $this->assertTrue($this->validar($this->valido(['telefono_fijo' => '044 123456', 'direccion' => 'Av. Ficticia 123']))->passes());
    }

    public function test_correo_y_celular_son_obligatorios(): void
    {
        $errores = $this->validar([])->errors()->keys();

        $this->assertContains('correo_personal', $errores);
        $this->assertContains('celular_whatsapp', $errores);
    }

    public function test_formatos_invalidos_se_rechazan(): void
    {
        $errores = $this->validar($this->valido(['correo_personal' => 'no-es-correo', 'celular_whatsapp' => '123', 'telefono_fijo' => 'abc']))->errors()->keys();

        $this->assertEqualsCanonicalizing(['correo_personal', 'celular_whatsapp', 'telefono_fijo'], $errores);
    }

    public function test_se_respetan_los_largos_maximos_de_la_bd(): void
    {
        $largo = str_repeat('a', 251);

        $this->assertArrayHasKey('direccion', $this->validar($this->valido(['direccion' => $largo]))->errors()->toArray());
        $this->assertArrayHasKey('correo_personal', $this->validar($this->valido(['correo_personal' => str_repeat('a', 250).'@x.pe']))->errors()->toArray());
    }

    public function test_los_mensajes_estan_en_espanol(): void
    {
        $mensaje = $this->validar([])->errors()->first('correo_personal');

        $this->assertSame('El campo correo personal es obligatorio.', $mensaje);
    }
}
