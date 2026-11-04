# Guía para trabajar en un módulo

Cada módulo es independiente. Esta guía explica cómo desarrollar vistas, rutas, modelos, migraciones y esquema de un módulo **sin modificar otros módulos**. Contexto general: [docs/architecture.md](docs/architecture.md) y [docs/base_de_datos.md](docs/base_de_datos.md). Ramas y commits: [docs/PC-Convenciones-Git.md](docs/PC-Convenciones-Git.md).

## 1. Qué es tuyo y qué no

Ejemplo con el módulo `Bienestar` (slug `bienestar`, esquema `bienestar`):

| Recurso | Ruta | Tuyo |
| --- | --- | --- |
| Controladores | `app/Modules/Bienestar/Controllers/` | Sí |
| Modelos | `app/Modules/Bienestar/Models/` | Sí |
| Servicios | `app/Modules/Bienestar/Services/` | Sí |
| Requests | `app/Modules/Bienestar/Requests/` | Sí |
| Policies | `app/Modules/Bienestar/Policies/` | Sí |
| Rutas | `app/Modules/Bienestar/Routes/web.php` y `api.php` | Sí |
| Migraciones | `app/Modules/Bienestar/Database/Migrations/` | Sí |
| Vistas | `resources/views/modules/bienestar/` | Sí |
| Pruebas | `tests/Feature/Bienestar/`, `tests/Unit/Bienestar/` | Sí |
| Esquema PostgreSQL | `bienestar` | Sí |
| Otro módulo (`app/Modules/*`, sus vistas, su esquema) | | **No** |
| `app/Shared/**` | | Solo con acuerdo del equipo |
| `core`, `seguridad`, `comun` | | Leer sí; modificar solo vía `app/Shared/Database/Migrations` con acuerdo |
| `config/modules.php`, `routes/web.php`, `routes/api.php` | | No (ya incluyen los 12 módulos) |

Reglas fijas:

1. Un módulo no importa clases de otro (`App\Modules\Otro\...`). El test `tests/Unit/ModuleStructureTest.php` falla si ocurre.
2. No hay FK hacia el esquema de otro módulo. Si necesitas relacionar datos, guarda el id como referencia lógica (sin FK).
3. Los modelos de tablas compartidas se reutilizan desde `App\Shared\Models\Core\...` y `App\Shared\Models\Seguridad\...`.
4. No se crean archivos de tu módulo fuera de su carpeta (nada en `app/Http/Controllers`, `app/Models` ni `database/migrations`).

## 2. Preparar el entorno

Sigue el [README](README.md) hasta tener `app` y `postgresql` activos. Crea tu rama según la convención de Git (por ejemplo `feature/bienestar-citas`).

Como el código se copia dentro de la imagen, tras cada cambio PHP/Blade debes reconstruir:

```bash
docker compose up -d --build app
```

Para trabajar con recarga inmediata, usa la instalación local del README (`composer run dev`) conectada a PostgreSQL en el puerto `5433`.

## 3. Esquema y migraciones

El esquema del módulo ya existe con sus tablas base. Para cambiarlo, crea **una migración nueva** en la carpeta de tu módulo.

Nombre: `AAAA_MM_DD_NNNNNN_create_nombre_table.php` (o `add_...`/`alter_...`).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bienestar.nota_cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cita')->constrained('bienestar.cita', 'id_cita');   // mismo esquema
            $table->foreignId('id_usuario')->constrained('seguridad.usuario', 'id_usuario'); // esquema compartido
            $table->text('detalle');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bienestar.nota_cita');
    }
};
```

Reglas:

- Siempre nombres calificados: `bienestar.tabla`, nunca `tabla` suelta.
- Las FK solo apuntan a tu esquema, `core`, `seguridad`, `comun` o `public`.
- `down()` obligatorio y simétrico.
- Nunca edites una migración ya aplicada: crea otra.
- El timestamp debe ser posterior al de las tablas que referencias.

Aplicar y revisar:

```bash
docker compose exec app php artisan module:migrate bienestar
docker compose exec app php artisan module:migrate:status bienestar
```

`module:migrate bienestar` aplica antes lo compartido y después solo tu módulo. Las migraciones de otros módulos no se ejecutan.

## 4. Modelos

Extiende `ModuleModel` y declara siempre la tabla calificada y su clave primaria:

```php
<?php

namespace App\Modules\Bienestar\Models;

use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\ModuleModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Cita extends ModuleModel
{
    protected $table = 'bienestar.cita';

    protected $primaryKey = 'id_cita';

    public $timestamps = false;

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }
}
```

Relaciones hacia `core`/`seguridad` usan los modelos de `App\Shared\Models`. Si falta un modelo compartido, se propone al equipo agregarlo en `app/Shared/Models`; no lo copies dentro de tu módulo.

## 5. Rutas

Tu módulo tiene dos archivos ya cargados por `routes/web.php` y `routes/api.php`; solo los editas, no tocas los globales.

`app/Modules/Bienestar/Routes/web.php`:

```php
use App\Modules\Bienestar\Controllers\BienestarController;
use Illuminate\Support\Facades\Route;

Route::prefix('bienestar')
    ->name('bienestar.')
    ->middleware('auth')
    ->group(function () {
        Route::get('/', [BienestarController::class, 'index'])->name('index');
        Route::get('/citas', [BienestarController::class, 'citas'])->name('citas');
    });
```

`app/Modules/Bienestar/Routes/api.php` usa `->name('api.bienestar.')`; Laravel agrega el prefijo `/api` por sí solo.

Reglas:

- Prefijo de URL y nombre de ruta empiezan por el slug del módulo (`bienestar`, `api.bienestar`). Así no chocan con otros módulos.
- Sin lógica de negocio en el archivo de rutas.
- Nombres de permisos con el formato `modulo.accion`, por ejemplo `bienestar.view`.

Verificar:

```bash
docker compose exec app php artisan route:list --path=bienestar
```

## 6. Controladores, Requests, Services y Policies

Flujo: `Route → Request → Controller → Service → Model`.

- **Controller** (`Controllers/`): delgado. Recibe el Request, llama al Service, devuelve vista o JSON. Puede extender `App\Shared\Controllers\ModuleController`, que ya resuelve `index`, `create` y `apiIndex` con las constantes `MODULE`, `SCHEMA` y `VIEW`.
- **Request** (`Requests/`): validación y `authorize()`.
- **Service** (`Services/`): casos de uso y transacciones (`DB::transaction`). No conoce HTTP ni vistas.
- **Policy** (`Policies/`): autorización por recurso.

Ejemplo mínimo de un caso de uso:

```php
final class CitaService
{
    public function agendar(array $datos): Cita
    {
        return DB::transaction(fn () => Cita::create($datos));
    }
}
```

## 7. Vistas

Tus vistas viven solo en `resources/views/modules/bienestar/`. Se llaman con el prefijo `modules.bienestar.`:

```php
return view('modules.bienestar.citas.index', ['citas' => $citas]);
```

Estructura recomendada:

```text
resources/views/modules/bienestar/
├── index.blade.php
├── citas/
│   ├── index.blade.php
│   └── create.blade.php
└── partials/
```

- Usa el layout de la aplicación con `<x-app-layout>` para conservar navegación y estilos.
- Los componentes propios del módulo van en `resources/views/modules/bienestar/partials/`.
- Un componente que usarán varios módulos se propone para `resources/views/components/` con acuerdo del equipo.
- No incluyas (`@include`) vistas de otro módulo.
- Estilos y JS: usa Tailwind y Vite del proyecto. Si necesitas assets propios, ponlos bajo un nombre con tu prefijo para no pisar otros.

Cambios de frontend requieren `npm run build` (o `npm run dev` en local) y reconstruir la imagen Docker.

## 8. Pruebas

Agrega pruebas en carpetas con el nombre de tu módulo:

```text
tests/Feature/Bienestar/CitasTest.php
tests/Unit/Bienestar/CitaServiceTest.php
```

Cubre caso feliz, validación, autorización y errores esperados. Ejecuta desde una instalación local (la imagen Docker no incluye PHPUnit):

```bash
vendor/bin/phpunit --filter=Bienestar
vendor/bin/phpunit tests/Unit/ModuleStructureTest.php   # aislamiento entre módulos
```

## 9. Revisión antes del commit

- [ ] Todos los archivos nuevos están dentro de `app/Modules/<TuModulo>/`, `resources/views/modules/<slug>/` o `tests/**/<TuModulo>/`.
- [ ] `git status` no muestra cambios en otros módulos ni en `app/Shared`, salvo acuerdo.
- [ ] Migraciones con nombres calificados, `down()` y FK solo a esquemas permitidos.
- [ ] `module:migrate <slug>` corre dos veces sin error (segunda vez: "Nothing to migrate").
- [ ] `ModuleStructureTest` pasa.
- [ ] El commit sigue el formato `<tipo>(<modulo>): <resumen>`; para migraciones usa el tipo `migration`. Ejemplo: `migration(bienestar): agrega tabla nota_cita`.

## 10. Necesito algo de otro módulo

No lo modifiques ni lo importes. Opciones, en orden de preferencia:

1. Si es un dato institucional (persona, curso, período, usuario), léelo de `core`/`seguridad`/`comun` con los modelos de `App\Shared\Models`.
2. Si es un dato de otro módulo, guarda su id como referencia lógica y pídelo a través de un contrato acordado con ese equipo.
3. Si hace falta cambiar un esquema compartido, abre un PR separado contra `app/Shared/Database/Migrations` y avisa al equipo.
