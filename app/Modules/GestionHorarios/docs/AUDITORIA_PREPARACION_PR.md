# Preparación del Draft PR: auditoría de la implementación

**Fecha:** 25/11/2026. **Alcance:** copia de código del portal, vistas, pruebas y cinco conectores locales recibidos para revisión, más el diff del stash. No se accedió a la instalación, a sus credenciales, a PostgreSQL ni a datos de alumnos/docentes. Esta revisión no autoriza fusionar ni desplegar el módulo.

## 1. Normas de integración

Aplicar [GUIA_MODULOS.md](../../../../GUIA_MODULOS.md), [arquitectura](../../../../docs/architecture.md), [base de datos](../../../../docs/base_de_datos.md), [diseño](../../../../docs/DESIGN.md) y [convenciones Git](../../../../docs/PC-Convenciones-Git.md). Rama de trabajo `feature/gestion-horarios-mejoras`; destino `develop`. En el PR incluir Objetivo, Cambios clave, Cómo probar e Impacto/Riesgos. Usar `feature`, `fix`, `refactor`, `chore`, `docs`, `style` y `migration` según la naturaleza del cambio; no reescribir los commits anteriores publicados para cambiar su prefijo.

| Aspecto | Decisión de esta revisión | Criterio de aceptación antes de merge |
| --- | --- | --- |
| Aislamiento | Controladores, servicios, motor y vistas dentro de su módulo. No se cambian rutas globales ni modelos de otros módulos. | `ModuleStructureTest`, autorización y revisión de contratos. |
| Diseño | Se conservan los estilos y vistas existentes; el cambio actual no modifica las interfaces. | Probar en navegador, accesibilidad y filtros/roles. |
| Persistencia | Tres archivos académicos del stash y fixture de cola separado de migraciones. | Aplicación, idempotencia y rollback en base descartable, revisión de propiedad y actor. |
| Seguridad | Conservar `RegistroPolicy` con permiso `gestion-horarios.view`, y proveedores de alcance/identidad con denegación por defecto. | Acreditar identidad real y autorización por escuela y recurso. |
| Exportación | Recuperar Dompdf y PhpSpreadsheet en manifiesto/lock, más extensiones GD/ZIP en Docker como candidato independiente. | Aprobación de infraestructura, build, validación Composer y PDF/XLSX reales. |
| Motor | Conectores QA trasladados al repositorio sin cambiar el algoritmo ni datos. | Contrato institucional, cálculo desde instantánea autorizada, worker y publicación institucional, regresión. |

## 2. Cambios y decisiones sobre el stash

| Archivo | Decisión |
| --- | --- |
| `2026_11_16_000001_create_demanda_academica_tables.php` | Conservar el SQL y nombre originales (dos tablas y FK a horario). Requiere aprobación de excepción a una migración por tabla o estrategia acordada para separar sin romper bases que ya lo registraron. |
| `2026_11_16_000002_create_revision_oferta_table.php` | Conservar; revisión pendiente del actor y retención. |
| `2026_11_17_000003_create_oferta_seccion_table.php` | Conservar; requiere conciliación con Core y alcance del actor. |
| `2026_11_17_000004_create_ejecucion_cpsat_prueba.php` | Solo fixture `tests/Support/GestionHorarios/Database/`, con dos referencias actualizadas en `ColaCpsatPostgresTest`. No se ejecuta en instalación normal. No borrar la tabla ni el historial de una base QA donde ya se ejecutó. |
| `RegistroPolicy.php` | No recuperar el cambio. Importaba `EscuelaAutorizada` (eliminada) y reemplazaba el control de permiso por rol. |
| `DatabaseSeeder.php` | No recuperar. El seeder histórico y sus datos se mantienen fuera del PR institucional hasta que se acuerde un fixture sintético. |
| `composer.json`, `composer.lock`, `Dockerfile` | Parche separado de dependencias. Se recuperan Dompdf `^3.1`, PhpSpreadsheet `^5.11`, lock y extensiones de exportación. Se conserva el comando `composer run dev` institucional, incluida la herramienta de logs. El Dockerfile no incorpora aún Python institucional. |
| `JUSTIFICACION_CAMBIOS_COMPARTIDOS_PR.md` | Se conserva como antecedente; las decisiones vigentes constan aquí. |

No modificar una migración aplicada ni ejecutar `migrate:fresh`, `db:wipe`, rollback o seeders en la base que conserva la publicación V3. No aplicar el stash entero ni eliminarlo. Las migraciones deben revisarse contra `origin/develop`, no solo contra una copia del módulo. La reubicación de un fixture no acredita que la suite antigua ya esté completa.

## 3. Reproducción del entorno

### Institucional (sin QA)

Seguir el README general del portal. Conservar `APP_ENV`, `APP_URL`, `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y `DB_URL` según la instalación. No añadir valores de demostración a `.env.example` global ni activar flags QA. Los secretos van en el entorno, nunca en Git. La exportación PHP requiere que se apruebe el parche de dependencias y que sus extensiones estén disponibles. Verificación:

```powershell
composer validate --strict
composer install --no-interaction
composer check-platform-reqs
composer audit
npm ci
npm run build
php artisan route:list --path=gestion-horarios
php artisan view:cache
```

En Docker, tras la aprobación y en un entorno de revisión que no comparta volumen con producción:

```sh
docker compose build app
# Después de preparar la base desechable y revisar migraciones:
docker compose exec app php artisan module:migrate:status gestion-horarios
```

No ejecutar Docker contra la base con datos que deban conservarse. El arranque de la imagen institucional puede ejecutar migraciones automáticamente.

### Demostración ya preparada

Plantilla: [`horarios.qa.env.example`](horarios.qa.env.example). El servidor local usa `127.0.0.1:8001` de manera intencional. El proyecto de referencia de horarios no debe estar activo en el mismo puerto. Las credenciales y rutas reales las suministra quien opera QA; no se imprimen.

| Variable de proceso | Uso |
| --- | --- |
| `APP_ENV=testing` | Guarda obligatoria en CLI/cli-server. |
| `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=horarios_demo_2026_ii_demanda` | PostgreSQL local aislado; también se verifica el nombre efectivo de la base. |
| `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_URL` | Configuración real del entorno local; comprobar que la URL heredada no redirija a otra base. |
| `HORARIOS_DEMO_PLAN=QA-HORARIOS` | Catálogo aislado reconocido. |
| `HORARIOS_QA_CONSULTAS=1` | Habilitación exclusivamente local. Ausente o `0` fuera de QA. |
| `HORARIOS_DEMO_ARCHIVO` | Ruta privada al JSON de referencia autorizado. Requerida para cálculo y validación. |
| `HORARIOS_QA_PYTHON` | Ruta al ejecutable Python de un venv separado, no Anaconda base. |
| `HORARIOS_DEMO_NOMBRES_REALES` | `0` recomendado. Cambiar el flag no anonimiza registros existentes; no se comparte el JSON original. |
| `HORARIOS_QA_LECTORES` | Correos de cuentas QA separados por `;`. |
| `HORARIOS_QA_DOCENTES` | Mapa `correo=id_docente` separado por `;`, solo para lectura. |

Las variables QA se exportan en cada consola (las guardas anteriores al arranque de Laravel no leen automáticamente `.env`). `EntornoEjecucionQa` centraliza la ruta, los valores usados en HTTP y el entorno del proceso hijo. No es configuración institucional ni un proveedor de identidad. El futuro ejecutor institucional debe leer parámetros aprobados mediante configuración Laravel registrada, no reutilizar las guardas y los datos históricos de QA.

Desde la raíz del portal, con las mismas variables en cada consola, detener primero el worker antiguo **solo cuando no exista cálculo activo** y cerrar el servidor web anterior. No iniciar un segundo worker. No ejecutar `init` ni preparación de tablas sobre una publicación existente.

```powershell
$qa = 'app/Modules/GestionHorarios/Engine/qa'
php -S 127.0.0.1:8001 -t public "$qa/horarios_qa_demanda.php"
# Otra consola, misma configuración y raíz:
$qa = 'app/Modules/GestionHorarios/Engine/qa'
php "$qa/operar_cola_cpsat_qa.php" worker
```

Rutas: `/gestion-horarios` (entrada), `/gestion-horarios/consulta`, `/gestion-horarios/panel`, `/gestion-horarios/mi-horario`. La descarga PDF usa los filtros de consulta. Verificar con cada rol autorizado sin regenerar ni retirar V3. El JSON sigue siendo un insumo externo: esta entrega no crea una instalación QA nueva desde cero. Los inicializadores históricos de horario único, versiones, prioridad y demanda no formaban parte de los cinco conectores recibidos; no se recrean por inferencia. El seeder `database/seeders/GestionHorariosDemo2026IISeeder.php` sigue siendo local y debe excluirse del commit candidato.

### Variables de pruebas especializadas

Las suites utilizan flags de activación y credenciales de bases desechables independientes (por ejemplo `HORARIOS_COLA_POSTGRES_AISLADO`, `HORARIOS_COLA_TEST_PASSWORD`, `HORARIOS_27B2_POSTGRES_AISLADO`, `HORARIOS_27B2_PASSWORD`, `HORARIOS_PYTHON_AISLADO`). No son variables obligatorias del módulo. Cada clase verifica su entorno; revisar `setUp()` y fixtures antes de habilitar una suite. Nunca usar credenciales QA o institucionales en una prueba que cree/elimine tablas. Las pruebas pueden quedar omitidas si el entorno especializado no se ha preparado.

## 4. Pruebas y evidencia de esta revisión

Desde la raíz, sin Laravel ni DB, ejecutados en la copia revisada:

```powershell
Get-ChildItem tests/Unit/GestionHorarios/smoke -Filter '*Smoke.php' | ForEach-Object {
    php $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "Falló $($_.Name)" }
}
node --test tests/Feature/GestionHorarios/javascript/borrador-semanal.test.mjs
```

Ocho scripts PHP finalizaron correctamente: ciclo de vida (13), consulta/panel (15), descarga (8), distribución, entorno QA (7), estado/exportación (12), lectores (10) y planos A4 (4 planos de ejemplo, 5 sesiones, métricas aproximadas). Cuatro pruebas JS pasaron. Esto no comprueba el render Dompdf, el contenido XLSX real, una conexión PostgreSQL, Livewire en navegador ni el worker contra la base del usuario. El código Python se revisó sintácticamente, no se ejecutó CP-SAT contra sus datos en esta intervención. El hash del manifiesto Composer coincide con el hash registrado por el lock candidato; no se instaló Composer ni se verificó el build Docker.

Para el commit definitivo (en una instalación completa y con base descartable, diferente de V3):

```powershell
php artisan test tests/Unit/GestionHorarios
php artisan test tests/Feature/GestionHorarios
php artisan test tests/Unit/ModuleStructureTest.php
```

Comprobar que se ejecutan las pruebas esperadas y documentar omisiones. Ensayar `php artisan module:migrate gestion-horarios` dos veces en una base nueva, comprobar `module:migrate:status`, validar las reversiones y la regresión global; no ejecutar esos comandos con datos que se deban conservar. Ajustar los tests de inventario de esquemas solo si el equipo aprueba las tablas nuevas y con evidencia de la compatibilidad. Ejecutar Pint sobre archivos modificados del módulo, no sobre todo el portal sin autorización.

## 5. Preparación del PR

Propuesta de título: `feature(gestion-horarios): integra planificación y consulta académica`.

**Objetivo:** incorporar preparación, consulta y exportación académica al portal, y presentar el prototipo aislado del motor para revisión.

**Cambios clave:** interfaces Blade/Livewire, contratos, restricciones, planificación, horarios de trabajo, consulta por alcance, PDF/XLSX y separación del soporte ficticio de pruebas; conectores QA reubicados para revisión. Identificar expresamente el carácter no institucional de la generación, edición validada, aprobación y publicación en el entorno de demostración.

**Cómo probar:** secciones 3–4, escenarios de cada rol, exportación y validación en entorno aislado; verificar rutas, dependencias y CI. Si el revisor no dispone del dataset de prueba autorizado, no afirmar que puede reproducir el cálculo completo de QA.

**Impacto/Riesgos:** las tres migraciones académicas crean cuatro tablas y una FK/columna sobre horario; se requieren revisión de Core y autorización de infraestructura para dependencias compartidas. El cálculo aún exige una fuente histórica, no un contrato Prematrícula en funcionamiento; no se dispone de un proveedor institucional de identidad/escuela confirmado. Las tablas QA globales no son almacenamiento institucional. Si el equipo no acepta conectores QA en un Draft PR, separarlos en una rama de demostración antes de fusionar y conservar coherencia de las llamadas. No habilitar el motor ni la publicación institucional mediante cambios de rol, variables QA o seeders.

**Selección Git:** conservar el stash; no usar `git add .`. Revisar tanto lo ya comprometido como los archivos sin seguimiento. No incluir `tatus --short`, el seeder histórico local, los `.env`, JSON históricos, credenciales, logs, dumps, entornos virtuales ni datos personales. Antes de `git push`, comprobar `git diff --cached --check` y `git diff --cached --name-status`. Mantener `composer.json`, `composer.lock` y Dockerfile separados para la revisión del responsable. Para nuevos commits, usar tipos definidos en la guía institucional, por ejemplo `migration(gestion-horarios): incorpora persistencia de demanda y oferta`, `refactor(gestion-horarios): separa conectores y pruebas de QA` y `docs(gestion-horarios): documenta entorno y criterios del PR`. El commit que cambia estructura existente debe llevar tipo `migration`.

**Bloqueo de fusión:** aprobar dependencias y migraciones, integrar proveedores institucionales, sustituir dependencia histórica, completar cola/ejecutor institucional y persistencia/actor/auditoría por escuela, ejecutar pruebas integrales y validar revisión visual. No marcar el PR como Ready for review ni solicitar merge hasta verificar estos criterios con el equipo.
