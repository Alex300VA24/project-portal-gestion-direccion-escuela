<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Models\User;
use App\Modules\GestionHorarios\Services\AmbitoEscrituraAcademica;
use App\Modules\GestionHorarios\Services\AutorizacionGestionAcademica;
use App\Shared\Contracts\CierreDemandaPrematricula;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Ajustes locales de planificación. Nunca modifica las solicitudes ni su instantánea. */
final class AjustesDemanda
{
    public function __construct(private readonly ConnectionInterface $db) {}

    private function fuente(): ?CierreDemandaPrematricula
    {
        if (! app()->environment('local', 'testing') || $this->db->getDriverName() !== 'pgsql'
            || ! in_array($this->db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            || (string) $this->db->getConfig('port') !== '5433'
            || $this->db->getDatabaseName() !== 'portal_integracion_pm_gh'
            || $this->db->selectOne('select current_database() as nombre')->nombre !== 'portal_integracion_pm_gh'
            || ! $this->db->getSchemaBuilder()->hasTable('gestion_horarios.ajuste_demanda')
            || ! app()->bound(CierreDemandaPrematricula::class)) {
            return null;
        }

        return app(CierreDemandaPrematricula::class);
    }

    public function habilitado(int $periodo): bool
    {
        try {
            return $this->fuente()?->confirmado($periodo) === true;
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    public function ultimo(int $demanda, int $curso): ?object
    {
        return $this->db->table('gestion_horarios.ajuste_demanda')->where('id_demanda', $demanda)->where('id_curso_plan', $curso)->orderByDesc('id_ajuste')->first();
    }

    public static function aplicar(object $fila, int $total): object
    {
        $fila->total = $total;
        // No inventar preferencias individuales cuando la reducción impide conservarlas.
        if ((int) $fila->manana + (int) $fila->tarde > $total) {
            $fila->manana = 0;
            $fila->tarde = 0;
        }
        return $fila;
    }

    public function filas(int $periodo, ?int $demanda, iterable $filas): void
    {
        if ($demanda === null || ! $this->habilitado($periodo)) return;
        $ajustes = $this->db->table('gestion_horarios.ajuste_demanda')->where('id_demanda', $demanda)
            ->orderByDesc('id_ajuste')->get()->unique('id_curso_plan')->keyBy('id_curso_plan');
        foreach ($filas as $fila) {
            if ($fila->total === null) continue;
            $ajuste = $ajustes->get($fila->id_curso_plan);
            if ($ajuste) {
                $fila->total_original = $fila->total;
                self::aplicar($fila, (int) $ajuste->total_ajustado);
            }
        }
    }

    public function detalle(int $periodo, int $curso): array
    {
        $vigente = (new RepositorioDemandaPostgres($this->db))->vigente($periodo);
        $fila = $vigente ? $this->db->table('gestion_horarios.demanda_curso')->where('id_demanda', $vigente->id_demanda)->where('id_curso_plan', $curso)->first() : null;
        $habilitado = $fila && $this->habilitado($periodo) && ! $vigente->requiere_revision
            && ! $this->db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->exists();
        $ultimo = $fila && $this->habilitado($periodo) ? $this->ultimo((int) $vigente->id_demanda, $curso) : null;
        return ['id_demanda' => $fila ? (int) $vigente->id_demanda : null, 'id_ajuste' => $ultimo ? (int) $ultimo->id_ajuste : null,
            'original' => $fila ? (int) $fila->total : null, 'efectiva' => $ultimo ? (int) $ultimo->total_ajustado : ($fila ? (int) $fila->total : null), 'habilitado' => (bool) $habilitado];
    }

    public function guardar(User $usuario, int $periodo, int $oferta, ?int $version, ?int $ajuste, mixed $total, string $motivo): void
    {
        (new AutorizacionGestionAcademica)->exigir($usuario);
        $datos = Validator::make(['total'=>$total, 'motivo'=>trim($motivo)], ['total'=>['required','integer','min:0','max:2147483647'], 'motivo'=>['required','string','max:1000']])->validate();
        $fuente = $this->fuente();
        if (! $fuente) self::rechazar('Ajuste no habilitado en este entorno.');
        (new RepositorioDemandaPostgres($this->db))->conBloqueo($periodo, function () use ($usuario,$periodo,$oferta,$version,$ajuste,$datos,$fuente) {
            $fuente->conBloqueo(function () use ($usuario,$periodo,$oferta,$version,$ajuste,$datos,$fuente) {
                if (! $fuente->confirmado($periodo)) self::rechazar('Prematrícula todavía no ha culminado oficialmente.');
                $this->db->statement('LOCK TABLE gestion_horarios.horario IN SHARE MODE');
                $o = $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo',$periodo)->where('id_oferta',$oferta)->lockForUpdate()->first();
                if (! $o || $o->tipo_oferta !== 'REGULAR' || $o->estado === 'CERRADA') self::rechazar('Solo se ajustan ofertas regulares abiertas.');
                (new AmbitoEscrituraAcademica($this->db))->autorizarCurso($usuario,(int)$o->id_curso_plan);
                if ($this->db->table('gestion_horarios.oferta_curso')->where('id_periodo',$periodo)->where('id_curso_plan',$o->id_curso_plan)->count() !== 1) self::rechazar('Hay varias ofertas; se requiere revisión de la distribución de demanda.');
                $actual = $this->detalle($periodo,(int)$o->id_curso_plan);
                if (! $actual['habilitado'] || $actual['id_demanda'] !== $version || $actual['id_ajuste'] !== $ajuste) self::rechazar('La demanda cambió o existe un horario. Abra nuevamente el formulario.');
                $repo = new RepositorioDemandaPostgres($this->db);
                $ultima = $repo->ultima($periodo);
                $captura = InstantaneaDemanda::desdeArray(['periodo'=>$periodo,'version'=>$ultima['version'],'cursos'=>$fuente->cursos($periodo)]);
                if (! hash_equals($ultima['hash'],$captura->hash())) self::rechazar('Falta sincronizar la demanda final de Prematrícula.');
                if ((int)$datos['total'] === $actual['efectiva']) return;
                $this->db->table('gestion_horarios.ajuste_demanda')->insert(['id_demanda'=>$version,'id_curso_plan'=>$o->id_curso_plan,'total_anterior'=>$actual['efectiva'],'total_ajustado'=>(int)$datos['total'],'motivo'=>$datos['motivo'],'id_usuario'=>$usuario->getKey()]);
            });
        });
    }

    private static function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['demandaFormulario'=>$mensaje]);
    }
}
