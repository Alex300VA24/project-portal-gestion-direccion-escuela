<?php

namespace Tests\Feature\TesisProyectoTesis;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rutas;
use Tests\TestCase;

/**
 * Revisión automática de 06_seguridad.md sobre el registro de rutas del módulo.
 */
final class SeguridadRutasTest extends TestCase
{
    /** @return list<Route> */
    private function rutas(): array
    {
        return array_values(array_filter(
            Rutas::getRoutes()->getRoutes(),
            fn (Route $r) => str_starts_with((string) $r->getName(), 'tesis-proyecto-tesis.'),
        ));
    }

    public function test_toda_ruta_salvo_la_portada_exige_auth_y_verified(): void
    {
        $this->assertNotEmpty($this->rutas());

        foreach ($this->rutas() as $r) {
            if ($r->getName() === 'tesis-proyecto-tesis.index') {
                continue;
            }
            $mw = $r->gatherMiddleware();
            $this->assertContains('auth', $mw, $r->getName().' sin auth');
            $this->assertContains('verified', $mw, $r->getName().' sin verified');
        }
    }

    public function test_rutas_de_cada_carril_exigen_su_rol(): void
    {
        $roles = ['alumno.' => 'role:alumno', 'asesor.' => 'role:profesor', 'jurado.' => 'role:profesor', 'director.' => 'role:director_escuela', 'secretaria.' => 'role:secretaria'];

        foreach ($this->rutas() as $r) {
            $nombre = substr((string) $r->getName(), strlen('tesis-proyecto-tesis.'));
            foreach ($roles as $prefijo => $rol) {
                if (str_starts_with($nombre, $prefijo)) {
                    $this->assertContains($rol, $r->gatherMiddleware(), $r->getName()." sin $rol");
                }
            }
        }
    }

    public function test_parametros_de_ruta_solo_aceptan_uuid(): void
    {
        foreach ($this->rutas() as $r) {
            foreach ($r->parameterNames() as $param) {
                if ($param === 'tipo') {
                    continue;
                }
                $this->assertArrayHasKey($param, $r->wheres, $r->getName()." sin restricción para {$param}");
                $this->assertStringContainsString('[\da-fA-F]{8}', $r->wheres[$param], $r->getName()." · {$param} no es uuid");
            }
        }
    }

    public function test_escrituras_solo_por_post(): void
    {
        foreach ($this->rutas() as $r) {
            $metodos = array_diff($r->methods(), ['HEAD']);
            $this->assertTrue($metodos === ['GET'] || $metodos === ['POST'] || array_values($metodos) === ['POST'], $r->getName());
        }
    }

    public function test_vistas_del_modulo_sin_salida_sin_escapar(): void
    {
        $dir = resource_path('views/modules/tesis-proyecto-tesis');
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($archivos as $f) {
            $codigo = (string) file_get_contents($f->getPathname());
            $this->assertStringNotContainsString('{!!', $codigo, $f->getFilename().' usa {!! !!}');
            if (preg_match_all('/<form[^>]*method="POST"[^>]*>/i', $codigo, $m)) {
                $this->assertGreaterThanOrEqual(count($m[0]), substr_count($codigo, '@csrf'), $f->getFilename().' tiene formularios POST sin @csrf');
            }
        }
    }

    public function test_modelos_del_modulo_declaran_fillable(): void
    {
        foreach (glob(app_path('Modules/TesisProyectoTesis/Models/*.php')) as $archivo) {
            $clase = 'App\\Modules\\TesisProyectoTesis\\Models\\'.basename($archivo, '.php');
            $this->assertStringContainsString('protected $fillable', (string) file_get_contents($archivo), $clase.' sin $fillable');
            $this->assertStringStartsWith('tesis.', (new $clase)->getTable(), $clase.' sin tabla calificada');
        }
    }
}
