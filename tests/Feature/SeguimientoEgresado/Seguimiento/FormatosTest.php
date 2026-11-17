<?php

namespace Tests\Feature\SeguimientoEgresado\Seguimiento;

use App\Modules\SeguimientoEgresado\Requests\Formatos;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Reglas de formato compartidas por los formularios del egresado (sin base de datos). */
class FormatosTest extends TestCase
{
    private function pasa(mixed $regla, mixed $valor): bool
    {
        return Validator::make(['campo' => $valor], ['campo' => $regla])->passes();
    }

    #[DataProvider('celulares')]
    public function test_celular(mixed $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::celular()], $valor), (string) $valor);
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function celulares(): iterable
    {
        yield 'nacional' => ['900000001', true];
        yield 'con código de país y espacios' => ['+51 900 000 001', true];
        yield 'internacional largo' => ['+1 202 555 0143', true];
        yield 'muy corto' => ['12345678', false];
        yield 'demasiado largo' => ['1234567890123456', false];
        yield 'con letras' => ['9000abc01', false];
        yield 'más en medio' => ['900+000001', false];
        yield 'con guiones' => ['900-000-001', false];
        yield 'símbolos' => ['<script>', false];
    }

    #[DataProvider('telefonosFijos')]
    public function test_telefono_fijo(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::telefonoFijo()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function telefonosFijos(): iterable
    {
        yield 'con código de ciudad' => ['044 123456', true];
        yield 'con paréntesis y guion' => ['(044) 123-456', true];
        yield 'muy corto' => ['12345', false];
        yield 'con letras' => ['044 abc123', false];
        yield 'demasiados dígitos' => ['1234567890123456', false];
    }

    #[DataProvider('correos')]
    public function test_correo(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', ...Formatos::correo()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function correos(): iterable
    {
        yield 'normal' => ['persona@correo.com', true];
        yield 'con punto y número' => ['nombre.apellido2@unitru.edu.pe', true];
        yield 'sin arroba' => ['personacorreo.com', false];
        yield 'sin terminación' => ['persona@correo', false];
        yield 'con espacios' => ['per sona@correo.com', false];
        yield 'dominio con punto final' => ['persona@correo.', false];
    }

    #[DataProvider('contactosDelJefe')]
    public function test_correo_o_celular_del_jefe(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', Formatos::correoOCelular()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function contactosDelJefe(): iterable
    {
        yield 'correo' => ['jefe@empresa.com', true];
        yield 'celular' => ['+51 900 000 001', true];
        yield 'texto libre' => ['mi jefe', false];
        yield 'correo sin terminación' => ['jefe@empresa', false];
    }

    #[DataProvider('textos')]
    public function test_texto_libre(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::texto()], $valor), json_encode($valor));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function textos(): iterable
    {
        yield 'normal con tilde y eñe' => ['Gestión de peñas S.A.C.', true];
        yield 'con números' => ['Empresa 2000', true];
        yield 'con salto de línea' => ["Primera línea\nSegunda línea", true];
        yield 'solo símbolos' => ['!!!???', false];
        yield 'con carácter de control' => ["Texto\x07roto", false];
        yield 'con byte nulo' => ["Texto\0roto", false];
    }

    #[DataProvider('lugares')]
    public function test_ciudad_o_lugar(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::lugar()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function lugares(): iterable
    {
        yield 'simple' => ['Trujillo', true];
        yield 'con tilde y espacio' => ['San José de Chiclayo', true];
        yield 'con paréntesis' => ['Lima (Perú)', true];
        yield 'con números' => ['<script>alert(1)</script>', false];
        yield 'empieza con símbolo' => ['-Trujillo', false];
        yield 'con arroba' => ['lima@peru', false];
    }

    #[DataProvider('claves')]
    public function test_clave_con_letras_y_numeros(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::clave()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function claves(): iterable
    {
        yield 'letras y números' => ['Una-clave-nueva-123', true];
        yield 'solo letras' => ['sololetrasaqui', false];
        yield 'solo números' => ['1234567890123', false];
    }

    #[DataProvider('matriculas')]
    public function test_matricula(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::reglaMatricula()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function matriculas(): iterable
    {
        yield 'numérica' => ['2019000001', true];
        yield 'alfanumérica con guion' => ['MAT-2019-001', true];
        yield 'muy corta' => ['201', false];
        yield 'con espacios' => ['2019 000001', false];
        yield 'con símbolos peligrosos' => ["2019'; DROP TABLE egresado;--", false];
    }

    #[DataProvider('documentos')]
    public function test_documento(string $valor, bool $valido): void
    {
        $this->assertSame($valido, $this->pasa(['required', 'string', Formatos::reglaDocumento()], $valor), $valor);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function documentos(): iterable
    {
        yield 'DNI' => ['70000001', true];
        yield 'pasaporte' => ['AB123456', true];
        yield 'carnet de extranjería con guion' => ['001-234567', true];
        yield 'muy corto' => ['1234', false];
        yield 'con símbolos' => ['7000<0001>', false];
    }
}
