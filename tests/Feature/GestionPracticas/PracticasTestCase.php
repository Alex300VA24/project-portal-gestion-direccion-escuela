<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use App\Modules\GestionPracticas\Database\Seeders\GestionPracticasDemoSeeder;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Base de las pruebas del módulo. Requiere PostgreSQL migrado (module:migrate all);
 * con SQLite se omiten, igual que el resto de pruebas de esquemas.
 *
 * Cada prueba parte de GestionPracticasDemoSeeder (un usuario por actor) dentro de una
 * transacción que se revierte al terminar.
 */
abstract class PracticasTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }

        Storage::fake('local');
        $this->seed(GestionPracticasDemoSeeder::class);
    }

    /** Inicia sesión como el actor de demostración: admin, empresa, estudiante o supervisor. */
    protected function como(string $actor): static
    {
        return $this->actingAs(User::where('email', "practicas.{$actor}@portal.local")->firstOrFail());
    }

    /** Crea otro estudiante con su cuenta, para probar el aislamiento entre estudiantes. */
    protected function otroEstudiante(string $sufijo = 'b'): User
    {
        $persona = $this->persona("7002{$sufijo}", 'Otro', 'Estudiante');
        $base = DB::table('core.estudiante')->first();

        DB::table('core.estudiante')->insert([
            'codigo_universitario' => "E-{$sufijo}", 'id_persona' => $persona, 'id_escuela' => $base->id_escuela,
            'id_plan' => $base->id_plan, 'id_periodo_ingreso' => $base->id_periodo_ingreso,
            'id_modalidad_ingreso' => $base->id_modalidad_ingreso, 'estado' => 'Activo',
        ]);

        return $this->cuenta("estudiante.{$sufijo}@portal.local", $persona, Acceso::ESTUDIANTE);
    }

    /** Crea otro docente con su cuenta de supervisor. */
    protected function otroSupervisor(string $sufijo = 'b'): User
    {
        $persona = $this->persona("7003{$sufijo}", 'Otro', 'Supervisor');
        $departamento = DB::table('core.departamento_academico')->value('id_departamento');

        DB::table('core.docente')->insert([
            'id_persona' => $persona, 'codigo_docente' => "D-{$sufijo}", 'id_departamento' => $departamento, 'estado' => 'Activo',
        ]);

        return $this->cuenta("supervisor.{$sufijo}@portal.local", $persona, Acceso::SUPERVISOR);
    }

    protected function idDocente(string $correo = 'practicas.supervisor@portal.local'): int
    {
        return (int) DB::table('core.docente as d')
            ->join('seguridad.usuario as u', 'u.id_persona', '=', 'd.id_persona')
            ->where('u.correo_institucional', $correo)->value('d.id_docente');
    }

    private function persona(string $dni, string $nombres, string $apellido): int
    {
        return (int) DB::table('core.persona')->insertGetId([
            'tipo_documento' => 'DNI', 'numero_documento' => $dni, 'nombres' => $nombres, 'apellido_paterno' => $apellido,
        ], 'id_persona');
    }

    private function cuenta(string $correo, int $persona, string $rol): User
    {
        DB::table('seguridad.usuario')->insert([
            'id_persona' => $persona, 'correo_institucional' => $correo,
            'contrasena_hash' => Hash::make('Portal2026!'), 'estado' => 'Activo', 'debe_cambiar_contrasena' => false,
        ]);

        $user = User::create(['name' => $correo, 'email' => $correo, 'password' => 'Portal2026!']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($rol);

        return $user;
    }
}
