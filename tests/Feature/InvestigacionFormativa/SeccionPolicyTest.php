<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Policies\SeccionPolicy;
use App\Shared\Models\Core\Seccion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class SeccionPolicyTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    private SeccionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
        $this->policy = app(SeccionPolicy::class);
    }

    public function test_director_solo_ve_su_escuela(): void
    {
        $seccionA = Seccion::query()->findOrFail(9901);
        $seccionB = Seccion::query()->findOrFail(9902);

        $this->assertTrue($this->policy->verComoDirector($this->datos['users']['director'], $seccionA));
        $this->assertFalse($this->policy->verComoDirector($this->datos['users']['director'], $seccionB));
    }

    public function test_docente_solo_ve_su_seccion_asignada(): void
    {
        $seccionA = Seccion::query()->findOrFail(9901);
        $seccionB = Seccion::query()->findOrFail(9902);

        $this->assertTrue($this->policy->verComoProfesor($this->datos['users']['docente_a'], $seccionA));
        $this->assertFalse($this->policy->verComoProfesor($this->datos['users']['docente_a'], $seccionB));
        $this->assertTrue($this->policy->verComoProfesor($this->datos['users']['docente_b'], $seccionB));
    }

    public function test_alumno_solo_ve_secciones_donde_tiene_grupo(): void
    {
        $this->crearGruposInvestigacion();

        $seccionA = Seccion::query()->findOrFail(9901);
        $seccionB = Seccion::query()->findOrFail(9902);

        $this->assertTrue($this->policy->verComoAlumno($this->datos['users']['alumno_a'], $seccionA));
        $this->assertFalse($this->policy->verComoAlumno($this->datos['users']['alumno_b'], $seccionA));
        $this->assertTrue($this->policy->verComoAlumno($this->datos['users']['alumno_otra_escuela'], $seccionB));
        $this->assertFalse($this->policy->verComoAlumno($this->datos['users']['alumno_otra_escuela'], $seccionA));
    }

    public function test_rol_ajeno_y_usuario_sin_vinculacion_son_denegados(): void
    {
        $seccionA = Seccion::query()->findOrFail(9901);

        $this->assertFalse($this->policy->verComoDirector($this->datos['users']['secretaria'], $seccionA));
        $this->assertFalse($this->policy->verComoProfesor($this->datos['users']['secretaria'], $seccionA));
        $this->assertFalse($this->policy->verComoAlumno($this->datos['users']['secretaria'], $seccionA));

        $this->assertFalse($this->policy->verComoDirector($this->datos['users']['sin_mapa'], $seccionA));
        $this->assertFalse($this->policy->configurar($this->datos['users']['sin_mapa'], $seccionA));
    }

    public function test_crear_grupo_exige_participacion_activa_sin_grupo_previo(): void
    {
        $seccionA = Seccion::query()->findOrFail(9901);

        $this->assertTrue($this->policy->crearGrupo($this->datos['users']['alumno_a'], $seccionA));
        $this->assertFalse($this->policy->crearGrupo($this->datos['users']['retirado'], $seccionA));
        $this->assertFalse($this->policy->crearGrupo($this->datos['users']['docente_a'], $seccionA));
        $this->assertFalse($this->policy->crearGrupo($this->datos['users']['director'], $seccionA));
    }

    public function test_configurar_exige_docente_responsable(): void
    {
        $seccionA = Seccion::query()->findOrFail(9901);
        $seccionB = Seccion::query()->findOrFail(9902);

        $this->assertTrue($this->policy->configurar($this->datos['users']['docente_a'], $seccionA));
        $this->assertFalse($this->policy->configurar($this->datos['users']['docente_a'], $seccionB));
        $this->assertFalse($this->policy->configurar($this->datos['users']['director'], $seccionA));
    }

    public function test_habilitar_exige_director_de_la_escuela(): void
    {
        $seccionA = Seccion::query()->findOrFail(9901);
        $seccionB = Seccion::query()->findOrFail(9902);

        $this->assertTrue($this->policy->habilitar($this->datos['users']['director'], $seccionA));
        $this->assertFalse($this->policy->habilitar($this->datos['users']['director'], $seccionB));
        $this->assertFalse($this->policy->habilitar($this->datos['users']['docente_a'], $seccionA));
    }
}
