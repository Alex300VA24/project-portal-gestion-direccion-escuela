<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Services\EjecucionesCpsatPrueba;
use RuntimeException;
use Tests\TestCase;

final class ColaSeguridadTest extends TestCase
{
    public function test_no_expone_despacho_publico(): void
    {
        $this->postJson('/gestion-horarios/administracion/ejecutar-cpsat')->assertNotFound();
    }

    public function test_rechaza_base_no_autorizada(): void
    {
        $this->expectException(RuntimeException::class);
        (new EjecucionesCpsatPrueba)->encolar('academico_factible_v1');
    }
}
