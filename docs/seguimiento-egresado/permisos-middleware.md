# Roles, permisos y middleware · Seguimiento al Egresado

El módulo decide quién puede hacer qué con **capacidades** (permisos con nombre) asignadas a **perfiles**. Los perfiles del personal salen de los **roles del portal** (Spatie); el egresado, que no tiene cuenta, entra con el enlace de su invitación. Un middleware exige la capacidad en cada ruta y una Policy decide sobre el expediente de un egresado concreto. Ocultar una opción del menú no restringe nada: la autorización se aplica siempre en el servidor.

## Perfiles

| Perfil | De dónde sale | Cómo se identifica |
|---|---|---|
| `SECRETARIA` | Rol `secretaria` del portal | Sesión del portal (`auth`). |
| `DIRECTOR` | Rol `director_escuela` del portal | Sesión del portal (`auth`). |
| `EGRESADO` | No es rol ni cuenta | Enlace personal de su invitación (`egresado_id` + `invitacion_id`, en la sesión). |

La empresa responde con un token y no tiene perfil. Un usuario del portal con otro rol (por ejemplo `profesor` o `alumno`) no tiene acceso a las pantallas del módulo: recibe 403. Si tuviera varios roles, se usa Secretaría antes que Director.

Código: `App\Modules\SeguimientoEgresado\Support\Accesos\Perfil` (`desdeRolesDelPortal`).

## Matriz de capacidades

Fuente única: `App\Modules\SeguimientoEgresado\Support\Accesos\Capacidades::MATRIZ`. El middleware, las Policies y el menú consultan solo ahí.

| Capacidad | Egresado | Secretaría | Director |
|---|:-:|:-:|:-:|
| `expediente.propio` | ✔ | | |
| `expediente.gestionar` | | ✔ | |
| `expediente.consultar` | | | ✔ |
| `padron.consultar` | | ✔ | ✔ |
| `padron.gestionar` | | ✔ | |
| `campanias.responder` | ✔ | | |
| `campanias.consultar` | | ✔ | ✔ |
| `campanias.administrar` | | ✔ | |
| `seguimiento.propio` | ✔ | | |
| `seguimiento.consultar` | | ✔ | |
| `seguimiento.agregados` | | | ✔ |
| `empresas.resultados` | | ✔ | ✔ |
| `indicadores.consultar` | | ✔ | ✔ |
| `reportes.exportar` | | ✔ | ✔ |
| `institucional.consultar` | | ✔ | ✔ |
| `institucional.registrar` | | ✔ | ✔ |
| `institucional.validar` | | | ✔ |

Una capacidad que no existe se deniega siempre. Para agregar una, se añade a la matriz y a `tests/Unit/SeguimientoEgresado/Accesos/CapacidadesTest.php`.

## Identidades de una petición

Pueden convivir dos, porque la Secretaría abre en su navegador el enlace de un egresado para ver lo que él ve:

- **interna**: el usuario del portal con sesión iniciada y rol de Secretaría o Dirección;
- **egresado**: quien abrió su enlace personal. Se guarda en la sesión (`seguimiento-egresado.identidad`) y vence cuando vence el enlace.

```php
// App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso
$sesion = SesionAcceso::desde($request);
$sesion->interna();                     // ?Identidad  (usuario del portal con rol del módulo)
$sesion->egresado();                    // ?Identidad  (enlace personal vigente)
$sesion->todas();                       // las que existan, la del personal primero
$sesion->iniciarEgresado($resultado);   // al abrir un enlace válido
$identidad->puede('padron.gestionar');  // bool
```

La identidad interna enlaza al usuario del portal con una fila de `seguimiento_egresado.usuario_interno` (se busca por el correo de la cuenta y se crea la primera vez). Esa fila da autoría a los registros: quién creó la campaña, quién cambió un estado académico, etc. (`UsuarioInterno::idActual()`).

## Middleware `RequiereCapacidad`

Se aplica por ruta con una o varias capacidades. Basta **una** de ellas (OR) en **alguna** de las identidades de la petición.

```php
// App\Modules\SeguimientoEgresado\Middleware\RequiereCapacidad
public function handle(Request $request, Closure $next, string ...$capacidades): Response
{
    foreach ($capacidades as $capacidad) {
        if (! Capacidades::existe($capacidad)) {
            throw new \LogicException("La capacidad «{$capacidad}» no existe en la matriz de permisos.");
        }
    }

    $sesion = SesionAcceso::desde($request);
    $identidades = $sesion->todas();

    foreach ($identidades as $identidad) {
        foreach ($capacidades as $capacidad) {
            if ($identidad->puede($capacidad)) {
                return $next($request);
            }
        }
    }

    // Una ruta solo del egresado, sin haber abierto su enlace: se explica cómo entrar en lugar de negar el acceso.
    if ($this->soloDelEgresado($capacidades) && $sesion->egresado() === null) {
        return $this->sinSesion($request, $capacidades);
    }

    if ($identidades === []) {
        return $this->sinSesion($request, $capacidades);
    }

    abort(403, 'No tienes permiso para acceder a este recurso.');
}
```

Respuestas:

| Situación | Respuesta |
|---|---|
| Capacidad mal escrita en la ruta | Error de programación (`LogicException`): falla en voz alta y la ruta no se abre. |
| Sin sesión del portal, ruta interna | `auth` redirige al acceso del portal (`login`). Sin `auth` (peticiones JSON), `401`. |
| Sin enlace, ruta del egresado | Redirige a `seguimiento-egresado.acceso` («abre tu enlace personal»). |
| Usuario del portal sin rol en el módulo | `403`. |
| Con identidad pero sin la capacidad | `403`. |
| Sesión del egresado vencida | Igual que sin enlace. |

### Uso en rutas

Las rutas del módulo están en `app/Modules/SeguimientoEgresado/Routes/web.php`. Las del personal combinan `auth` con la capacidad; las del egresado solo la capacidad.

```php
use App\Modules\SeguimientoEgresado\Middleware\RequiereCapacidad;

$interna = fn (string $capacidad): array => ['auth', RequiereCapacidad::class.':'.$capacidad];
$egresado = fn (string $capacidad): array => [RequiereCapacidad::class.':'.$capacidad];

Route::get('/padron', [PadronController::class, 'index'])->middleware($interna('padron.consultar'));
Route::post('/padron/importar', [PadronController::class, 'importar'])->middleware($interna('padron.gestionar'));
Route::get('/egresado/contacto', [ContactoController::class, 'editar'])->middleware($egresado('expediente.propio'));
// Varias capacidades (OR): RequiereCapacidad::class.':seguimiento.propio,seguimiento.consultar'
```

### Dónde está aplicado

| Zona | Capacidades |
|---|---|
| Padrón y contacto inicial | `padron.consultar` (ver), `padron.gestionar` (importar, guardar, generar enlaces) |
| Campañas e invitaciones | `campanias.consultar` (ver), `campanias.administrar` (crear, editar, emitir, recordatorios) |
| Evaluación institucional | `institucional.consultar` (ver), `institucional.registrar` (Secretaría y Dirección: informes en borrador, cambios propuestos, planes de acción), `institucional.validar` (solo Dirección: validar informes y aprobar, ejecutar, implementar o descartar cambios) |
| Reportes | `reportes.exportar` |
| Encuesta empresarial e indicadores | `empresas.resultados`, `campanias.administrar`, `indicadores.consultar`, `reportes.exportar` |
| Área del egresado | `expediente.propio`, `campanias.responder`, `seguimiento.propio` |
| Portada, entrada por enlace y encuesta pública de la empresa | Sin sesión (la empresa y el egresado entran con su enlace; límite de 30 peticiones por minuto) |

## Policy `ExpedientePolicy`

Decide sobre el expediente de **un** egresado. Es una clase plana (no depende del registro de Gates). El egresado solo alcanza su propio expediente, y el id sale siempre de la sesión, nunca de la URL.

```php
// App\Modules\SeguimientoEgresado\Policies\ExpedientePolicy
public function ver(Identidad $identidad, int $egresadoId): bool
{
    return match ($identidad->perfil) {
        Perfil::Egresado => $this->esPropio($identidad, $egresadoId) && $identidad->puede('expediente.propio'),
        Perfil::Secretaria => $identidad->puede('expediente.gestionar'),
        Perfil::Director => $identidad->puede('expediente.consultar'),
    };
}

public function responderEncuesta(Identidad $identidad, int $egresadoId): bool
{
    return $identidad->perfil === Perfil::Egresado
        && $this->esPropio($identidad, $egresadoId)
        && $identidad->puede('campanias.responder');
}
```

Además tiene `modificar` y `verSeguimiento`. El Director no ve seguimientos individuales, solo agregados. Que la invitación elegida pertenezca al egresado y esté disponible lo exige `EncuestaService` en la consulta (`id_egresado` + `proposito` + estado).

## Relación con los permisos de Spatie

El portal define permisos con el formato `modulo.accion` (por ejemplo `seguimiento-egresado.view` en la Policy base del esqueleto). Este módulo decide por **rol → perfil → capacidad**, así que no depende de que esos permisos existan en la base. Si más adelante el portal asigna permisos de Spatie por capacidad, el único cambio es la implementación de `Identidad::puede()`, que hoy consulta la matriz.

## Pruebas

| Archivo | Qué comprueba |
|---|---|
| `tests/Unit/SeguimientoEgresado/Accesos/CapacidadesTest.php` | La matriz completa y la denegación de capacidades desconocidas. |
| `tests/Unit/SeguimientoEgresado/Accesos/ExpedientePolicyTest.php` | Propio / ajeno / interno por perfil. |
| `tests/Feature/SeguimientoEgresado/Accesos/RequiereCapacidadTest.php` | Sin sesión, 403, redirección, sesión vencida, identidad manipulada, capacidad mal escrita y convivencia de las dos identidades. |
| `tests/Feature/SeguimientoEgresado/Accesos/IdentidadDelPortalTest.php` | Roles del portal a perfiles, usuario interno enlazado y autoría. |
| `tests/Feature/SeguimientoEgresado/SeguimientoShellTest.php` | Sin sesión las pantallas internas llevan al acceso del portal; con sesión se ve la cuenta del portal. |
| `tests/Feature/SeguimientoEgresado/Empresas/PermisosEmpresasIndicadoresTest.php` | Secretaría y Dirección en empresas e indicadores; usuario sin rol: 403. |
| `tests/Feature/SeguimientoEgresado/Invitaciones/ContactoInicialTest.php` | Solo Secretaría genera enlaces (Dirección los consulta y recibe 403 al generar). |
