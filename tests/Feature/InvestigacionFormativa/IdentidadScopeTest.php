<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;
use App\Modules\InvestigacionFormativa\Services\IdentidadInvestigacionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class IdentidadScopeTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_resuelve_identidad_activa_con_persona_y_docente(): void
    {
        $actor = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['docente_a']);

        $this->assertSame(8802, $actor->idUsuarioSeguridad);
        $this->assertSame(9003, $actor->idPersona);
        $this->assertSame(9801, $actor->idDocente);
    }

    public function test_rechaza_usuario_sin_vinculacion(): void
    {
        $this->expectException(AccesoDenegado::class);

        app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['sin_mapa']);
    }

    public function test_rechaza_vinculacion_inactiva(): void
    {
        $this->expectException(AccesoDenegado::class);

        app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['inactivo']);
    }

    public function test_rechaza_usuario_academico_inactivo(): void
    {
        $this->expectException(AccesoDenegado::class);

        app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['academico_inactivo']);
    }

    public function test_estudiante_se_resuelve_por_persona_y_escuela_de_la_seccion(): void
    {
        $actor = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['alumno_otra_escuela']);
        $acceso = app(AccesoInvestigacionService::class);

        $this->assertSame(9903, $acceso->idEstudianteEnEscuela($actor, 921));
        $this->assertNull($acceso->idEstudianteEnEscuela($actor, 920));
    }

    public function test_director_solo_ve_su_escuela_importada(): void
    {
        $acceso = app(AccesoInvestigacionService::class);
        $user = $this->datos['users']['director'];

        $this->assertTrue($acceso->puedeVerSeccion($user, 9901, 'director_escuela'));
        $this->assertFalse($acceso->puedeVerSeccion($user, 9902, 'director_escuela'));
    }

    public function test_docente_solo_ve_su_seccion_asignada(): void
    {
        $acceso = app(AccesoInvestigacionService::class);

        $this->assertTrue($acceso->puedeVerSeccion($this->datos['users']['docente_a'], 9901, 'profesor'));
        $this->assertFalse($acceso->puedeVerSeccion($this->datos['users']['docente_a'], 9902, 'profesor'));
        $this->assertTrue($acceso->puedeVerSeccion($this->datos['users']['docente_b'], 9902, 'profesor'));
    }

    public function test_docente_sin_asignacion_no_ve_ninguna_seccion(): void
    {
        $acceso = app(AccesoInvestigacionService::class);
        $actor = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['multi']);

        $this->assertSame(9803, $actor->idDocente);
        $this->assertFalse($acceso->puedeVerSeccion($this->datos['users']['multi'], 9901, 'profesor'));
        $this->assertFalse($acceso->puedeVerSeccion($this->datos['users']['multi'], 9902, 'profesor'));
    }

    public function test_alumno_solo_ve_secciones_donde_tiene_grupo(): void
    {
        $this->crearGruposInvestigacion();
        $acceso = app(AccesoInvestigacionService::class);

        $this->assertTrue($acceso->puedeVerSeccion($this->datos['users']['alumno_a'], 9901, 'alumno'));
        $this->assertFalse($acceso->puedeVerSeccion($this->datos['users']['alumno_b'], 9901, 'alumno'));
        $this->assertFalse($acceso->puedeVerSeccion($this->datos['users']['alumno_otra_escuela'], 9901, 'alumno'));
        $this->assertTrue($acceso->puedeVerSeccion($this->datos['users']['alumno_otra_escuela'], 9902, 'alumno'));
    }

    public function test_coordinador_se_exige_para_escrituras_del_grupo(): void
    {
        $this->crearGruposInvestigacion();
        $acceso = app(AccesoInvestigacionService::class);
        $actorA = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['alumno_a']);

        $acceso->exigirCoordinador($actorA, 9951);
        $this->addToAssertionCount(1);

        $actorB = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['alumno_b']);
        $this->expectException(AccesoDenegado::class);
        $acceso->exigirCoordinador($actorB, 9951);
    }

    public function test_coordinador_retirado_no_puede_escribir(): void
    {
        $this->crearGruposInvestigacion();
        $acceso = app(AccesoInvestigacionService::class);
        $actor = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['retirado']);

        $this->expectException(AccesoDenegado::class);
        $acceso->exigirCoordinador($actor, 9953);
    }

    public function test_usuario_multirrol_conserva_docencia_y_estudiante(): void
    {
        $this->crearGruposInvestigacion();
        $actor = app(IdentidadInvestigacionService::class)->resolver($this->datos['users']['multi']);

        $this->assertSame(9803, $actor->idDocente);
        $this->assertSame(9905, app(AccesoInvestigacionService::class)->idEstudianteEnEscuela($actor, 920));
    }

    public function test_datos_academicos_resuelven_escuela_de_seccion(): void
    {
        $datos = app(DatosAcademicosService::class);

        $this->assertSame(920, $datos->escuelaDeSeccion(9901));
        $this->assertSame(921, $datos->escuelaDeSeccion(9902));
        $this->assertSame(9801, $datos->seccionConEscuela(9901)->id_docente_responsable);
    }
}
