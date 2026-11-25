<?php

namespace App\Modules\Bienestar\Services;

use App\Models\User;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\ArticuloInformativo;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions as P;
use Illuminate\Support\Facades\DB;

/** Resumen de la portada según el rol: indicadores, pendientes y accesos. Solo datos que el rol puede ver. */
final class InicioBienestarService
{
    /** @return array{kpis: list<array>, pendientes: list<array>, accesos: list<array>} */
    public function resumen(?User $user): array
    {
        $r = ['kpis' => [], 'pendientes' => [], 'accesos' => []];
        if (! $user || DB::getDriverName() !== 'pgsql') {
            return $r;
        }
        $actor = ActorBienestarResolver::resolve($user);
        $kpi = function (string $texto, int $valor, string $url, string $tono = 'azul') use (&$r) {
            $r['kpis'][] = compact('texto', 'valor', 'url', 'tono');
        };
        $pendiente = function (string $titulo, string $detalle, string $url, string $tono = 'azul') use (&$r) {
            $r['pendientes'][] = compact('titulo', 'detalle', 'url', 'tono');
        };
        $acceso = function (string $titulo, string $detalle, string $url, string $icono) use (&$r) {
            $r['accesos'][] = compact('titulo', 'detalle', 'url', 'icono');
        };

        if (P::check($user, P::SOLICITAR) && $actor->idEstudiante) {
            $mias = SolicitudApoyo::where('id_estudiante', $actor->idEstudiante);
            $citas = Cita::whereHas('caso', fn ($q) => $q->where('id_estudiante', $actor->idEstudiante))
                ->where('estado', 'Programada')->where('inicio', '>=', now())->orderBy('inicio');
            $url = route('bienestar.alumno.solicitudes.index');
            $kpi('En trámite', (clone $mias)->whereIn('estado', ['enviada', 'recibida', 'remitida'])->count(), $url);
            $kpi('Por corregir', (clone $mias)->where('estado', 'observada')->count(), $url, 'rojo');
            $kpi('Aprobadas', (clone $mias)->where('estado', 'aprobada')->count(), $url, 'dorado');
            $kpi('Próximas citas', (clone $citas)->count(), route('bienestar.alumno.citas.index'));
            foreach ((clone $mias)->where('estado', 'observada')->with('tipoSolicitud')->limit(3)->get() as $s) {
                $pendiente("Corrige la solicitud #{$s->id_solicitud}", $s->tipoSolicitud?->nombre ?? '', route('bienestar.alumno.solicitudes.subsanar', $s), 'rojo');
            }
            if ($cita = $citas->first()) {
                $pendiente('Tu próxima cita', $cita->inicio->format('d/m/Y H:i').' · '.$cita->modalidad, route('bienestar.alumno.citas.index'), 'dorado');
            }
            $acceso('Nueva solicitud', TipoSolicitud::activos()->count().' trámites disponibles con sus requisitos.', route('bienestar.alumno.solicitudes.create'), 'mas');
            $acceso('Mis solicitudes', 'Sigue cada etapa y corrige lo observado.', $url, 'documento');
            $acceso('Mis citas', 'Consulta tu agenda de acompañamiento.', route('bienestar.alumno.citas.index'), 'calendario');
        }

        if (P::check($user, P::REPORTAR) && $actor->idDocente) {
            $mios = AlertaRiesgo::docentes()->where('id_docente', $actor->idDocente);
            $url = route('bienestar.profesor.index');
            $kpi('Reportes enviados', (clone $mios)->count(), $url);
            $kpi('En revisión', (clone $mios)->where('estado', 'en_revision')->count(), $url, 'dorado');
            $kpi('En Bienestar', (clone $mios)->whereIn('estado', ['remitida_bienestar', 'en_atencion'])->count(), $url);
            $kpi('Atendidos', (clone $mios)->where('estado', 'atendida')->count(), $url);
            $acceso('Reportar estudiante', 'Comunica un posible riesgo de permanencia.', route('bienestar.profesor.create'), 'alerta');
            $acceso('Mis reportes', 'Revisa en qué etapa está cada reporte.', $url, 'documento');
        }

        if (P::check($user, P::SECRETARIA)) {
            $alertas = AlertaRiesgo::where('estado', 'en_revision');
            $altas = (clone $alertas)->whereIn('prioridad', ['alta', 'urgente'])->count();
            $sol = fn (string $estado) => SolicitudApoyo::where('estado', $estado)->count();
            $bandeja = fn (string $estado) => route('bienestar.secretaria.solicitudes', ['estado' => $estado]);
            $kpi('Alertas por revisar', (clone $alertas)->count(), route('bienestar.secretaria.alertas'), 'dorado');
            $kpi('De prioridad alta', $altas, route('bienestar.secretaria.alertas', ['prioridad' => 'alta']), 'rojo');
            $kpi('Solicitudes nuevas', $nuevas = $sol('enviada'), $bandeja('enviada'));
            $kpi('En verificación', $verificando = $sol('recibida'), $bandeja('recibida'));
            if ($altas) {
                $pendiente($altas.($altas === 1 ? " alerta" : " alertas")." de prioridad alta", 'Pásalas a Bienestar cuanto antes.', route('bienestar.secretaria.alertas', ['prioridad' => 'alta']), 'rojo');
            }
            if ($nuevas) {
                $pendiente($nuevas.($nuevas === 1 ? " solicitud nueva" : " solicitudes nuevas"), 'Aún sin recibir.', $bandeja('enviada'), 'dorado');
            }
            if ($verificando) {
                $pendiente($verificando.($verificando === 1 ? " solicitud" : " solicitudes")." en verificación", 'Revisa sus requisitos y remítelas.', $bandeja('recibida'));
            }
            $acceso('Alertas en revisión', 'Pasa a Bienestar o descarta con motivo.', route('bienestar.secretaria.alertas'), 'alerta');
            $acceso('Recepción de solicitudes', 'Verifica cada requisito.', route('bienestar.secretaria.solicitudes'), 'documento');
            $acceso('Indicadores operativos', 'Tu carga de trabajo en cifras.', route('bienestar.secretaria.indicadores'), 'grafico');
        }

        if (P::check($user, P::GESTIONAR)) {
            $remitidas = AlertaRiesgo::where('estado', 'remitida_bienestar');
            $porDictaminar = SolicitudApoyo::where('estado', 'remitida')->where('folio_externo_confirmado', true)->count();
            $citasHoy = Cita::where('estado', 'Programada')->whereBetween('inicio', [now()->startOfDay(), now()->endOfDay()])->count();
            $kpi('Alertas por atender', (clone $remitidas)->count(), route('bienestar.panel.index'), 'rojo');
            $kpi('Casos abiertos', CasoBienestar::where('estado', '!=', 'Cerrado')->count(), route('bienestar.panel.casos.index', ['estado' => 'abierto']));
            $kpi('Por dictaminar', $porDictaminar, route('bienestar.panel.solicitudes.index', ['estado' => 'remitida']), 'dorado');
            $kpi('Citas de hoy', $citasHoy, route('bienestar.panel.casos.index', ['estado' => 'abierto']));
            foreach ((clone $remitidas)->with('estudiante.persona')->orderByRaw("CASE WHEN prioridad in ('alta','urgente') THEN 1 ELSE 2 END")->limit(3)->get() as $a) {
                $pendiente('Atender a '.trim($a->estudiante?->persona?->nombres.' '.$a->estudiante?->persona?->apellido_paterno),
                    'Alerta #'.$a->id_alerta.' · prioridad '.$a->prioridad, route('bienestar.panel.atender', $a), $a->prioridad === 'alta' ? 'rojo' : 'azul');
            }
            if ($porDictaminar) {
                $pendiente($porDictaminar.($porDictaminar === 1 ? " solicitud" : " solicitudes")." por dictaminar", 'Remitidas con folio confirmado.', route('bienestar.panel.solicitudes.index', ['estado' => 'remitida']), 'dorado');
            }
            $acceso('Panel de alertas', 'Atiende lo remitido por Secretaría.', route('bienestar.panel.index'), 'alerta');
            $acceso('Casos y citas', 'Seguimiento, derivaciones y cierre.', route('bienestar.panel.casos.index'), 'calendario');
            $acceso('Catálogo de trámites', 'Tipos, requisitos y plantillas.', route('bienestar.catalogo-solicitudes.index'), 'documento');
            $acceso('Estadísticas', 'Indicadores y tendencias.', route('bienestar.director.index'), 'grafico');
        } elseif (P::check($user, P::ESTADISTICAS)) {
            $url = route('bienestar.director.index');
            $kpi('Alertas registradas', AlertaRiesgo::count(), $url);
            $kpi('Casos abiertos', CasoBienestar::where('estado', '!=', 'Cerrado')->count(), $url, 'dorado');
            $kpi('Casos cerrados', CasoBienestar::where('estado', 'Cerrado')->count(), $url);
            $kpi('Solicitudes', SolicitudApoyo::count(), $url);
            $acceso('Estadísticas y reportes', 'Gráficos por periodo y rango de fechas.', $url, 'grafico');
            $acceso('Reporte imprimible', 'Versión lista para PDF.', route('bienestar.director.imprimir'), 'documento');
        }

        $acceso('Normativa académica', ArticuloInformativo::query()->publicados()->count().' artículos publicados.', route('bienestar.informacion.index'), 'libro');

        return $r;
    }
}
