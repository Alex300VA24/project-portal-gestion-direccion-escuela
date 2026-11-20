<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validación del formulario de contacto por HTTP: cada campo con un valor incorrecto se rechaza con su mensaje
 * en español. La validación ocurre antes de tocar la base de datos, por eso no necesita PostgreSQL.
 */
class ValidacionContactoWebTest extends TestCase
{
    private const URL = '/seguimiento-egresado/egresado/contacto';

    private function enviar(array $cambios)
    {
        $datos = array_merge(['correo_personal' => 'persona@correo.com', 'celular_whatsapp' => '+51 900 000 001', 'telefono_fijo' => '044 123456', 'direccion' => 'Av. Ficticia 123'], $cambios);

        return $this->withSession(['seguimiento-egresado.identidad' => [
            'perfil' => Perfil::Egresado->value, 'egresado_id' => 1, 'invitacion_id' => 1, 'vence_en' => now()->addDay()->toIso8601String(),
        ]])->from(self::URL)->post(self::URL, $datos);
    }

    #[DataProvider('valoresInvalidos')]
    public function test_cada_campo_invalido_se_rechaza_con_su_mensaje(array $cambios, string $campo, string $fragmento): void
    {
        $respuesta = $this->enviar($cambios);

        $respuesta->assertRedirect(self::URL)->assertSessionHasErrors($campo);
        $this->assertStringContainsString($fragmento, session('errors')->first($campo));
    }

    /** @return iterable<string, array{array<string, mixed>, string, string}> */
    public static function valoresInvalidos(): iterable
    {
        yield 'correo vacío' => [['correo_personal' => ''], 'correo_personal', 'obligatorio'];
        yield 'correo sin arroba' => [['correo_personal' => 'persona.correo.com'], 'correo_personal', 'correo personal'];
        yield 'correo sin terminación' => [['correo_personal' => 'persona@correo'], 'correo_personal', 'no es válido'];
        yield 'correo demasiado largo' => [['correo_personal' => str_repeat('a', 250).'@x.com'], 'correo_personal', 'no puede superar'];
        yield 'celular vacío' => [['celular_whatsapp' => ''], 'celular_whatsapp', 'obligatorio'];
        yield 'celular con letras' => [['celular_whatsapp' => '90000abcd'], 'celular_whatsapp', 'de 9 a 15 dígitos'];
        yield 'celular muy corto' => [['celular_whatsapp' => '12345'], 'celular_whatsapp', 'de 9 a 15 dígitos'];
        yield 'teléfono fijo con letras' => [['telefono_fijo' => 'abc123'], 'telefono_fijo', 'de 6 a 15 dígitos'];
        yield 'dirección demasiado corta' => [['direccion' => 'Av'], 'direccion', 'al menos 5'];
        yield 'dirección demasiado larga' => [['direccion' => str_repeat('a', 251)], 'direccion', 'no puede superar'];
        yield 'dirección con carácter de control' => [['direccion' => "Av. Real\x07 123"], 'direccion', 'texto válido'];
    }
}
