<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lo que está por cerrar y aún espera respuestas, para la portada del módulo: las campañas abiertas (de egresados y de
 * empleadores) y el contacto inicial de cada promoción que sigue en plazo. Se ordena por la fecha de cierre más cercana.
 */
final class AvisosDeInicio
{
    public function __construct(private readonly ContactoInicialService $contactoInicial) {}

    /**
     * @return array{avisos: list<array<string, mixed>>, total: int}
     */
    public function obtener(int $maximo = 5): array
    {
        try {
            $avisos = [...$this->campaniasAbiertas(), ...$this->contactoInicialEnPlazo()];
        } catch (Throwable $e) {
            report($e);
            $avisos = [];
        }

        usort($avisos, fn (array $a, array $b) => [$a['cierra'], $a['titulo']] <=> [$b['cierra'], $b['titulo']]);

        return ['avisos' => array_slice($avisos, 0, $maximo), 'total' => count($avisos)];
    }

    /** @return list<array<string, mixed>> */
    private function campaniasAbiertas(): array
    {
        $hoy = $this->hoy();
        $campanias = CampaniaSeguimiento::with('invitaciones.medicion')
            ->whereDate('fecha_inicio', '<=', $hoy->toDateString())->whereDate('fecha_fin', '>=', $hoy->toDateString())
            ->get();

        $deEmpresas = DB::table('seguimiento_egresado.invitacion_empresa')
            ->whereIn('id_campania', $campanias->where('tipo', 'EMPRESAS')->pluck('id'))->where('estado', '<>', 'ANULADA')
            ->selectRaw("id_campania, count(*) as total, sum(case when estado = 'COMPLETADA' then 1 else 0 end) as respondieron")
            ->groupBy('id_campania')->get()->keyBy('id_campania');

        return $campanias->map(function (CampaniaSeguimiento $c) use ($hoy, $deEmpresas) {
            if ($c->tipo === 'EMPRESAS') {
                $total = (int) ($deEmpresas[$c->id]->total ?? 0);
                $respondieron = (int) ($deEmpresas[$c->id]->respondieron ?? 0);
                $subtitulo = 'Para empleadores';
                $enlace = route('seguimiento-egresado.empresas', ['campania' => $c->id]);
            } else {
                $total = $c->invitaciones->count();
                $respondieron = $c->invitaciones->filter(fn ($i) => $i->estado === 'COMPLETADA' || $i->medicion !== null)->count();
                $subtitulo = 'Para egresados · '.$c->periodo;
                $enlace = route('seguimiento-egresado.campanias', ['ver' => $c->id]);
            }

            return $this->aviso($c->nombre, $subtitulo, CarbonImmutable::parse($c->fecha_fin, FiltroIndicadores::ZONA), $hoy, $respondieron, $total, $enlace, 'Ver detalle', $c->tipo === 'EMPRESAS' ? 'enlaces' : 'invitaciones');
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function contactoInicialEnPlazo(): array
    {
        $hoy = $this->hoy();

        return $this->contactoInicial->avancePorPromocion()
            ->filter(fn (array $f) => $f['con_avance'] && ! $f['terminada'] && $f['pendientes'] > 0 && $f['fecha_limite'] !== null)
            ->map(fn (array $f) => $this->aviso(
                'Contacto inicial · Promoción '.$f['promocion']->anio_ingreso, 'Datos de contacto de los alumnos',
                CarbonImmutable::parse($f['fecha_limite'], FiltroIndicadores::ZONA), $hoy, $f['completaron'], $f['total'],
                route('seguimiento-egresado.padron.promocion', $f['promocion']->id), 'Ver promoción', 'alumnos',
            ))->values()->all();
    }

    /** @return array<string, mixed> */
    private function aviso(string $titulo, string $subtitulo, CarbonImmutable $cierra, CarbonImmutable $hoy, int $respondieron, int $total, string $enlace, string $boton, string $unidad): array
    {
        $dias = max(0, (int) round($hoy->diffInDays($cierra->startOfDay())));

        return [
            'titulo' => $titulo, 'subtitulo' => $subtitulo, 'cierra' => $cierra->toDateString(), 'dias' => $dias,
            'urgente' => $dias <= 7, 'respondieron' => $respondieron, 'total' => $total, 'faltan' => max(0, $total - $respondieron),
            'porcentaje' => $total > 0 ? (int) round(100 * $respondieron / $total) : 0, 'unidad' => $unidad,
            'enlace' => $enlace, 'boton' => $boton,
        ];
    }

    private function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(FiltroIndicadores::ZONA)->startOfDay();
    }
}
