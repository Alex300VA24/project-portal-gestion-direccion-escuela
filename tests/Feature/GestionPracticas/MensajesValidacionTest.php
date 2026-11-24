<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** Los errores de los formularios se leen en español y dicen qué corregir (nunca claves como «validation.digits»). */
final class MensajesValidacionTest extends PracticasTestCase
{
    /** @return list<string> */
    private function mensajes(TestResponse $respuesta): array
    {
        $bolsa = $respuesta->baseResponse->getSession()->get('errors')['default']['messages'] ?? [];

        return array_merge(...array_values($bolsa ?: [[]]));
    }

    private function assertSoloEspanol(TestResponse $respuesta): void
    {
        $mensajes = $this->mensajes($respuesta);

        $this->assertNotEmpty($mensajes, 'La petición inválida debía devolver errores.');

        foreach ($mensajes as $mensaje) {
            $this->assertStringNotContainsString('validation.', $mensaje, "Clave sin traducir: {$mensaje}");
            $this->assertStringNotContainsString(':attribute', $mensaje);
            $this->assertStringNotContainsString('The ', $mensaje, "Mensaje en inglés: {$mensaje}");
            $this->assertStringNotContainsString(' field ', $mensaje, "Mensaje en inglés: {$mensaje}");
        }
    }

    public function test_el_formulario_de_empresa_explica_cada_error(): void
    {
        $r = $this->como('admin')->post(route('gestion-practicas.empresas.store'), [
            'ruc' => '1369852147', 'razon_social' => '', 'contacto_dni' => '123', 'correo_acceso' => 'no-es-correo',
            'password' => 'corta', 'pagina_web' => 'empresa.com',
        ]);

        $this->assertSoloEspanol($r);
        $mensajes = implode(' | ', $this->mensajes($r));
        $this->assertStringContainsString('El RUC debe tener exactamente 11 dígitos', $mensajes);
        $this->assertStringContainsString('DNI del representante debe tener exactamente 8 dígitos', $mensajes);
        $this->assertStringContainsString('correo de acceso válido', $mensajes);
        $this->assertStringContainsString('al menos 8 caracteres', $mensajes);
        $this->assertStringContainsString('http:// o https://', $mensajes);
    }

    public function test_un_ruc_de_persona_natural_se_explica_con_claridad(): void
    {
        $r = $this->como('admin')->post(route('gestion-practicas.empresas.store'), [
            'ruc' => '10123456789', 'razon_social' => 'Empresa de prueba', 'contacto_dni' => '71234567',
            'contacto_nombres' => 'Ana', 'contacto_apellido_paterno' => 'Paz', 'correo_acceso' => 'a@empresa.test', 'password' => 'Segura2026!',
        ]);

        $this->assertSoloEspanol($r);
        $this->assertStringContainsString('deben comenzar con 20', implode(' ', $this->mensajes($r)));
    }

    public function test_el_formulario_de_convocatoria_explica_cada_error(): void
    {
        $r = $this->como('empresa')->post(route('gestion-practicas.empresa.convocatorias.store'), [
            'titulo' => '', 'enlace_postulacion' => 'sin-enlace', 'tipo_practica' => 'x', 'vacantes' => '0',
            'fecha_cierre' => now()->subDay()->toDateString(),
        ]);

        $this->assertSoloEspanol($r);
        $mensajes = implode(' | ', $this->mensajes($r));
        $this->assertStringContainsString('Complete el campo «título»', $mensajes);
        $this->assertStringContainsString('no puede ser anterior a hoy', $mensajes);
    }

    public function test_el_cambio_de_contrasena_explica_cada_error(): void
    {
        $r = $this->como('empresa')->put(route('gestion-practicas.empresa.perfil.password'), []);

        $this->assertSoloEspanol($r);
        $this->assertStringContainsString('Escriba su contraseña actual', implode(' ', $this->mensajes($r)));
    }

    public function test_el_formulario_de_la_practica_explica_cada_error(): void
    {
        $estudiante = (int) DB::table('core.estudiante')->where('codigo_universitario', 'E2026DEMO')->value('id_estudiante');
        $postulacion = Postulacion::create([
            'id_convocatoria' => Convocatoria::firstOrFail()->id_convocatoria, 'id_estudiante' => $estudiante,
            'estado' => 'seleccionado', 'resultado_en' => now(),
            'resultado_registrado_por' => (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.estudiante@portal.local')->value('id_usuario'),
        ]);

        $r = $this->como('estudiante')->post(route('gestion-practicas.estudiante.practicas.store', $postulacion), [
            'area' => '', 'fecha_inicio' => now()->addMonth()->toDateString(), 'fecha_fin' => now()->toDateString(),
            'horas_totales' => 'muchas', 'carta' => UploadedFile::fake()->create('carta.exe', 10, 'application/x-msdownload'),
        ]);

        $this->assertSoloEspanol($r);
        $mensajes = implode(' | ', $this->mensajes($r));
        $this->assertStringContainsString('La fecha de fin debe ser posterior a la fecha de inicio', $mensajes);
        $this->assertStringContainsString('PDF o una imagen', $mensajes);
    }

    public function test_una_cuenta_sin_vinculo_recibe_un_mensaje_que_dice_que_hacer(): void
    {
        $huerfano = User::create(['name' => 'Sin vínculo', 'email' => 'huerfano2@portal.local', 'password' => 'Portal2026!']);
        $huerfano->forceFill(['email_verified_at' => now()])->save();
        $huerfano->assignRole(Acceso::ESTUDIANTE);

        $this->actingAs($huerfano)->get(route('gestion-practicas.estudiante.postulaciones.index'))
            ->assertForbidden()
            ->assertSee('Contacte al administrador')
            ->assertDontSee('seguridad.usuario');
    }
}
