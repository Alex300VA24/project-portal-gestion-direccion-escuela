<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_institutional_landing_is_available(): void
    {
        $this->get('/web-institucional')
            ->assertOk()
            ->assertSee('Portal Gestión Dirección')
            ->assertSee('Iniciar sesión');
    }

    public function test_quality_workspace_requires_authentication(): void
    {
        $this->get('/calidad/home')
            ->assertRedirect('/login');
    }

    public function test_login_uses_the_institutional_experience(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Bienvenido de nuevo.')
            ->assertSee('Correo institucional')
            ->assertSee('Ingresar al portal');
    }

    public function test_development_accounts_are_seeded_with_their_roles(): void
    {
        $this->seed();

        $expectedAccounts = [
            'director@portal.local' => 'director_escuela',
            'secretaria@portal.local' => 'secretaria',
            'profesor@portal.local' => 'profesor',
            'alumno@portal.local' => 'alumno',
        ];

        foreach ($expectedAccounts as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check('Portal2026!', $user->password));
            $this->assertTrue($user->hasRole($role));
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function test_verified_user_can_open_portal_and_quality_routes(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Elige dónde trabajar')
            ->assertSee('Gestión de Calidad')
            ->assertSee('11 disponibles')
            ->assertDontSee('Web Institucional');

        $this->actingAs($user)->get('/calidad/home')
            ->assertOk()
            ->assertSee('Estado de la calidad');

        $this->actingAs($user)->get('/calidad/mapa-procesos')
            ->assertOk()
            ->assertSee('Mapa de procesos');
    }
}
