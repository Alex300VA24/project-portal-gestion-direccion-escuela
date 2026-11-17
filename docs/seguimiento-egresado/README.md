# Seguimiento al Egresado

Módulo `seguimiento-egresado` del portal (esquema PostgreSQL `seguimiento_egresado`). Registra el padrón de egresados, convoca campañas de seguimiento por enlace personal, recoge la encuesta del egresado y de las empresas, y calcula los indicadores I1 a I6 y los formatos F1 a F11.

## Documentos

| Documento | Para quién | Contenido |
|---|---|---|
| [guion-demostracion.md](guion-demostracion.md) | Presentación | Recorrido completo paso a paso, con datos, comandos y resultados esperados. |
| [manual-usuario.md](manual-usuario.md) | Secretaría, Dirección, egresados | Cómo usar cada pantalla. |
| [flujo.md](flujo.md) | Pruebas y revisión | Flujos del sistema con sus reglas y lo que queda en la BD. |
| [arquitectura.md](arquitectura.md) | Desarrollo | Capas, archivos, decisiones y pruebas. |
| [permisos-middleware.md](permisos-middleware.md) | Desarrollo | Roles, capacidades y middleware, con código. |
| [base-de-datos.md](base-de-datos.md) | Desarrollo y despliegue | Migraciones del esquema, qué cambió respecto a la primera versión y cómo verificarlo. |
| [correo.md](correo.md) | Despliegue | Envío de los enlaces por correo y qué decidir para producción. |

## Poner en marcha

Con la instalación del portal ya hecha (README principal):

```bash
# 1. Esquema del módulo (la primera vez, o con `module:migrate all`)
php artisan module:migrate seguimiento-egresado

# 2. Cuentas de demostración del portal (roles `secretaria` y `director_escuela`)
php artisan db:seed            # con APP_ENV=production (Docker) hay que agregar --force

# 3. Datos de demostración del módulo (opcional; solo en un entorno local, no en production)
php artisan db:seed --class="App\Modules\SeguimientoEgresado\Database\Seeders\SeguimientoEgresadoSeeder"
```

Las cuentas son `secretaria@portal.local` y `director@portal.local`; su contraseña está en `database/seeders/DatabaseSeeder.php`.

**Para el recorrido de demostración** (alumno apto → contacto → egreso → campaña → encuesta → empleador → indicadores) **no se ejecuta el paso 3**: ese seeder carga egresados y respuestas ficticias que se mezclan con los resultados. El recorrido parte de una base vacía con el CSV de `datos/alumnos-aptos-demo.csv`; ver [guion-demostracion.md](guion-demostracion.md). Si ya se cargó el paso 3, se vuelve a una base limpia con `php artisan module:migrate all --force` sobre una BD nueva (no usar `migrate:fresh`).

El módulo vive en `/seguimiento-egresado`. La portada es pública; las pantallas del personal piden iniciar sesión en el portal.

### Enlaces de egresados de demostración

El egresado no tiene cuenta: entra con el enlace de su invitación.

| Caso | Enlace |
|---|---|
| Contacto pendiente y una encuesta por responder | `/seguimiento-egresado/acceso/demo-contacto-valeria-4F7K2M9Q` |
| Contacto completo y encuesta pendiente | `/seguimiento-egresado/acceso/demo-encuesta-renzo-8H3T5P1W` |
| Todo completo, con historial | `/seguimiento-egresado/acceso/demo-historial-camila-6B9X2D7R` |

Para ver «Invitar a la cohorte»: Padrón → Contacto inicial de egresados → cohorte `DEMO-2020-I` (tres egresados sin contacto).

## Configuración

Variables opcionales del `.env` (detalle en [correo.md](correo.md)):

| Variable | Por defecto | Para qué |
|---|---|---|
| `SG_CORREO_ENVIAR` | `true` | Apaga el envío de correos (los enlaces siguen saliendo en pantalla). |
| `SG_CORREO_REDIRIGIR_A` | vacío | Manda todo el correo a una dirección de pruebas. Obligatorio con datos de demostración. |
| `SG_DIAS_CONTACTO_INICIAL` | `30` | Vigencia del enlace de contacto inicial. |

Con `MAIL_MAILER=log` (valor por defecto del portal) no sale ningún correo: el mensaje queda en `storage/logs`.

## Pruebas

Las pruebas del módulo usan PostgreSQL y el esquema que crea `module:migrate`; con la configuración por defecto (SQLite) se omiten solas. Usar una **base de pruebas distinta** de la de trabajo: las pruebas de autenticación del portal recrean las tablas de `public`.

```bash
# Crear y migrar la base de pruebas (una vez)
DB_DATABASE=portal_prueba php artisan module:migrate all --force

# Solo el módulo
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=portal_prueba DB_USERNAME=postgres DB_PASSWORD=postgres \
vendor/bin/phpunit tests/Feature/SeguimientoEgresado tests/Unit/SeguimientoEgresado

# Todo el portal
DB_CONNECTION=pgsql ... vendor/bin/phpunit
```
