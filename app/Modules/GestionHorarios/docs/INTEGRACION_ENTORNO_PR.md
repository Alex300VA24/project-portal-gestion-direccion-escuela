# Gestión de Horarios: integración, entorno y preparación del Draft PR

## Anexo: integración local Prematrícula–Horarios

La prueba conjunta utiliza el worktree de integración y la base `portal_integracion_pm_gh` (puerto 5433). No ejecutar las pruebas de escritura contra la base original. Consulte [Planificación automática de secciones](PLANIFICACION_AUTOMATICA_SECCIONES.md) · [Ajuste de demanda](AJUSTE_DEMANDA_POST_PREMATRICULA.md) · [Prioridad docente](PRIORIDAD_DOCENTE_CPSAT.md) para las nuevas operaciones y sus pruebas de aceptación.


Actualización: 25 de noviembre de 2026. **Estado: candidato a revisión, no listo para fusión o despliegue institucional.**

Este documento complementa el índice de documentación, los manuales y los contratos anteriores. No cambia las decisiones de seguridad del portal. Distingue las funciones institucionales de la demostración aislada (QA). La revisión final debe hacerse sobre el diff real de la rama respecto de `develop`, no sobre los parches por separado.

> Actualización de revisión del ZIP y stash (25/11/2026): [auditoría, decisiones y pasos de aceptación](AUDITORIA_PREPARACION_PR.md). Esta actualización prevalece sobre los anexos de etapas anteriores.

## 1. Funcionalidades incorporadas y alcance

| Área | Cambios recientes | Alcance real |
| --- | --- | --- |
| Interfaz | Navegación directa, cabeceras y estilos comunes, selectores buscables, ajustes responsive. Referencia: `docs/DESIGN.md`, `resources/css/portal.css`, Fira Sans, Fira Code, tokens de color y accesibilidad. | Adaptación implementada; revisión visual integral pendiente. |
| Preparación académica | Oferta, asignación, disponibilidad, secciones y restricciones; edición de disponibilidad, prioridad y bloqueos horarios en QA. | Escrituras y lectura privada sujetas a permisos, escuela y fuentes. Subgrupos y catálogos aún no completamente editables. |
| Calendario | Selección de días laborables y excepciones de sábado por ciclo; repetición los sábados si hay disponibilidad declarada y se satisfacen las reglas. | Calendario semanal QA; jornada, receso y fechas bajo configuración existente. |
| Elaboración | Horario único de trabajo, revisiones por cambio de contenido, consultas por ciclo/sección, docente y ambiente, movimientos validados, generación y reorganización. | Flujo de ensayo aislado; la generación sigue dependiendo de la fuente histórica y de la conciliación con PostgreSQL. No se acredita la generación para otros periodos o escuelas. |
| Seguimiento | Estado real de fases, tiempo transcurrido, historial observado, cancelación en espera, hora `America/Lima`. | Cola y operador QA. No existe estimación porcentual del avance ni cancelación arbitraria de un cálculo en curso. |
| Revisión y publicación | Aprobación, publicación, actualización y retiro; instantánea independiente; motivo, versión, actor y fecha; modales de confirmación y diagnósticos. | QA. La aprobación y publicación por CLI se comprobaron para V3. La operación web integral requiere una nueva comprobación, incluidas las transacciones y el actor institucional. |
| Exportación | PDF/XLSX nativos en PHP, distribución compartida, A4 horizontal, una página/hoja por ciclo-sección como objetivo, lunes a sábado, AM/PM, receso y sesiones simultáneas. Descarga de consulta publicada y del docente. | Geometría y pruebas de lógica verificadas en ensayos; comprobar PDF real, impresión Excel, secciones densas y logo. Exportar no ejecuta Python ni crea una revisión. |
| Lectura | Consulta y Panel, filtros, actualización periódica del panel y horario personal docente. | Las vistas y rutas están registradas. En QA se muestra exclusivamente la instantánea publicada y solo a lectores autorizados. La ocupación es programada, no presencia física. |
| Integración | Contrato versionado de demanda, servicios de prevalidación y fuentes académicas. | Prematrícula, identidad docente, escuela, población y reglas institucionales no están completamente integradas. |

## 2. Rutas y responsabilidades

Todas las rutas son relativas al portal y conservan los middleware existentes. No fijar `localhost`, puertos ni una escuela en enlaces de producción.

| Ruta | Acceso esperado |
| --- | --- |
| `/gestion-horarios` | Entrada según el rol y ámbito autorizado. |
| `/gestion-horarios/consulta` | Usuario autenticado; consulta publicada y PDF según filtros. |
| `/gestion-horarios/panel` | Usuario autenticado; ocupación programada, actualización periódica. |
| `/gestion-horarios/mi-horario` | Profesor, con identidad docente acreditada. |
| `/gestion-horarios/mi-disponibilidad` | Profesor; sujeto a autorización y fuente de disponibilidad. |
| `/gestion-horarios/publicados` | Compatibilidad: navegador redirigido a Consulta; clientes que solicitan JSON conservan el contrato existente. |
| `/gestion-horarios/administracion/*` | Director/secretaría, con permisos y ámbito académico; acciones de demostración protegidas adicionalmente. |

Roles del portal: `alumno`, `profesor`, `director_escuela`, `secretaria`. El rol no sustituye el permiso `gestion-horarios.view`, la adscripción vigente ni la autorización de escritura. Para Mi horario no se deduce identidad por nombre o correo en producción: se requiere `ProveedorIdentidadDocente`. Los proveedores ficticios son exclusivos de pruebas.

## 3. Dependencias y entorno institucional

Usar la versión de PHP y extensiones aprobadas por infraestructura y exigidas por el `composer.lock` efectivo. PostgreSQL es la base del portal. Mantener el flujo de instalación estándar del proyecto y su `APP_KEY` existente. Las exportaciones emplean `dompdf/dompdf` y `phpoffice/phpspreadsheet`; revisar que ambos figuren en el manifiesto y lock correspondientes y que PHP disponga de las extensiones exigidas (incluidas `gd`, `zip`, `mbstring` y las de PostgreSQL cuando correspondan). No modificar el Dockerfile ni el comando compartido de desarrollo solo por diferencias de Windows sin aprobación.

Python y OR-Tools son necesarios para el cálculo/validación en QA, no para la consulta ni el PDF/XLSX. El entorno probado por el desarrollador informó Python 3.14.1 y OR-Tools 9.15.6755. Revisar `Engine/python/requirements-prueba-windows.txt` antes de instalar, y utilizar un entorno virtual separado. No instalar ni actualizar dependencias en Anaconda base. La selección de versión y despliegue institucional de Python sigue pendiente.

En el `.env` institucional, conservar la configuración del portal. No añadir valores de QA como configuración obligatoria ni activar `APP_ENV=testing` en un servidor institucional. Las variables estándar son `APP_ENV`, `APP_URL`, `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y, si existe, `DB_URL`. Los valores reales se suministran mediante la infraestructura/gestión de secretos. No copiar claves al repositorio ni a logs. No cambiar la zona horaria global para este módulo: las vistas de seguimiento convierten las marcas a `America/Lima`.

## 4. Variables adicionales exclusivamente de QA

Ejemplo de referencia: [horarios.qa.env.example](horarios.qa.env.example). Es una plantilla documental, no un archivo que Laravel cargue automáticamente. Las protecciones existentes usan `getenv()`; escribir valores solo en `.env` no garantiza que estén disponibles en el proceso HTTP, operador o proceso secundario. Exportarlos en cada consola de PowerShell antes de iniciar PHP. No añadir secretos o valores personales a `.env.example` del portal.

| Variable | Valor/uso en demostración |
| --- | --- |
| `APP_ENV` | `testing` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | Puerto efectivo de PostgreSQL local; comprobarlo. |
| `DB_DATABASE` | `horarios_demo_2026_ii_demanda` (exclusiva para esta prueba). |
| `DB_USERNAME`, `DB_PASSWORD` | Credenciales privadas del entorno aislado; no compartir. |
| `DB_URL` | No heredar una URL que apunte a otra base. Si se usa, debe corresponder a la misma conexión aislada. |
| `HORARIOS_DEMO_PLAN` | `QA-HORARIOS` |
| `HORARIOS_QA_CONSULTAS` | `1` en demostración; ausente o `0` fuera de ella. |
| `HORARIOS_DEMO_ARCHIVO` | Ruta absoluta privada al JSON histórico autorizado, fuera del repositorio. Todavía es requerido al generar/validar. |
| `HORARIOS_QA_PYTHON` | Ruta absoluta al ejecutable Python del entorno virtual. |
| `HORARIOS_DEMO_NOMBRES_REALES` | `0` por defecto. `1` solo para la operación local autorizada de nombres reales, nunca para un fixture distribuido. Cambiarlo no anonimiza datos ya cargados. |
| `HORARIOS_QA_LECTORES` | Correos de cuentas de prueba separados por `;`. Vacío si no se requiere lectura QA de alumno/profesor. |
| `HORARIOS_QA_DOCENTES` | Mapa explícito `correo-de-prueba=id_docente;otro-correo=id_docente`. IDs positivos reales de la base QA, no de otra base. Solo lectura de Mi horario. |

Las pruebas PostgreSQL/Python especializadas usan otras variables `HORARIOS_*_AISLADO`, `HORARIOS_*_PASSWORD` y `HORARIOS_PYTHON_AISLADO`. No son requisitos de la aplicación. Revisar cada prueba antes de ejecutarla; no apuntar a bases con información que deba conservarse.

### Arranque de la instalación QA existente (Windows)

Ejecutar desde la raíz del portal, no desde el proyecto original. Reutilizar las credenciales privadas y rutas que ya se tienen, sin cambiar el `.env` institucional. Ejemplo, sustituyendo los marcadores por rutas autorizadas:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_DATABASE = 'horarios_demo_2026_ii_demanda'
$env:HORARIOS_DEMO_PLAN = 'QA-HORARIOS'
$env:HORARIOS_QA_CONSULTAS = '1'
$env:HORARIOS_DEMO_ARCHIVO = '<ruta privada al JSON autorizado>'
$env:HORARIOS_QA_PYTHON = '<ruta al python.exe del venv>'
$env:HORARIOS_DEMO_NOMBRES_REALES = '0'
# Configurar DB_PORT, DB_USERNAME, DB_PASSWORD y lectores/docentes según la instalación local.
# Comprobar cualquier DB_URL heredada antes de iniciar.
php artisan config:clear
php artisan tinker --execute="dump(app()->environment(), DB::connection()->getDriverName(), DB::connection()->getDatabaseName());"
Test-Path $env:HORARIOS_QA_PYTHON
Test-Path $env:HORARIOS_DEMO_ARCHIVO
& $env:HORARIOS_QA_PYTHON -m pip check
```

La base efectiva debe ser `horarios_demo_2026_ii_demanda`. Si no coincide, no continuar. No imprimir credenciales. Los lectores deben existir con los roles/permisos correspondientes; la lista no crea cuentas. El puerto 8001 es intencional en las guardas de QA.

En dos consolas con la misma configuración, y solo si no están ya activos:

```powershell
php -S 127.0.0.1:8001 -t public app/Modules/GestionHorarios/Engine/qa/horarios_qa_demanda.php
# Segunda consola, mismo directorio y variables:
php app/Modules/GestionHorarios/Engine/qa/operar_cola_cpsat_qa.php worker
```

Los cinco conectores recibidos en el ZIP se proponen dentro de `Engine/qa/`, fuera de `public/`. Se calculan las rutas desde el propio repositorio. La edición y aprobación/publicación usan `EntornoEjecucionQa` para construir el entorno del proceso secundario a partir de la conexión PostgreSQL efectiva. Se mantiene el aislamiento previo: PHP CLI/cli-server, testing, conexión local, catálogo QA, cuenta QA y guardas de autorización. Las variables que intervienen antes de iniciar Laravel deben seguir exportadas al proceso; no se convierten en configuración institucional. No iniciar otro operador ni regenerar una publicación solo para probar la consulta. Para una base ya preparada, no ejecutar `init` ni scripts de DDL.

### Almacenamiento de demostración

`qa_horario_revision`, `qa_horario_trabajo`, `qa_prioridad_docente_revision`, `qa_generacion`, `qa_calendario_revision` y `qa_publicacion_evento` pertenecen al ensayo. La preparación previa se realizó mediante scripts CLI locales y guardas de aislamiento. No son migraciones institucionales aprobadas. En un entorno QA ya preparado no repetir seeders, scripts de inicialización o DDL. Para una base nueva se requiere un procedimiento reproducible con fixture sintético, migraciones acordadas y revisión de infraestructura; el PR actual no entrega un instalador QA autónomo.

## 5. Selección de archivos para el PR

**Candidatos:** servicios, controladores, Livewire, vistas y pruebas del módulo que no introduzcan bypass de autorización; documentación y recursos compartidos estrictamente necesarios. Revisar las dependencias entre archivos: no seleccionar una vista que llame a una clase excluida ni una ruta que apunte a un controlador ausente.

**Revisión separada obligatoria:**

- `composer.json` y `composer.lock` deben ser coherentes y revisarse juntos si añaden Dompdf/PhpSpreadsheet. `Dockerfile` y `RegistroPolicy.php` requieren aprobación de infraestructura/seguridad; mantener el permiso de la policy y no reemplazarlo por rol solamente.
- Las tres migraciones académicas nuevas (la cuarta se traslada a un fixture bajo tests/Support) y cambios a `DatabaseSeeder.php` requieren revisión de dependencias, propiedad de datos y orden de despliegue; no ejecutar sobre la base compartida para probar.
- Las clases/rutas marcadas `Demo`, `Qa` o `QA` incluyen comportamiento de ensayo. No se deben fusionar como operación institucional. Para un Draft PR, identificarlas de forma explícita y acordar con el revisor si se conservan como prototipo aislado o se separan. No borrar archivos a ciegas, porque existen llamadas cruzadas. Un PR listo para fusionar requiere sustituir esas dependencias con servicios y permisos aprobados o retirar el flujo de ensayo de forma coherente.
- No subir `.env`, dumps, JSON históricos con nombres/datos personales, archivos de sesiones, logs, reportes reales, credenciales, `.venv`, `vendor/`, `node_modules/`, caches, archivos de parche ni scripts locales del directorio padre. No usar `git add .`.

### Comprobaciones antes de preparar el commit

```powershell
git branch --show-current
git remote -v
git status --short
git fetch origin develop
git diff --name-status origin/develop
git diff --stat origin/develop
git diff --check
git diff --cached --check
git ls-files --others --exclude-standard
git diff origin/develop -- composer.json composer.lock Dockerfile database/seeders/DatabaseSeeder.php app/Modules/GestionHorarios/Policies/RegistroPolicy.php
```

`origin/develop` debe corresponder a la rama institucional. Verificar el remoto antes de hacer fetch; si el remoto institucional tiene otro nombre, sustituirlo. El diff contra `origin/develop` no incluye archivos no seguidos: revisar la última lista. No recuperar o eliminar stashes automáticamente. Para el commit, seleccionar rutas exactas con `git add -- <ruta>` y revisar `git diff --cached --name-status`, `git diff --cached --check` y el contenido del diff. Comprobar también lo que ya está en commits de la rama frente a `develop`; excluir un archivo del área de staging no elimina cambios ya comprometidos.

## 6. Pruebas y evidencia

Evidencia comunicada por el desarrollador: generación de un horario factible de demostración, validación independiente de V3 con huellas de recursos/origen coincidentes, aprobación y publicación V3 por CLI y ocho comprobaciones correctas de `DescargaConsultaPublicadaSmoke.php`. La lista de rutas mostró 31 rutas del módulo. Estos resultados son parciales; no son una regresión completa del PR final.

En un entorno de pruebas aislado, ejecutar las verificaciones disponibles en la rama:

```powershell
composer validate
composer check-platform-reqs
php artisan route:list --path=gestion-horarios
php artisan view:cache
php tests/Unit/GestionHorarios/smoke/ConsultaPanelHorarioSmoke.php
php tests/Unit/GestionHorarios/smoke/LectoresPublicacionQaSmoke.php
php tests/Unit/GestionHorarios/smoke/DescargaConsultaPublicadaSmoke.php
php tests/Unit/GestionHorarios/smoke/CicloVidaHorarioQaSmoke.php
php artisan test --filter=ColaGeneracionDemoDominioTest
```

Antes de `php artisan test`, inspeccionar `phpunit.xml`, variables y base efectiva. Las pruebas con migraciones o `RefreshDatabase` deben usar una base desechable distinta de la que conserva V3. Comprobar que cada prueba exista y realmente ejecute casos; un filtro con cero pruebas no es una validación. Ejecutar también la regresión de módulos compartidos y la CI del portal sobre el commit candidato. No afirmar que una prueba no ejecutada pasó.

Pruebas manuales pendientes: sesión de alumno/profesor/director; denegaciones por rol y escuela; selección y descarga PDF por ciclo/docente/ambiente; PDF A4 y Excel impresos; actualización y retiro de publicación; Panel con reloj local, actualización automática y pérdida de conexión; vista responsive y accesibilidad (tema, contraste, tamaño de texto y foco); aprobación/publicación web; reorganización completa; pruebas de concurrencia y seguridad del proceso secundario. Usar una copia de prueba para escenarios que modifican publicaciones.

## 7. Bloqueantes para fusionar y desplegar

1. Acordar la identidad, los permisos, adscripciones y ámbito de escuela con Core/Seguridad. Eliminar dependencia de cuentas QA y proveedores ficticios para uso institucional.
2. Completar contrato de Prematrícula y fuentes dinámicas de secciones, capacidades, subgrupos, disponibilidad y calendario; construir el modelo desde PostgreSQL sin JSON histórico ni supuesto fijo de 175 sesiones/versionado de demanda 1.
3. Sustituir el operador y adaptador QA ahora propuestos bajo Engine/qa por un ejecutor institucional aprobado (configuración, cola, límites, logs, recuperación, aislamiento y validación). El traslado de archivos no elimina los supuestos de datos históricos ni habilita la generación institucional.
4. Utilizar almacenamiento institucional aprobado para horario de trabajo, revisiones y publicaciones, con auditoría del actor real, control de concurrencia y autorización por escuela. No trasladar directamente las tablas QA globales.
5. Resolver dependencias Composer, Docker y migraciones; probar el conjunto exacto de archivos del PR y finalizar el manual y la revisión visual.

**Conclusión:** se puede solicitar revisión mediante Draft PR con estos límites expresamente indicados. No se debe marcar listo para fusionar, afirmar uso productivo ni activar una publicación de prueba en el portal institucional.

## 8. Cumplimiento de las normas de integración del repositorio

Fuentes normativas consultadas en `develop` el 25/11/2026:

- [PC-Convenciones-Git.md](../../../../docs/PC-Convenciones-Git.md): ramas, commits y cuerpo del PR.
- [GUIA_MODULOS.md](../../../../GUIA_MODULOS.md): propiedad de archivos, dependencias y revisión antes del commit.
- [architecture.md](../../../../docs/architecture.md): capas, rutas, seguridad y migraciones.
- [base_de_datos.md](../../../../docs/base_de_datos.md): esquemas, identidad y auditoría.
- [DESIGN.md](../../../../docs/DESIGN.md): diseño y frontend compartidos.

### 8.1 Matriz de cumplimiento y evidencias exigidas

| Norma | Aplicación en Gestión de Horarios | Evidencia requerida antes de fusionar |
| --- | --- | --- |
| Rama temporal desde `develop`; prefijo `feature/`, `fix/` o `refactor/` | Mantener la rama de mejoras existente si su origen se confirma. No crear otra rama ni reescribir la historia sin revisar el estado. | `git merge-base`, historial y comparación contra el develop institucional. |
| Commit `<tipo>(<modulo>): <resumen>` | Usar alias `gestion-horarios`; tipo literal `feature`, no `feat`. Resumen en imperativo, aproximadamente 72 caracteres como máximo. | Revisar los commits que integrará el PR; los commits anteriores no cambian por añadir documentación. |
| Aislamiento del módulo | Código en `app/Modules/GestionHorarios`, vistas en `resources/views/modules/gestion-horarios`, pruebas en `tests/Feature/GestionHorarios` o `tests/Unit/GestionHorarios`. | `ModuleStructureTest`, inventario del diff y revisión de imports. Los smoke scripts internos son auxiliares, no sustituyen PHPUnit. |
| Datos de otros módulos | Sin importar clases internas ni leer tablas privadas de Prematrícula directamente. Usar contrato acordado, referencias lógicas y modelos compartidos para datos de `core`, `seguridad`, `comun`. | Revisar consultas/servicios existentes, incluso consultas directas a catálogos compartidos que deban adaptarse a modelos de `App\Shared\Models`. No afirmar cumplimiento total sin revisar el diff. |
| Capas y rutas | Route -> Request/Policy -> Controller -> Service -> Model; servicios sin HTTP/vistas, transacciones en servicios; URLs y nombres kebab-case, prefijo `gestion-horarios.` y `api.gestion-horarios.`. | Revisar controladores de publicación, configuración del proceso y autorización por recurso; separar QA de implementación definitiva. No modificar registros globales de rutas. |
| Migraciones | Nuevos archivos en `Database/Migrations`, una migración por tabla nueva según arquitectura; nombres calificados, timestamp posterior a dependencias, `down()` simétrico. FK solo al esquema propio, `core`, `seguridad`, `comun` o `public`. | Doble pasada de `module:migrate gestion-horarios` en una base desechable; segunda sin migraciones pendientes. No editar migraciones aplicadas. DDL QA no equivale a migraciones aprobadas. |
| Autorización | `auth` + roles/permisos Spatie + Policy/FormRequest/Controller con ámbito de escuela y recurso; el servicio ejecuta casos autorizados. | Pruebas de acceso anónimo, alumno, profesor, secretaria, director, escuela ajena, identidad no vinculada y transiciones inválidas. No eliminar guardas QA para habilitar producción. |
| Auditoría | Registro de versión, evento, motivo, responsable y fecha en QA. | Actor institucional acreditado y contrato de auditoría acordado; `seguridad.auditoria` es inalterable. No modificar esquema compartido sin acuerdo. |
| Diseño | Layout institucional, Fira Sans/Code, tokens de `portal.css`, Blade/Livewire/Alpine único; foco, contraste, tema y tamaño de fuente. | Revisar todas las vistas en móvil/escritorio, navegación por teclado y modales. No añadir una segunda instancia de Alpine, `@livewireScripts` ni estilos globales paralelos. |
| Infraestructura | Laravel modular y PostgreSQL 17; Docker del portal normalmente publica HTTP 8000 y PostgreSQL 5433 hacia el host. QA local 8001 es otro entorno. | Acuerdo para Composer, Docker y dependencias de cálculo. Reconstruir imagen si se prueba en Docker; no confundirla con el servidor local. |

### 8.2 Comprobaciones Git y pruebas exigidas

Confirmar primero cuál remoto es institucional y cuál es el fork personal. Los ejemplos asumen `origin` institucional y `mi-fork` personal; sustituirlos si `git remote -v` indica otra cosa. No hacer push al repositorio institucional directamente.

```powershell
git branch --show-current
git remote -v
git status --short
git stash list
git fetch origin develop
git log --oneline origin/develop..HEAD
git diff --name-status origin/develop...HEAD
git diff --name-status origin/develop
git diff --check
git diff --cached --check
```

El diff de tres puntos muestra cambios comprometidos desde el ancestro común; el diff contra la rama incluye cambios locales en archivos seguidos. Revisar también archivos no seguidos. Integrar cambios nuevos de develop solo después de revisar el árbol local y acordar merge/rebase; no usar force-push ni recuperar/eliminar stashes a ciegas.

En una instalación de pruebas con base desechable, revisar el entorno de PHPUnit antes de ejecutar:

```powershell
php artisan module:migrate:status gestion-horarios
php artisan module:migrate gestion-horarios
php artisan module:migrate gestion-horarios
php artisan test --filter=GestionHorarios
php artisan test tests/Unit/ModuleStructureTest.php
php artisan test tests/Feature/ModularRouteTest.php
```

Confirmar que los archivos y filtros existen y que las pruebas ejecutan casos. Revisar rollback de nuevas migraciones en la base desechable y ejecutar regresión general/CI sobre el commit. No ejecutar estas migraciones ni pruebas destructivas en la base con V3. La guía de módulos indica PHPUnit local; la arquitectura también menciona pruebas en Docker, por lo que debe confirmarse que la imagen de pruebas tenga dependencias dev antes de usarlas allí. Si la infraestructura lo aprueba, `scripts/validate-modular-database.ps1` proporciona validación aislada de la base modular. Para frontend, ejecutar `npm run build` cuando corresponda. La comprobación visual no se reemplaza con `view:cache`.

### 8.3 Propuesta de commits y Draft PR

Ejemplos de mensajes (no son comandos ejecutados ni una orden de crear commits sin revisar el staging):

- `feature(gestion-horarios): integra planificación y consulta de horarios`
- `fix(gestion-horarios): corrige acceso y exportación de horarios`
- `style(gestion-horarios): corrige formato del controlador`
- `docs(gestion-horarios): documenta integración y entorno de pruebas`
- `migration(gestion-horarios): agrega tabla de revisiones` (solo si la migración correspondiente se aprueba e incluye; tipo obligatorio para cambios estructurales de tablas existentes).
- `chore(gestion-horarios): ajusta dependencias de exportación` (solo si se acuerdan los cambios compartidos).

**Título propuesto:** `feature(gestion-horarios): integra planificación y consulta académica`

**Objetivo:** incorporar al portal las interfaces de preparación, elaboración y consulta de horarios, con un flujo de generación y publicación probado de manera aislada.

**Cambios clave:** navegación por rol y diseño común; calendario y restricciones; generación, reorganización y revisiones QA; publicación con instantáneas y auditoría QA; consulta, panel, horario docente y exportación PHP; documentación y pruebas.

**Cómo probar:** preparar entorno según secciones 3–4; confirmar base efectiva; verificar rutas y smoke tests de sección 6; ejecutar pruebas de módulo/estructura y doble pasada de migraciones en una base desechable; revisar consulta y PDF por rol, calendario y publicación en una copia QA. El ensayo de generación completo necesita los conectores externos y fixture autorizado existentes; este PR no permite reconstruirlo desde un clon limpio por sí solo. No presentar esos pasos como reproducibles sin dichos prerrequisitos.

**Impacto/Riesgos:** generación y publicación institucionales no habilitadas; contrato Prematrícula e identidad pendientes; DDL y conectores QA no son infraestructura de producción; dependencias compartidas y migraciones requieren aprobación; falta CI y regresión sobre el commit final. El PR se solicita como Draft, no como listo para fusionar.

Tras seleccionar y revisar el staging, preparar el commit y hacer push a la rama del fork confirmada, crear el PR desde esa rama hacia `develop` del repositorio institucional. Si ya existe un PR abierto para la misma rama, actualizarlo en vez de duplicarlo. El tipo de PR, su título y sus cuatro apartados siguen la guía oficial.
