# Arquitectura del proyecto

## 1. Visión general

Portal Gestión Dirección usa Laravel 13 como monolito modular. La aplicación se despliega como una sola unidad, pero separa el dominio en 12 módulos dentro de `app/Modules`.

Cada módulo encapsula sus controladores, validaciones, modelos, servicios, políticas y rutas. El código transversal vive en `app/Shared`. PostgreSQL usa un esquema independiente por módulo y conserva datos comunes en `public`.

## 2. Estructura

```text
app/
├── Modules/
│   └── NombreModulo/
│       ├── Controllers/
│       ├── Models/
│       ├── Policies/
│       ├── Requests/
│       ├── Routes/
│       │   ├── api.php
│       │   └── web.php
│       └── Services/
└── Shared/
    ├── Controllers/
    ├── Models/
    ├── Policies/
    ├── Requests/
    ├── Routes/
    └── Services/

resources/views/modules/{modulo-kebab-case}/
routes/{web,api}.php
config/modules.php
database/migrations/
```

Los namespaces siguen PSR-4 bajo `App\Modules\{NombreModulo}`. Los nombres de directorio y clases usan PascalCase; las URLs, nombres de ruta y directorios de vistas usan kebab-case.

## 3. Responsabilidades por capa

- **Routes:** definen prefijos, nombres y middleware del módulo. No contienen lógica de negocio.
- **Controllers:** reciben la petición, coordinan servicios y producen respuestas HTTP.
- **Requests:** autorizan la operación y validan la entrada.
- **Services:** contienen casos de uso y límites transaccionales. No conocen HTTP ni vistas.
- **Models:** representan persistencia Eloquent. Todo modelo modular declara una tabla calificada, por ejemplo `gestion_horarios.horarios`.
- **Policies:** expresan autorización por recurso mediante roles y permisos.
- **Shared:** contiene abstracciones realmente reutilizables. No debe acumular reglas exclusivas de un módulo.

Un módulo puede usar `app/Shared` y contratos públicos de otro módulo. No debe acceder directamente a controladores, vistas o tablas internas de otro módulo. Una dependencia entre módulos debe ser explícita y evitar ciclos.

## 4. Flujo HTTP y rutas

```text
Petición
  -> public/index.php
  -> bootstrap/app.php
  -> routes/web.php o routes/api.php
  -> Routes/{web,api}.php del módulo
  -> middleware y FormRequest
  -> Controller
  -> Service
  -> Model/PostgreSQL
  -> respuesta Blade o JSON
```

`routes/web.php` y `routes/api.php` actúan como puntos de entrada. Ambos cargan de forma explícita los archivos de rutas de los 12 módulos. Laravel aplica automáticamente el prefijo `/api` a las rutas registradas por `routes/api.php`.

Los alias `role`, `permission` y `role_or_permission` se registran en `bootstrap/app.php`. No se usa `App\Http\Kernel`.

## 5. Módulos y esquemas PostgreSQL

`config/modules.php` es el inventario central de módulos y nombres de esquema.

| Módulo | URL base | Esquema |
| --- | --- | --- |
| Pre Matrícula | `pre-matricula` | `pre_matricula` |
| Bienestar | `bienestar` | `bienestar` |
| Investigación Formativa | `investigacion-formativa` | `investigacion_formativa` |
| Seguimiento al Estudiante | `seguimiento-estudiante` | `seguimiento_estudiante` |
| Web Institucional | `web-institucional` | `web_institucional` |
| Calidad | `calidad` | `calidad` |
| Gestión de Horarios | `gestion-horarios` | `gestion_horarios` |
| COTECU | `cotecu` | `cotecu` |
| Gestión de Prácticas | `gestion-practicas` | `gestion_practicas` |
| Tesis y Proyecto de Tesis | `tesis-proyecto-tesis` | `tesis_proyecto_tesis` |
| Gestión de Matrículas | `gestion-matriculas` | `gestion_matriculas` |
| Seguimiento al Egresado | `seguimiento-egresado` | `seguimiento_egresado` |

Las tablas compartidas, como `users`, `roles` y `permissions`, pertenecen al esquema `public`. Las tablas de dominio pertenecen al esquema de su módulo.

### Control de cambios de base de datos

- Toda modificación de esquema se realiza mediante una migración nueva. No se modifica una migración ya ejecutada en entornos compartidos.
- La migración `2026_10_28_000000_create_module_schemas.php` crea los esquemas declarados en `config/modules.php`.
- Las migraciones de tablas deben ejecutarse después de la creación de esquemas y usar nombres calificados, por ejemplo `gestion_horarios.horarios`.
- `php artisan migrate` aplica cambios pendientes y registra el lote en `public.migrations`.
- `php artisan migrate:status` muestra el estado. `php artisan migrate:rollback` revierte el último lote cuando su método `down()` es seguro.
- Eliminar el volumen PostgreSQL destruye los datos persistidos. No se usa `docker compose down -v` en un entorno con datos que deban conservarse.

## 6. Autenticación y autorización

Laravel Breeze administra registro, inicio de sesión, recuperación de contraseña y sesiones. `spatie/laravel-permission` administra roles y permisos en tablas compartidas del esquema `public`.

Cada operación protegida debe aplicar defensa en profundidad:

1. Middleware `auth` valida la sesión.
2. Middleware de Spatie puede restringir una ruta completa.
3. Form Request o Controller autoriza la acción mediante una Policy.
4. Service ejecuta únicamente el caso de uso ya autorizado.

Los nombres de permisos deben ser estables y contener el módulo y la acción, por ejemplo `gestion-horarios.view`.

## 7. Arquitectura Docker

`docker-compose.yml` define dos servicios:

- **app:** aplicación Laravel con Apache. Expone `http://localhost:8000` y conecta internamente a `postgresql:5432`.
- **postgresql:** PostgreSQL 17. Expone `localhost:5433` para evitar colisión con PostgreSQL local en `5432`.

El volumen `app_postgresql_data` persiste la base de datos fuera del ciclo de vida del contenedor. El servicio `app` espera el healthcheck de PostgreSQL y ejecuta `php artisan migrate --force` antes de iniciar Apache.

Comandos habituales:

```bash
docker compose up -d --build
docker compose ps
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
docker compose logs app postgresql
docker compose down
```

La carpeta `docs` permanece en Git, pero `.dockerignore` la excluye de la imagen porque no es necesaria en ejecución.

## 8. Agregar un módulo

1. Definir nombre, slug y esquema único en `config/modules.php`.
2. Crear el directorio `app/Modules/{NombreModulo}` y sus seis capas.
3. Crear rutas web y API con prefijo y nombres únicos.
4. Incluir esas rutas en los puntos de entrada globales.
5. Crear la vista en `resources/views/modules/{slug}`.
6. Crear una migración nueva para las tablas del esquema.
7. Declarar tablas Eloquent con el esquema explícito.
8. Definir permisos, Policy y middleware necesarios.
9. Agregar pruebas de rutas, autorización, validación, servicio y aislamiento de esquema.
10. Ejecutar migraciones y la suite completa dentro de Docker.

## 9. Pruebas y criterios

- Las pruebas de rutas verifican que cada módulo expone endpoints web y API con nombres únicos.
- Las pruebas de aislamiento verifican que cada modelo apunta a su propio esquema.
- La prueba PostgreSQL confirma que los 12 esquemas configurados existen.
- Cada funcionalidad nueva debe incluir pruebas felices, validación, autorización y errores esperados.

## 10. Limitaciones actuales

- Los servicios modulares son scaffolds y todavía devuelven los datos recibidos sin lógica de negocio.
- Las clases `Registro` apuntan a tablas `registros`, pero aún no existen migraciones para crear esas tablas.
- Las Policies están definidas, pero todavía no están aplicadas a las rutas o acciones de los módulos.
- Las rutas modulares actuales no exigen autenticación ni permisos.
- Las vistas modulares son páginas base y aún no implementan operaciones completas de dominio.
- La carga de rutas es explícita; agregar un módulo requiere actualizar ambos archivos globales de rutas.

Estas limitaciones deben resolverse por funcionalidad. No se deben ocultar con lógica genérica en `Shared`.
