<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use App\Shared\Models\ModuleModel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Persona del personal de la Escuela que figura como autora de registros (campañas, estados académicos, mejoras...).
 * Se enlaza con la cuenta del portal por el correo (correo_institucional = email de la cuenta); la contraseña y los
 * roles los administra el portal.
 */
class UsuarioInterno extends ModuleModel
{
    protected $table = 'seguimiento_egresado.usuario_interno';

    public $timestamps = false;

    protected $fillable = [
        'id_rol',
        'nombre_completo',
        'correo_institucional',
        'password_hash',
        'activo',
        'creado_en',
    ];

    protected $hidden = ['password_hash'];

    public function rol()
    {
        return $this->belongsTo(RolInterno::class, 'id_rol');
    }

    /** Fila del módulo que corresponde a un usuario del portal; se crea la primera vez que lo usa. */
    public static function delPortal(Authenticatable $usuario, Perfil $perfil): self
    {
        $existente = static::whereRaw('lower(correo_institucional) = ?', [mb_strtolower($usuario->email)])->first();

        if ($existente !== null) {
            return $existente;
        }

        return static::create([
            'id_rol' => RolInterno::where('codigo', $perfil->value)->value('id'),
            'nombre_completo' => $usuario->name ?? $usuario->email,
            'correo_institucional' => $usuario->email,
            'password_hash' => '!portal',
            'activo' => true,
        ]);
    }

    /**
     * Id con el que se registra la autoría de lo que se hace ahora: el del usuario del portal con sesión iniciada y
     * rol de Secretaría o Dirección. Sin sesión (consola, semillas) se usa el primer usuario activo del módulo.
     */
    public static function idActual(): ?int
    {
        $usuario = auth()->user();

        if ($usuario !== null && method_exists($usuario, 'getRoleNames')) {
            $perfil = Perfil::desdeRolesDelPortal($usuario->getRoleNames()->all());

            if ($perfil !== null) {
                return static::delPortal($usuario, $perfil)->id;
            }
        }

        $id = static::where('activo', true)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
