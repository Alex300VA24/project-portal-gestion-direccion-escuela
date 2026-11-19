<?php

namespace App\Modules\GestionPracticas\Services;

use App\Models\User;
use App\Modules\GestionPracticas\Support\Acceso;
use App\Modules\GestionPracticas\Support\Actor;
use App\Shared\Models\Core\Persona;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Empresas que publican convocatorias. La empresa es un dato compartido (comun.empresa);
 * el módulo solo guarda quién la representa en gestion_practicas.empresa_usuario.
 */
final class EmpresaService
{
    /**
     * El administrador registra una empresa y crea su cuenta de acceso.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(Actor $admin, array $datos): int
    {
        $idAdmin = $admin->requiereUsuario();
        $correo = mb_strtolower($datos['correo_acceso']);

        $correoUsado = User::where('email', $correo)->exists()
            || Usuario::query()->whereRaw('lower(correo_institucional) = ?', [$correo])->exists();

        if ($correoUsado) {
            throw ValidationException::withMessages(['correo_acceso' => 'Ese correo ya tiene una cuenta.']);
        }

        if (DB::table('comun.empresa')->where('ruc', $datos['ruc'])->exists()) {
            throw ValidationException::withMessages(['ruc' => 'Ya existe una empresa con ese RUC.']);
        }

        return DB::transaction(function () use ($datos, $correo, $idAdmin): int {
            $persona = Persona::query()
                ->where('tipo_documento', 'DNI')->where('numero_documento', $datos['contacto_dni'])->first();

            if ($persona !== null && Usuario::query()->where('id_persona', $persona->id_persona)->exists()) {
                throw ValidationException::withMessages(['contacto_dni' => 'Esa persona ya tiene una cuenta de usuario.']);
            }

            $idPersona = $persona->id_persona ?? Persona::create([
                'tipo_documento' => 'DNI',
                'numero_documento' => $datos['contacto_dni'],
                'nombres' => $datos['contacto_nombres'],
                'apellido_paterno' => $datos['contacto_apellido_paterno'],
                'correo_personal' => $correo,
                'telefono' => $datos['telefono'] ?? null,
            ])->id_persona;

            $idEmpresa = DB::table('comun.empresa')->insertGetId([
                'ruc' => $datos['ruc'],
                'razon_social' => $datos['razon_social'],
                'nombre_comercial' => $datos['nombre_comercial'] ?? null,
                'direccion' => $datos['direccion'] ?? null,
                'telefono' => $datos['telefono'] ?? null,
                'correo' => $datos['correo_contacto'] ?? $correo,
                'pagina_web' => $datos['pagina_web'] ?? null,
                'estado' => 'Activo',
            ], 'id_empresa');

            DB::table('comun.contacto_empresa')->insert([
                'id_empresa' => $idEmpresa,
                'id_persona' => $idPersona,
                'cargo' => 'Representante',
                'correo_laboral' => $correo,
                'telefono_laboral' => $datos['telefono'] ?? null,
            ]);

            $idUsuario = Usuario::create([
                'id_persona' => $idPersona,
                'correo_institucional' => $correo,
                'contrasena_hash' => Hash::make($datos['password']),
                'estado' => 'Activo',
                'debe_cambiar_contrasena' => true,
            ])->id_usuario;

            $login = User::create([
                'name' => $datos['nombre_comercial'] ?? $datos['razon_social'],
                'email' => $correo,
                'password' => $datos['password'],
            ]);
            $login->forceFill(['email_verified_at' => now()])->save();
            $login->assignRole(Acceso::EMPRESA);

            DB::table('gestion_practicas.empresa_usuario')->insert([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'es_principal' => true,
                'estado' => 'activo',
                'asignado_por' => $idAdmin,
            ]);

            return (int) $idEmpresa;
        });
    }

    /** La empresa completa o actualiza su perfil (el RUC y la razón social no se editan aquí). @param array<string, mixed> $datos */
    public function actualizarPerfil(int $idEmpresa, array $datos): void
    {
        DB::table('comun.empresa')->where('id_empresa', $idEmpresa)->update(
            array_intersect_key($datos, array_flip(['nombre_comercial', 'direccion', 'telefono', 'correo', 'pagina_web'])),
        );
    }

    public function cambiarEstado(int $idEmpresa, bool $activa): void
    {
        DB::transaction(function () use ($idEmpresa, $activa): void {
            DB::table('comun.empresa')->where('id_empresa', $idEmpresa)
                ->update(['estado' => $activa ? 'Activo' : 'Inactivo']);
            DB::table('gestion_practicas.empresa_usuario')->where('id_empresa', $idEmpresa)
                ->update(['estado' => $activa ? 'activo' : 'inactivo']);
        });
    }

    public function estaActiva(int $idEmpresa): bool
    {
        return mb_strtolower((string) DB::table('comun.empresa')->where('id_empresa', $idEmpresa)->value('estado')) === 'activo';
    }
}
