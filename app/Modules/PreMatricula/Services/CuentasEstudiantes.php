<?php

namespace App\Modules\PreMatricula\Services;

use App\Models\User;
use App\Modules\PreMatricula\Models\LimiteCreditosEstudiante;
use App\Modules\PreMatricula\Policies\PrematriculaPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Cuentas de estudiantes, como AccountController y el registro de la rama master.
 * En el portal una cuenta de estudiante une public.users (rol alumno), seguridad.usuario,
 * core.persona, core.estudiante y su límite de créditos.
 */
final class CuentasEstudiantes
{
    public const ACTIVO = 'Activo';

    public const INACTIVO = 'Inactivo';

    /** Tipo de documento con el que se registra la persona cuando solo se conoce su código universitario. */
    public const TIPO_DOCUMENTO = 'CODIGO';

    public function __construct(
        private readonly BloqueoAcademico $bloqueo,
        private readonly PlanEstudios $plan,
    ) {}

    public function listar(?string $buscar): LengthAwarePaginator
    {
        $buscar = trim((string) $buscar);

        return $this->consulta()
            ->when($buscar !== '', function ($query) use ($buscar): void {
                $patron = '%'.addcslashes(mb_strtolower($buscar), '\\%_').'%';
                $query->where(fn ($q) => $q->whereRaw('lower(e.codigo_universitario) like ?', [$patron])
                    ->orWhereRaw('lower(u.correo_institucional) like ?', [$patron])
                    ->orWhereRaw("lower(p.nombres || ' ' || p.apellido_paterno || ' ' || coalesce(p.apellido_materno, '')) like ?", [$patron]));
            })
            ->orderBy('e.codigo_universitario')
            ->paginate(20)
            ->withQueryString();
    }

    public function cuenta(int $idEstudiante): object
    {
        return $this->consulta()->where('e.id_estudiante', $idEstudiante)->first() ?? abort(404);
    }

    /**
     * Crea la cuenta completa del estudiante.
     *
     * @param  array{correo_institucional: string, password: string, codigo_universitario: string, nombres: string, apellidos: string, creditos_maximos?: int|null}  $datos
     */
    public function crear(array $datos): int
    {
        return $this->bloqueo->transaccion(function () use ($datos): int {
            $correo = mb_strtolower(trim($datos['correo_institucional']));
            $codigo = trim($datos['codigo_universitario']);
            $this->exigirUnicos($correo, $codigo);

            $plan = $this->plan->plan();
            $modalidad = DB::table('core.modalidad_ingreso')->where('activo', true)->orderBy('id_modalidad')->value('id_modalidad')
                ?? throw new PrematriculaException('No hay modalidades de ingreso activas en el portal.');
            $periodoIngreso = DB::table('core.periodo_academico')
                ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [PeriodosAcademicos::PREMATRICULA])
                ->orderByDesc('fecha_inicio')
                ->value('id_periodo')
                ?? throw new PrematriculaException('No hay períodos académicos registrados.');

            [$paterno, $materno] = $this->apellidos($datos['apellidos']);
            $idPersona = DB::table('core.persona')->insertGetId([
                'tipo_documento' => self::TIPO_DOCUMENTO,
                'numero_documento' => $codigo,
                'nombres' => trim($datos['nombres']),
                'apellido_paterno' => $paterno,
                'apellido_materno' => $materno,
            ], 'id_persona');

            $idEstudiante = DB::table('core.estudiante')->insertGetId([
                'codigo_universitario' => $codigo,
                'estado' => self::ACTIVO,
                'id_persona' => $idPersona,
                'id_escuela' => $plan->id_escuela,
                'id_plan' => $plan->id_plan,
                'id_periodo_ingreso' => $periodoIngreso,
                'id_modalidad_ingreso' => $modalidad,
            ], 'id_estudiante');

            $hash = Hash::make($datos['password']);
            DB::table('seguridad.usuario')->insert([
                'correo_institucional' => $correo,
                'contrasena_hash' => $hash,
                'estado' => self::ACTIVO,
                'id_persona' => $idPersona,
                'debe_cambiar_contrasena' => false,
            ]);

            $user = User::query()->forceCreate([
                'name' => trim($datos['nombres'].' '.$datos['apellidos']),
                'email' => $correo,
                'password' => $hash,
                'email_verified_at' => now(),
            ]);
            $user->assignRole(Role::findOrCreate(PrematriculaPolicy::ROL_ALUMNO, 'web'));

            if (isset($datos['creditos_maximos'])) {
                LimiteCreditosEstudiante::query()->create(['id_estudiante' => $idEstudiante, 'creditos_maximos' => (int) $datos['creditos_maximos']]);
            }

            return (int) $idEstudiante;
        });
    }

    /**
     * Cambia correo, contraseña o estado de la cuenta (el resto del perfil no se edita, como en master).
     *
     * @param  array{correo_institucional?: string, password?: string, estado?: string}  $datos
     */
    public function actualizar(int $idEstudiante, array $datos): void
    {
        $this->bloqueo->transaccion(function () use ($idEstudiante, $datos): void {
            $cuenta = $this->cuenta($idEstudiante);
            $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($cuenta->correo_institucional)])->first();

            if (isset($datos['correo_institucional'])) {
                $correo = mb_strtolower(trim($datos['correo_institucional']));
                if ($correo !== mb_strtolower($cuenta->correo_institucional)) {
                    $this->exigirCorreoLibre($correo);
                    DB::table('seguridad.usuario')->where('id_usuario', $cuenta->id_usuario)->update(['correo_institucional' => $correo]);
                    $user?->forceFill(['email' => $correo])->save();
                }
            }

            if (isset($datos['password'])) {
                $hash = Hash::make($datos['password']);
                DB::table('seguridad.usuario')->where('id_usuario', $cuenta->id_usuario)->update(['contrasena_hash' => $hash]);
                $user?->forceFill(['password' => $hash, 'remember_token' => null])->save();
            }

            if (isset($datos['estado'])) {
                DB::table('seguridad.usuario')->where('id_usuario', $cuenta->id_usuario)->update(['estado' => $datos['estado']]);
            }
        });
    }

    /** Una cuenta inactiva no puede usar la prematrícula, como el middleware user.active de master. */
    public function activa(?string $correo): bool
    {
        if ($correo === null) {
            return false;
        }

        $estado = DB::table('seguridad.usuario')->whereRaw('lower(correo_institucional) = ?', [mb_strtolower($correo)])->value('estado');

        return $estado === null || mb_strtoupper($estado) !== mb_strtoupper(self::INACTIVO);
    }

    private function consulta(): Builder
    {
        return DB::table('core.estudiante as e')
            ->join('core.persona as p', 'p.id_persona', '=', 'e.id_persona')
            ->join('seguridad.usuario as u', 'u.id_persona', '=', 'e.id_persona')
            ->leftJoin('prematricula.limite_creditos_estudiante as l', 'l.id_estudiante', '=', 'e.id_estudiante')
            ->select([
                'e.id_estudiante', 'e.codigo_universitario', 'e.estado as estado_estudiante',
                'p.nombres', 'p.apellido_paterno', 'p.apellido_materno',
                'u.id_usuario', 'u.correo_institucional', 'u.estado',
            ])
            ->selectRaw('coalesce(l.creditos_maximos, ?) as creditos_maximos', [LimiteCreditosEstudiante::CREDITOS_POR_DEFECTO]);
    }

    private function exigirUnicos(string $correo, string $codigo): void
    {
        $this->exigirCorreoLibre($correo);

        if (DB::table('core.estudiante')->where('codigo_universitario', $codigo)->exists()
            || DB::table('core.persona')->where('tipo_documento', self::TIPO_DOCUMENTO)->where('numero_documento', $codigo)->exists()) {
            throw ValidationException::withMessages(['codigo_universitario' => 'Este código universitario ya está registrado.']);
        }
    }

    private function exigirCorreoLibre(string $correo): void
    {
        if (DB::table('seguridad.usuario')->whereRaw('lower(correo_institucional) = ?', [$correo])->exists()
            || User::query()->whereRaw('lower(email) = ?', [$correo])->exists()) {
            throw ValidationException::withMessages(['correo_institucional' => 'Este correo institucional ya está registrado.']);
        }
    }

    /** @return array{0: string, 1: string|null} apellido paterno y materno */
    private function apellidos(string $apellidos): array
    {
        $partes = preg_split('/\s+/', trim($apellidos), 2) ?: [''];

        return [$partes[0], isset($partes[1]) && $partes[1] !== '' ? $partes[1] : null];
    }
}
