# Portal Gestión Dirección

Aplicación Laravel 13 organizada como monolito modular. Usa PostgreSQL 17, un esquema por módulo, Laravel Breeze y `spatie/laravel-permission`.

## Documentación

- [Arquitectura del proyecto](docs/architecture.md)

## Instalación recomendada con Docker

Docker instala PHP, Apache, Composer, Node.js y PostgreSQL dentro de las imágenes. En el equipo solo se requieren:

- Git.
- Docker Desktop con Docker Compose v2.
- Puertos `8000` y `5433` disponibles.

### 1. Clonar el repositorio

```bash
git clone https://github.com/Alex300VA24/project-portal-gestion-direccion-escuela.git
cd project-portal-gestion-direccion-escuela
```

### 2. Crear el archivo de entorno

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Linux o macOS:

```bash
cp .env.example .env
```

No se debe confirmar `.env` en Git. Antes de compartir o desplegar el proyecto, cambie `DB_PASSWORD` y las credenciales `POSTGRES_*`.

### 3. Construir la imagen y generar `APP_KEY`

```bash
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show
```

El segundo comando imprime una clave con formato `base64:...`. Cópiela en `.env`:

```dotenv
APP_KEY=base64:CLAVE_GENERADA
```

La clave debe persistir. Cambiarla después invalida sesiones y datos cifrados existentes.

### 4. Levantar la aplicación

```bash
docker compose up -d
docker compose ps
```

El contenedor `app` espera a PostgreSQL y ejecuta automáticamente:

```bash
docker compose exec app php artisan migrate --force
```

La aplicación queda disponible en [http://localhost:8000](http://localhost:8000).

### 5. Verificar la instalación

```bash
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec postgresql pg_isready -U postgres -d portal_gestion_direccion
```

Resultado esperado:

- Los servicios `app` y `postgresql` aparecen activos.
- PostgreSQL aparece como `healthy` o acepta conexiones.
- Las migraciones aparecen ejecutadas.
- `http://localhost:8000` responde correctamente.

## Conexión PostgreSQL

La aplicación usa dos direcciones según el origen de la conexión:

| Origen | Host | Puerto | Base de datos |
| --- | --- | --- | --- |
| Contenedor `app` | `postgresql` | `5432` | `portal_gestion_direccion` |
| Equipo local | `127.0.0.1` | `5433` | `portal_gestion_direccion` |

`docker-compose.yml` sobrescribe `DB_HOST` y `DB_PORT` dentro del contenedor. El `.env` conserva `127.0.0.1:5433` para comandos ejecutados desde el equipo local.

Los datos se guardan en el volumen `app_postgresql_data`. `docker compose down` conserva el volumen.

> `docker compose down -v` elimina el volumen y todos los datos de PostgreSQL. Úselo solo para reiniciar completamente el entorno.

## Comandos habituales

```bash
# Ver estado
docker compose ps

# Ver logs
docker compose logs -f app postgresql

# Ejecutar Artisan
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list
docker compose exec app php artisan tinker

# Limpiar cachés Laravel
docker compose exec app php artisan optimize:clear

# Detener servicios sin borrar datos
docker compose down

# Reconstruir después de cambiar dependencias o frontend
docker compose up -d --build
```

La imagen Docker es de ejecución: instala dependencias Composer con `--no-dev` y excluye `tests`. Ejecute la suite desde una instalación local de desarrollo.

## Instalación local para desarrollo

Use esta opción si necesita Vite en modo desarrollo o ejecutar PHPUnit fuera del contenedor.

Requisitos:

- PHP `8.4.1` o superior con `pdo_pgsql`, `pgsql`, `mbstring`, `bcmath`, `openssl` y `fileinfo`.
- Composer 2.
- Node.js 22 y npm.
- PostgreSQL accesible. Puede usar solo el servicio Docker incluido.

### 1. Preparar PostgreSQL

Para usar PostgreSQL mediante Docker:

```bash
docker compose up -d postgresql
```

El servidor queda disponible en `127.0.0.1:5433`, como indica `.env.example`.

### 2. Instalar y configurar Laravel

Windows PowerShell:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
```

Linux o macOS:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
```

### 3. Iniciar el entorno de desarrollo

```bash
composer run dev
```

Este comando inicia el servidor Laravel, el worker de colas, Pail y Vite. La URL predeterminada es [http://localhost:8000](http://localhost:8000).

### 4. Ejecutar pruebas

```bash
composer test
```

Las pruebas PostgreSQL requieren una base migrada y las variables `DB_*` correctas.

## Cambiar puertos

Si `8000` está ocupado, cambie el mapeo del servicio `app`:

```yaml
ports:
  - "8001:80"
```

Si `5433` está ocupado, cambie el mapeo de PostgreSQL y `DB_PORT` en `.env`:

```yaml
ports:
  - "5434:5432"
```

```dotenv
DB_PORT=5434
```

La comunicación interna entre contenedores continúa usando `postgresql:5432`.

## Solución de problemas

### La aplicación muestra un error sobre la clave

Confirme que `.env` contiene una clave válida:

```dotenv
APP_KEY=base64:...
```

Después reinicie el servicio:

```bash
docker compose up -d --force-recreate app
```

### PostgreSQL no está disponible

```bash
docker compose ps
docker compose logs postgresql
```

Revise que `5433` esté libre y que `POSTGRES_DB`, `POSTGRES_USER` y `POSTGRES_PASSWORD` coincidan con las credenciales `DB_*`.

### Cambios de código no aparecen en Docker

El proyecto se copia dentro de la imagen; no usa un bind mount. Reconstruya la imagen:

```bash
docker compose up -d --build app
```

## Licencia

Este proyecto usa Laravel, distribuido bajo licencia MIT.
