# Arquitectura del módulo Seguimiento al Egresado

Módulo `seguimiento-egresado` del monolito modular del portal (Laravel 13, Blade, PostgreSQL, esquema `seguimiento_egresado`). Sigue la estructura de `GUIA_MODULOS.md`: todo su código vive en `app/Modules/SeguimientoEgresado/`, sus vistas en `resources/views/modules/seguimiento-egresado/` y sus pruebas en `tests/*/SeguimientoEgresado/`.

## Capas

```
Navegador ──► Routes/web.php ──► Middleware (auth, RequiereCapacidad)
                                      │
                                 Controllers (delgados)
                                      │
                         Services (reglas y transacciones)  ◄── Policies / Requests
                                      │
                         Models / DB (nombres calificados)  ──►  PostgreSQL: seguimiento_egresado
```

- **Controllers:** reciben la petición, piden la identidad y llaman a un servicio. Los controladores del esqueleto extienden `ModuleController`.
- **Services:** reglas y transacciones; no conocen Blade ni la petición.
- **Requests y `Formatos`:** validación de entrada. En la encuesta, el navegador repite las reglas de los campos con formato para avisar al escribir.
- **Models:** extienden `ModuleModel` con la tabla calificada (`seguimiento_egresado.egresado`).
- **Vistas:** Blade con el CSS propio del módulo (`public/css/seguimiento-egresado.css`, clases `sg-*`). El módulo tiene su layout, con la cuenta del portal en la barra superior.

## Mapa de archivos

| Tema | Ubicación |
|---|---|
| Rutas | `Routes/web.php` (más `Routes/api.php` del esqueleto) |
| Identidad y permisos | `Support/Accesos/` (`Perfil`, `Capacidades`, `Identidad`), `Middleware/RequiereCapacidad.php`, `Policies/ExpedientePolicy.php`, `Services/Accesos/SesionAcceso.php` |
| Entrada por enlace | `Services/Accesos/` (`EnlaceInvitacion`, `InvitacionRepositorio`, `InvitacionDb`), `Controllers/Accesos/AccesoController.php`, `resources/views/modules/seguimiento-egresado/acceso/` |
| Área del egresado | `Controllers/Egresado/`, `Services/Egresado/` (`ContactoService`, `EncuestaService`, `HistorialService`), `Requests/`, `resources/views/modules/seguimiento-egresado/egresado/` |
| Padrón y cohortes | `Controllers/PadronController.php`, `Services/PadronImportService.php`, `paginas/padron.blade.php` |
| Contacto inicial y correo | `Controllers/Invitaciones/`, `Services/Invitaciones/`, `Mail/`, `resources/views/.../emails/` |
| Campañas e invitaciones | `Controllers/CampaniasController.php`, `paginas/campanias.blade.php` |
| Evaluación institucional y reportes | `Controllers/InstitucionalController.php`, `Controllers/ReportesController.php` |
| Encuesta empresarial | `Controllers/Empresas/`, `Services/Empresas/` |
| Indicadores I1 a I6 | `Controllers/Indicadores/`, `Services/Indicadores/`, `Services/IndicadoresService.php` |
| Base de datos | `Database/Migrations/`, `Database/Sql/`, `Database/Seeders/` (ver [base-de-datos.md](base-de-datos.md)) |
| Configuración | `config/seguimiento-egresado.php` |
| Assets | `public/css/seguimiento-egresado.css`, `public/js/seguimiento-egresado/` |

## Decisiones de diseño

1. **Dos identidades.** El personal entra con su cuenta del portal (`auth` + rol de Spatie); el egresado, con el enlace de su invitación. Ver [permisos-middleware.md](permisos-middleware.md).
2. **El enlace es la credencial y solo se guarda su hash.** Un volcado de la BD no permite entrar; un enlace perdido se reemplaza.
3. **El expediente sale de la sesión, nunca de la URL.** No hay rutas `/egresado/{id}`.
4. **Datos con historia.** El contacto se versiona; las respuestas de la encuesta son mediciones inmutables.
5. **Una operación, una transacción.** Responder la encuesta guarda todo o nada, y la invitación se bloquea para que dos envíos simultáneos no dupliquen la respuesta.
6. **Estado del correo veraz.** `enviado_en` solo se marca con un envío real; el enlace siempre se muestra en pantalla (ver [correo.md](correo.md)).
7. **Nombres calificados y reglas de validación con conexión.** Las consultas usan `seguimiento_egresado.<tabla>`. Las reglas `exists`/`unique` leen `a.b` como «conexión.tabla», por lo que se construyen con `Support\Esquema::validar()`.
8. **Autoría real.** Quién crea o registra algo queda en `usuario_interno`, enlazado con la cuenta del portal.

## Aislamiento entre módulos

- Ningún archivo del módulo importa clases de otro módulo (`ModuleStructureTest`).
- Las claves foráneas solo apuntan a `seguimiento_egresado`, `core` y `public`.
- No se modificó `app/Shared`. Los únicos archivos compartidos tocados son los de conteo de tablas de los tests de estructura y `docs/base_de_datos.md` (ver [base-de-datos.md](base-de-datos.md)).

## Dependencias del esqueleto del portal que se conservan

`SeguimientoEgresadoController` (portada y ruta `index`, `create`, `store`), `Registro`, `RegistroPolicy`, `SeguimientoEgresadoRequest`, `SeguimientoEgresadoService` y `Routes/api.php` siguen como los entregó el portal: `ModularRouteTest` y `ModuleSchemaIsolationTest` dependen de ellos. La portada (`/seguimiento-egresado`) es pública y muestra el nombre y el esquema del módulo.

## Pruebas

| Grupo | Dónde | Motor |
|---|---|---|
| Matriz de permisos y Policy | `tests/Unit/SeguimientoEgresado/Accesos/` | Sin BD |
| Entrada, permisos e identidad | `tests/Feature/SeguimientoEgresado/Accesos/` | PostgreSQL |
| Área del egresado, servicios y validaciones | `tests/Feature/SeguimientoEgresado/Egresado/`, `Seguimiento/` | PostgreSQL |
| Contacto inicial, correo, campañas, padrón, reportes | `Invitaciones/`, `Campanias/`, `Flujo/`, `PadronIntegrationTest`, `CampaniasInstitucionalReportesTest` | PostgreSQL |
| Empresas e indicadores | `Empresas/`, `Indicadores/` | PostgreSQL |
| Seeders de demostración | `Seeders/` | PostgreSQL |

Las pruebas del módulo corren cada una dentro de una transacción que se revierte (`Tests\Support\SeguimientoEgresado\UsaBaseDeDatos`) y se omiten si la conexión no es PostgreSQL o si falta el esquema. Cómo ejecutarlas: [README.md](README.md).

## Extensión prevista

- **Cola de correo.** Mover el envío a un trabajo en cola y marcar `enviado_en` desde allí (ver [correo.md](correo.md)).
- **Permisos de Spatie por capacidad**, si el portal los define, sustituyendo `Identidad::puede()`.
- **Tablas compartidas** (`seguridad.usuario`, `core.plan_estudios`, `comun.archivo`) en lugar de `usuario_interno`, `plan_curricular` y `archivo_evidencia` del módulo.
