# Arquitectura de Investigación Formativa

## Alcance y estado

El módulo usa el monolito Laravel y PostgreSQL del portal. No inicia servicios separados. La configuración, los grupos, los títulos, las evaluaciones, las encuestas y los reportes tienen implementación y pruebas funcionales. La carga y descarga de archivos sigue pendiente de G4, el contrato del escritor compartido de archivos.

Las asignaciones de identidad, padrón, competencias y alcance de dirección tienen contratos locales explícitos. Falta confirmar su integración con los responsables de los datos compartidos. No se deducen de correos ni se sustituyen por contenido académico inventado.

## Directorios y responsabilidades

Las rutas de esta tabla son relativas a `app/Modules/InvestigacionFormativa/`, salvo las indicadas con prefijo completo.

| Directorio o archivo | Responsabilidad |
|---|---|
| `Controllers/` | Respuestas HTML, redirecciones, filtros y conflictos. |
| `Requests/` | Autorización inicial y validación del formulario. |
| `Services/IdentidadInvestigacionService.php` | Vinculación entre `users` y la cuenta académica activa. |
| `Services/AccesoInvestigacionService.php` | Alcance por escuela, sección, padrón, grupo y coordinación. |
| `Services/DatosAcademicosService.php` | Consultas de datos compartidos y etiquetas académicas. |
| `Services/ConfiguracionService.php` | Habilitación, competencias, rúbrica, fechas y congelación. |
| `Services/GrupoService.php` | Grupo atómico, membresía fija, título y revisiones. |
| `Services/EvaluacionService.php` | Evaluación por entrega, corrección y observaciones históricas. |
| `Services/EncuestaService.php` | Borrador, publicación, cierre y respuestas individuales. |
| `Services/ReporteService.php` | Resultados por sección, promedio, empates e historial real. |
| `Services/CalificacionService.php` | Normalización y promedios con centésimas enteras. |
| `Models/`, `Policies/` | Tablas del módulo y autorización de sus recursos. |
| `Commands/`, `Support/CsvImportReader.php` | Cuatro importaciones atómicas con validación previa. |
| `Support/ArchivoGateway.php` | Interfaz del archivo compartido. No es un escritor aprobado. |
| `Support/UnavailableArchivoGateway.php` | Rechazo explícito de crear o buscar archivos mientras falte G4. |
| `Database/Migrations/` | Definiciones y cambios aditivos del esquema. |
| `Database/Seeders/` | Permisos aditivos y datos aislados de demostración. |
| `Routes/web.php`, `Routes/api.php` | Rutas propias del módulo. |
| `Providers/InvestigacionFormativaServiceProvider.php` | Políticas, comandos y enlace del gateway cerrado. |
| `resources/views/modules/investigacion-formativa/` | Pantallas Blade por actor y elementos comunes. |
| `tests/Feature/InvestigacionFormativa/` | Pruebas PostgreSQL de comportamiento y demostración. |
| `tests/Browser/investigacion_formativa.py` | Recorrido Firefox mediante WebDriver y formularios reales. |

El proveedor está registrado en `bootstrap/providers.php`. El módulo no importa controladores o modelos de otro módulo. Los modelos de catálogos comunes se encuentran en `App\Shared\Models\Core`.

```mermaid
flowchart LR
    U[Usuario] -->|envía formulario| R[Ruta y middleware]
    R -->|valida permiso y recurso| V[Request y Policy]
    V -->|entrega datos válidos| C[Controller]
    C -->|ejecuta operación| S[Service]
    S -->|lee identidad y catálogo| A[core y seguridad]
    S -->|escribe datos propios en transacción| D[investigacion_formativa]
    D -->|devuelve estado| S
    S -->|devuelve resultado| C
    C -->|renderiza respuesta| B[Blade]
    B -->|muestra resultado| U
```

La lectura sigue las flechas desde el formulario hasta los datos y vuelve a la pantalla.

## Esquema y propiedad de datos

El esquema `investigacion_formativa` tiene 22 tablas. Las claves académicas referencian `core`; los usuarios académicos y módulos referencian `seguridad`. `public.users` es la identidad de inicio de sesión. El catálogo compartido de archivos pertenece a `comun`.

| Grupo | Tablas |
|---|---|
| Identidad y configuración | `identidad_usuario`, `director_escuela`, `seccion_configuracion`, `participante_seccion`, `competencia_seccion` |
| Proyecto | `tipo_producto`, `grupo`, `grupo_estudiante`, `proyecto`, `revision_titulo` |
| Evaluación | `rubrica`, `criterio`, `criterio_nivel`, `entregable`, `entrega`, `evaluacion`, `evaluacion_criterio`, `observacion` |
| Encuesta | `encuesta`, `encuesta_pregunta`, `encuesta_respuesta`, `encuesta_respuesta_item` |

La base impide múltiples grupos del mismo estudiante en una sección y múltiples coordinadores por grupo. La creación del servicio incluye al coordinador como integrante. Las encuestas tienen unicidad por sección y por estudiante; las claves compuestas de los ítems impiden mezclar preguntas y respuestas de encuestas distintas.

Los cambios no alteran las notas oficiales de matrícula. La migración base del módulo y 18 migraciones posteriores describen el estado actual. Las migraciones posteriores se agregan sin reescribir el historial de otros módulos.

## Contratos de integración

| Contrato | Implementación local | Límite pendiente |
|---|---|---|
| G1: identidad | `identidad_usuario` vincula un `user_id` con `id_usuario_seguridad`. Se comprueban ambos estados activos. | Confirmación del origen y mantenimiento de la vinculación. |
| G2: padrón | `participante_seccion` guarda estudiante, sección, estado, fuente y referencia. La importación no borra filas. | Entrega autorizada desde matrícula. |
| G3: competencias | Instantánea por sección con código, texto, fuente y selección. | Fuente oficial y actualización coordinada del contenido. |
| G4: archivo | `ArchivoGateway` y su implementación que rechaza la operación. | Escritor/modelo compartido, misma conexión y ciclo del archivo privado. |
| G5: alcance | Director por escuela y docente responsable en `core.seccion`. | Confirmación de responsables de las asignaciones. |

Los comandos `investigacion:importar-identidades`, `investigacion:importar-directores`, `investigacion:importar-participantes` e `investigacion:importar-competencias` aceptan `CSV --dry-run` o `CSV --apply`. Valide antes de aplicar. Las importaciones no sustituyen la gestión de roles Spatie.

## Transacciones y concurrencia

La configuración de sección es el primer punto de serialización de las escrituras del dominio. Los servicios vuelven a comprobar el estado después del bloqueo.

| Operación | Orden y garantía |
|---|---|
| Configuración | Configuración de sección; comparación de versión; cambio y nuevo sello dentro de una transacción. |
| Crear grupo | Configuración; nueva comprobación del padrón; grupo, integrantes y proyecto atómicos; restricciones únicas como respaldo. |
| Editar o revisar título | Configuración; proyecto bloqueado; versión esperada; cambio e historial atómicos. |
| Evaluar | Configuración; proyecto; entrega; evaluación. Se reemplazan los criterios actuales y se conservan observaciones anteriores. |
| Encuesta | Configuración; encuesta. Publicación, cierre y respuesta usan el mismo orden. Cabecera e ítems de respuesta se guardan juntos. |
| Importar padrón | Bloqueo de configuración por sección antes de aplicar sus estados. No elimina integrantes históricos. |

Los conflictos de edición devuelven 409 en los flujos que tienen recuperación de formulario. Recargar permite comparar el estado vigente; no se considera correcto sobrescribirlo silenciosamente. La prueba de concurrencia usa procesos y conexiones PostgreSQL separados, con barreras de bloqueo.

## Cálculo de notas y encuestas

Sea `P` la suma de puntos seleccionados y `M` la suma de máximos de la rúbrica. La nota de unidad es `redondear(20 × P / M, 2)`. Cada puntaje se convierte a centésimas antes de operar. Se usa redondeo medio hacia arriba.

Ejemplo independiente: máximos `4 + 4 + 2 = 10`; seleccionados `3 + 2 + 1 = 6`; nota `20 × 6 / 10 = 12.00`. Si las tres unidades vigentes tienen notas `12.00`, `16.00` y `14.00`, la nota final es `(12.00 + 16.00 + 14.00) / 3 = 14.00`.

La versión más alta de cada entrega determina el avance vigente. Si esa versión no tiene evaluación, la unidad está pendiente. Una evaluación antigua permanece en el historial, pero no sustituye a la vigente.

Solo los proyectos con tres unidades evaluadas tienen nota final. La clasificación incluye todos los empates y los ordena por ID de grupo. El promedio del curso excluye proyectos incompletos e indica el denominador. Con un solo proyecto elegible, mejor y peor coinciden y la pantalla lo explica.

Las respuestas de encuesta se cuentan por cabecera. El promedio de una pregunta usa sus ítems reales; no usa el total del padrón. La distribución cuenta valores de 1 a 5. La participación es `100 × respuestas / participantes_activos_actuales`; puede superar el 100% tras retiros. Si el denominador es cero, no hay tasa calculable.

Los reportes limitan las secciones al alcance antes de leer sus datos. El resumen lee los conjuntos por lote; no consulta cada tabla dentro del bucle de secciones. Los comentarios se paginan y se renderizan escapados, sin identidad del estudiante.

## Archivos: estado actual y trabajo pendiente

El proveedor enlaza `ArchivoGateway` con `UnavailableArchivoGateway`. Sus métodos `crear` y `buscar` lanzan `ArchivoNoDisponible`. No existen rutas de carga o descarga funcionales. Tampoco existe aún el comando propuesto `investigacion:auditar-archivos`.

El ciclo pendiente debe cubrir almacenamiento privado, metadatos compartidos en la misma conexión, limpieza de temporales al fallar y descargas autorizadas por entrega. Todavía no se declara verificado ese ciclo. No se instala un adaptador de producción ni se escriben metadatos compartidos para aparentar integración.

Las pruebas de lectura pueden insertar entregas y metadatos de prueba dentro de una base exclusiva. El seeder de demostración no los crea. El PDF de muestra tiene 601 bytes y SHA-256 `477ac889a46353abee504e726e420b78d0241f7ff244448376c8160d0644fd0a`; queda disponible para la futura integración.

## Entorno, compilación y pruebas

El README raíz y el Dockerfile definen el entorno del portal. Use una base de prueba nueva con `DB_CONNECTION=pgsql`, `DB_URL` vacío y las variables de conexión correctas. El `phpunit.xml` usa SQLite si no se sustituyen; una ejecución que omite PostgreSQL no valida este módulo.

```bash
php artisan module:migrate all --force
php artisan test tests/Feature/InvestigacionFormativa
php artisan test tests/Unit/InvestigacionFormativa
php artisan test --compact
npm run build
php artisan view:cache
php artisan route:cache
php artisan route:list --path=investigacion-formativa
php artisan route:clear
php artisan view:clear
git diff --check
```

En bases exclusivas con `_test_demo_` también se ejecutan las pruebas del seeder. La prueba de concurrencia necesita otra base nueva con `_test_concurrency_`, todas las migraciones y `RUN_CONCURRENCY_TESTS=1`. No la ejecute sobre datos que quiera conservar. Consulte [manual-flujos.md](manual-flujos.md) para generar datos y ejecutar Firefox.

El Dockerfile crea dependencias sin paquetes de desarrollo, compila Vite y sirve `public` con PHP/Apache. El contenedor de ejecución no es el entorno de PHPUnit. Se intentó construir la imagen con Podman temporal y sin privilegios. El motor pudo iniciar, pero la construcción se bloqueó antes de ejecutar el Dockerfile porque falta el archivo de configuración del host `policy.json`. No se cambió la configuración del sistema. La imagen de producción sigue sin verificar. Las verificaciones locales no sustituyen esa comprobación de despliegue.

## Reglas implementadas y límites de aceptación

Los formularios de configuración, rúbrica, cronograma, grupos, evaluación, encuesta y resultados corresponden a las interfaces requeridas. Las restricciones de membresía fija, versiones, congelación, promedio sin ponderación y publicación sin reapertura son decisiones locales propuestas. Falta su validación académica; no se describen como una norma institucional.

La demostración prueba los flujos disponibles con PostgreSQL y Firefox. La aceptación completa sigue pendiente de G4, su recorrido de archivos y evaluación, la confirmación de contratos compartidos y la revisión de seguridad dedicada que fue diferida. No se ocultan estos límites mediante archivos o notas de ejemplo en la aplicación.
