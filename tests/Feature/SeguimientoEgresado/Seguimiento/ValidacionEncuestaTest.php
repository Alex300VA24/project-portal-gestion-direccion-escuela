<?php

namespace Tests\Feature\SeguimientoEgresado\Seguimiento;

use App\Modules\SeguimientoEgresado\Requests\EncuestaSeguimientoRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Reglas condicionales de la encuesta, sin consultar catálogos (eso se verifica contra la BD aparte). */
class ValidacionEncuestaTest extends TestCase
{
    private function validar(array $datos): \Illuminate\Validation\Validator
    {
        $request = new EncuestaSeguimientoRequest;

        return Validator::make($datos, EncuestaSeguimientoRequest::reglasPara($datos, false), $request->messages(), $request->attributes());
    }

    private function base(array $cambios = []): array
    {
        return array_merge([
            'situacion' => 'NO_TRABAJA',
            'pais_residencia' => 'PE',
            'area_profesional' => 'SOFTWARE',
            'nivel_ingles' => 'INTERMEDIO',
            'satisfaccion' => 'SATISFECHO',
        ], $cambios);
    }

    private function trabajando(array $cambios = []): array
    {
        return $this->base(array_merge([
            'situacion' => 'TRABAJA',
            'trabajo_relacionado' => '1',
            'modalidad_laboral' => 'DEPENDIENTE',
            'modalidad_trabajo' => 'HIBRIDA',
            'sector_laboral' => 'PRIVADO',
            'nombre_empleador_cliente' => 'Empresa Ficticia S.A.C.',
            'pais_empleador' => 'PE',
            'region_empleo' => 'LA_LIBERTAD',
            'autoriza_contacto_empresa' => '0',
        ], $cambios));
    }

    public function test_una_respuesta_completa_de_quien_trabaja_es_valida(): void
    {
        $this->assertTrue($this->validar($this->trabajando())->passes());
    }

    public function test_quien_no_trabaja_no_necesita_ni_almacena_campos_de_empleo(): void
    {
        $datos = $this->base([
            'modalidad_laboral' => 'FREELANCE', 'nombre_empleador_cliente' => 'Resto de una selección anterior',
            'pais_empleador' => 'PE', 'region_empleo' => 'LA_LIBERTAD', 'autoriza_contacto_empresa' => '1', 'contacto_jefe' => 'jefe@empresa.test',
        ]);

        $validador = $this->validar($datos);

        $this->assertTrue($validador->passes());
        $this->assertSame(
            ['situacion', 'pais_residencia', 'area_profesional', 'nivel_ingles', 'satisfaccion'],
            array_keys($validador->validated()),
        );
    }

    public function test_el_pais_de_residencia_es_obligatorio_aunque_no_trabaje(): void
    {
        $datos = $this->base();
        unset($datos['pais_residencia']);

        $this->assertArrayHasKey('pais_residencia', $this->validar($datos)->errors()->toArray());
    }

    public function test_quien_trabaja_debe_completar_los_datos_del_empleo(): void
    {
        $errores = $this->validar($this->base(['situacion' => 'TRABAJA']))->errors()->keys();

        foreach (['trabajo_relacionado', 'modalidad_laboral', 'modalidad_trabajo', 'sector_laboral', 'nombre_empleador_cliente', 'pais_empleador', 'autoriza_contacto_empresa'] as $campo) {
            $this->assertContains($campo, $errores, "Falta exigir {$campo}");
        }
    }

    public function test_la_situacion_y_el_nivel_de_ingles_deben_ser_valores_conocidos(): void
    {
        $this->assertArrayHasKey('situacion', $this->validar($this->base(['situacion' => 'JUBILADO']))->errors()->toArray());
        $this->assertArrayHasKey('nivel_ingles', $this->validar($this->base(['nivel_ingles' => 'NATIVO']))->errors()->toArray());
    }

    public function test_la_region_solo_se_pide_si_el_empleo_es_en_peru(): void
    {
        $this->assertArrayHasKey('region_empleo', $this->validar($this->trabajando(['region_empleo' => null]))->errors()->toArray());
        $this->assertArrayHasKey('region_empleo', $this->validar($this->trabajando(['region_empleo' => 'LIMA']))->errors()->toArray());

        $extranjero = $this->validar($this->trabajando(['pais_empleador' => 'US', 'region_empleo' => 'LA_LIBERTAD']));
        $this->assertTrue($extranjero->passes());
        $this->assertArrayNotHasKey('region_empleo', $extranjero->validated());
    }

    public function test_el_contacto_del_jefe_exige_autorizacion_y_formato_valido(): void
    {
        $sinContacto = $this->validar($this->trabajando(['autoriza_contacto_empresa' => '1']));
        $this->assertArrayHasKey('contacto_jefe', $sinContacto->errors()->toArray());

        $this->assertTrue($this->validar($this->trabajando(['autoriza_contacto_empresa' => '1', 'contacto_jefe' => 'jefe@empresa.test']))->passes());
        $this->assertTrue($this->validar($this->trabajando(['autoriza_contacto_empresa' => '1', 'contacto_jefe' => '+51 900 123 456']))->passes());
        $this->assertArrayHasKey('contacto_jefe', $this->validar($this->trabajando(['autoriza_contacto_empresa' => '1', 'contacto_jefe' => 'no es un contacto']))->errors()->toArray());
    }

    public function test_sin_autorizacion_el_contacto_del_jefe_no_se_almacena(): void
    {
        $validador = $this->validar($this->trabajando(['autoriza_contacto_empresa' => '0', 'contacto_jefe' => 'jefe@empresa.test']));

        $this->assertTrue($validador->passes());
        $this->assertArrayNotHasKey('contacto_jefe', $validador->validated());
    }

    public function test_la_opcion_otra_en_habilidades_exige_su_detalle(): void
    {
        $this->assertArrayHasKey('detalle_brecha_otra', $this->validar($this->base(['brechas' => ['CLOUD', 'OTRA']]))->errors()->toArray());
        $this->assertTrue($this->validar($this->base(['brechas' => ['CLOUD', 'OTRA'], 'detalle_brecha_otra' => 'Gestión ágil']))->passes());
        $this->assertArrayHasKey('detalle_fortaleza_otra', $this->validar($this->base(['fortalezas' => ['OTRA']]))->errors()->toArray());

        $sinOtra = $this->validar($this->base(['brechas' => ['CLOUD'], 'detalle_brecha_otra' => 'se descarta']));
        $this->assertArrayNotHasKey('detalle_brecha_otra', $sinOtra->validated());
    }

    public function test_no_se_admiten_habilidades_repetidas(): void
    {
        $this->assertTrue($this->validar($this->base(['brechas' => ['CLOUD', 'CLOUD']]))->fails());
    }

    public function test_la_formacion_es_opcional_y_repetible(): void
    {
        $this->assertTrue($this->validar($this->base())->passes());

        $dos = $this->base(['formaciones' => [
            ['tipo' => 'DIPLOMADO', 'estado' => 'EN_CURSO', 'area_formacion' => 'Datos', 'institucion' => 'Universidad Ficticia', 'fecha_inicio' => '2026-03-01'],
            ['tipo' => 'CERTIFICACION', 'estado' => 'CULMINADA', 'area_formacion' => 'Cloud', 'institucion' => 'Academia Ficticia', 'fecha_inicio' => '2025-01-10', 'fecha_fin' => '2025-04-10'],
        ]]);

        $this->assertTrue($this->validar($dos)->passes());
    }

    public function test_una_formacion_en_curso_no_guarda_fecha_de_fin(): void
    {
        $validador = $this->validar($this->base(['formaciones' => [
            ['tipo' => 'CURSO', 'estado' => 'EN_CURSO', 'area_formacion' => 'Redes', 'institucion' => 'Instituto Ficticio', 'fecha_fin' => '2026-12-01'],
        ]]));

        $this->assertTrue($validador->passes());
        $this->assertArrayNotHasKey('fecha_fin', $validador->validated()['formaciones'][0]);
    }

    public function test_una_formacion_culminada_exige_fecha_de_fin_y_no_anterior_al_inicio(): void
    {
        $sinFin = $this->validar($this->base(['formaciones' => [
            ['tipo' => 'CURSO', 'estado' => 'CULMINADA', 'area_formacion' => 'Redes', 'institucion' => 'Instituto Ficticio'],
        ]]));
        $this->assertArrayHasKey('formaciones.0.fecha_fin', $sinFin->errors()->toArray());

        $finAntes = $this->validar($this->base(['formaciones' => [
            ['tipo' => 'CURSO', 'estado' => 'CULMINADA', 'area_formacion' => 'Redes', 'institucion' => 'Instituto Ficticio', 'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-01-01'],
        ]]));
        $this->assertArrayHasKey('formaciones.0.fecha_fin', $finAntes->errors()->toArray());
    }

    public function test_cada_formacion_exige_tipo_estado_area_e_institucion(): void
    {
        $errores = $this->validar($this->base(['formaciones' => [[]]]))->errors()->keys();

        foreach (['tipo', 'estado', 'area_formacion', 'institucion'] as $campo) {
            $this->assertContains("formaciones.0.$campo", $errores);
        }
    }

    public function test_no_se_admiten_mas_de_diez_formaciones(): void
    {
        $item = ['tipo' => 'CURSO', 'estado' => 'EN_CURSO', 'area_formacion' => 'Redes', 'institucion' => 'Instituto Ficticio'];

        $this->assertTrue($this->validar($this->base(['formaciones' => array_fill(0, 11, $item)]))->fails());
        $this->assertTrue($this->validar($this->base(['formaciones' => array_fill(0, 10, $item)]))->passes());
    }

    public function test_los_mensajes_estan_en_espanol(): void
    {
        $mensaje = $this->validar($this->base(['situacion' => null]))->errors()->first('situacion');

        $this->assertSame('El campo situación laboral es obligatorio.', $mensaje);
    }
}
