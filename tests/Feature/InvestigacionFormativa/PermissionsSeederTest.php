<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Database\Seeders\InvestigacionPermisosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PermissionsSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_seeder_is_idempotent_and_additive(): void
    {
        $profesor = Role::firstOrCreate(['name' => 'profesor', 'guard_name' => 'web']);
        $secretaria = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);
        $existente = Permission::firstOrCreate(['name' => 'prueba.permiso.existente', 'guard_name' => 'web']);
        $profesor->givePermissionTo($existente);

        $this->seed(InvestigacionPermisosSeeder::class);
        $this->seed(InvestigacionPermisosSeeder::class);

        $this->assertSame(
            12,
            Permission::query()->where('name', 'like', 'investigacion-formativa.%')->count(),
        );

        $profesor = $profesor->fresh();

        $this->assertTrue($profesor->hasPermissionTo('investigacion-formativa.configuration.manage'));
        $this->assertTrue($profesor->hasPermissionTo('investigacion-formativa.groups.review'));
        $this->assertTrue($profesor->hasPermissionTo('investigacion-formativa.evaluations.manage'));
        $this->assertFalse($profesor->hasPermissionTo('investigacion-formativa.courses.manage'));
        $this->assertTrue($profesor->hasPermissionTo('prueba.permiso.existente'));

        $director = Role::findByName('director_escuela', 'web');
        $this->assertTrue($director->hasPermissionTo('investigacion-formativa.courses.manage'));
        $this->assertTrue($director->hasPermissionTo('investigacion-formativa.surveys.manage'));
        $this->assertTrue($director->hasPermissionTo('investigacion-formativa.reports.view'));

        $alumno = Role::findByName('alumno', 'web');
        $this->assertTrue($alumno->hasPermissionTo('investigacion-formativa.groups.create'));
        $this->assertTrue($alumno->hasPermissionTo('investigacion-formativa.projects.update'));
        $this->assertTrue($alumno->hasPermissionTo('investigacion-formativa.submissions.create'));
        $this->assertTrue($alumno->hasPermissionTo('investigacion-formativa.surveys.respond'));
        $this->assertFalse($alumno->hasPermissionTo('investigacion-formativa.groups.review'));

        $secretaria = $secretaria->fresh();
        $this->assertFalse($secretaria->hasPermissionTo('investigacion-formativa.view'));
    }
}
