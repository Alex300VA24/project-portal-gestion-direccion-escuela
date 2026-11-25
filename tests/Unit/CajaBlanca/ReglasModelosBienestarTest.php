<?php

namespace Tests\Unit\CajaBlanca;

use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Support\BienestarPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Caja blanca: tablas de decisión de las reglas de negocio de los modelos. */
final class ReglasModelosBienestarTest extends TestCase
{
    #[DataProvider('descartes')]
    public function test_salvaguarda_de_descarte(string $origen, string $prioridad, string $regla, bool $bloqueada): void
    {
        $alerta = new AlertaRiesgo(compact('origen', 'prioridad', 'regla'));

        $this->assertSame($bloqueada, $alerta->noPuedeDescartarse());
    }

    public static function descartes(): array
    {
        return [
            'automatica alta' => ['automatica', 'alta', 'inasistencia_inhabilitacion', true],
            'automatica urgente' => ['automatica', 'urgente', 'x', true],
            'automatica ALTA mayuscula' => ['automatica', 'ALTA', 'x', true],
            'tercera con prioridad media' => ['automatica', 'media', 'tercera_matricula', true],
            'cuarta alta' => ['automatica', 'alta', 'cuarta_matricula', true],
            'automatica media' => ['automatica', 'media', 'creditos_insuficientes', false],
            'automatica baja' => ['automatica', 'baja', 'inasistencia_preventiva', false],
            'docente alta' => ['reporte_docente', 'alta', 'reporte_docente', false],
            'manual alta' => ['manual_bienestar', 'alta', 'manual_bienestar', false],
        ];
    }

    #[DataProvider('dictamenes')]
    public function test_solo_se_dictamina_remitida_con_folio(string $estado, bool $folio, bool $esperado): void
    {
        $solicitud = new SolicitudApoyo(['estado' => $estado, 'folio_externo_confirmado' => $folio]);

        $this->assertSame($esperado, $solicitud->puedeDictaminarse());
    }

    public static function dictamenes(): array
    {
        $casos = ['remitida con folio' => ['remitida', true, true], 'remitida sin folio' => ['remitida', false, false]];
        foreach (['enviada', 'recibida', 'observada', 'aprobada', 'rechazada'] as $estado) {
            $casos["{$estado} con folio"] = [$estado, true, false];
        }

        return $casos;
    }

    public function test_permisos_sin_usuario_y_mapeo_de_middleware(): void
    {
        $this->assertFalse(BienestarPermissions::check(null, BienestarPermissions::GESTIONAR));
        $this->assertSame('role_or_permission:bienestar|bienestar.gestionar', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR));
        $this->assertSame('can:otro.permiso', BienestarPermissions::roleOrPermission('otro.permiso'));
    }
}
