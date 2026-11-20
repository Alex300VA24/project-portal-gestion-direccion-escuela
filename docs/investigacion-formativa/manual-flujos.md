# Flujos de Investigación Formativa

El módulo permite configurar secciones, formar grupos, revisar títulos, evaluar entregas existentes, aplicar encuestas y consultar resultados. La carga y descarga de archivos permanece pendiente del contrato compartido.

## Preparación de una demostración local

Use PostgreSQL, PHP y las dependencias indicadas en el README del proyecto. Ejecute los comandos desde la raíz del repositorio. Configure primero la conexión a su servidor PostgreSQL local. Use una base nueva; no reutilice una base institucional ni ejecute el seeder raíz.

```bash
export APP_ENV=local
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=55432
export DB_USERNAME=postgres
export DB_URL=''
export PGHOST="$DB_HOST"
export PGPORT="$DB_PORT"
export PGUSER="$DB_USERNAME"
export DB_DATABASE="portal_gestion_direccion_test_demo_$(date -u +%Y%m%d%H%M%S)"
createdb "$DB_DATABASE"
php artisan module:migrate all --force
read -rs -p 'Contraseña local de al menos 12 caracteres: ' IF_DEMO_PASSWORD
export IF_DEMO_PASSWORD
export IF_DEMO_MODE=workflow
export IF_DEMO_EXPORT_DIR="/tmp/if-demo-exports"
php artisan db:seed --class='App\Modules\InvestigacionFormativa\Database\Seeders\InvestigacionDemoSeeder'
npm run build
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=127.0.0.1 --port=8059 --no-reload
```

El servidor PostgreSQL y las credenciales de estos ejemplos corresponden a la verificación local. Adapte el puerto y el usuario a su entorno. La base debe contener `_test_demo_` en su nombre. El seeder solo funciona en `local` o `testing`. No imprime la contraseña. Crea cuentas nuevas; no cambia contraseñas ni roles de cuentas existentes.

El seeder exige `IF_DEMO_MODE` con uno de dos valores. `workflow` crea el recorrido operativo sin decisiones: dos grupos, seis entregas, seis evaluaciones y una encuesta publicada con tres respuestas. `showcase` crea el mismo recorrido y agrega dos aprobaciones y un trabajo destacado vigente. Cada ejecución crea un lote nuevo con códigos propios; los lotes anteriores se conservan y cada lote usa IDs distintos.

El seeder imprime los correos, las secciones y la cantidad de datos de muestra. Exporta un directorio por lote con `demo.json` y cinco CSV. No exporta contraseñas. Los CSV contienen IDs devueltos por esa misma base y no sirven como catálogo institucional.

| Archivo exportado | Comando de validación |
|---|---|
| `identidades.csv` | `php artisan investigacion:importar-identidades RUTA --dry-run` |
| `directores.csv` | `php artisan investigacion:importar-directores RUTA --dry-run` |
| `secretarias.csv` | `php artisan investigacion:importar-secretarias RUTA --dry-run` |
| `participantes.csv` | `php artisan investigacion:importar-participantes RUTA --dry-run` |
| `competencias.csv` | `php artisan investigacion:importar-competencias RUTA --dry-run` |

Sustituya `RUTA` por el archivo del lote. `--dry-run` valida sin escribir. Solo `--apply` aplica una importación. No combine ambas opciones. Los roles de Laravel se asignan por separado; importar una identidad no concede un rol.

`competencias.csv` es una foto del lote. La sección `completa` ya tiene entregas, por lo que el comando rechaza el reemplazo de competencias en esa sección y no escribe nada. Valide el formato con un CSV que contenga solo secciones sin entregas.

La demostración incluye dos escuelas, dos periodos, dos directores, una secretaria de escuela, dos docentes responsables, un docente sin sección y seis cuentas con rol de estudiante. Una de estas también tiene rol de profesor. Incluye un estudiante retirado y otro de una escuela distinta. El plan de la escuela A tiene dos cursos: uno con Investigación Formativa y otro sin el módulo en el mismo periodo, para contrastar el denominador del indicador 3.1. La sección `completa` está configurada y tiene grupos, entregas, evaluaciones y encuesta. `incompleta` permite configurar. `deshabilitada` impide escrituras. `historica` está cerrada.

## 1. Habilitación y configuración

Actor inicial: director de la escuela. Datos iniciales: identidad y alcance importados; sección y responsable existentes en `core`.

1. Inicie sesión y abra Investigación Formativa.
2. Abra «Ver cursos y habilitar secciones». Filtre por escuela o periodo.
3. Habilite la sección deseada.
4. Inicie sesión como su docente responsable.
5. En «Competencias», seleccione el contenido importado y guarde.
6. En «Rúbrica», configure criterios y sus cuatro niveles. El puntaje mayor debe ser positivo.
7. En «Cronograma», guarde tres fechas crecientes, expresadas en hora de Lima.
8. Recargue «Mis cursos». La sección debe indicar configuración completa.

Resultado: una configuración por sección, competencias seleccionadas, rúbrica y tres entregables. La configuración tiene una versión para detectar formularios antiguos. La demostración ya realiza estos pasos en `completa`; use `incompleta` para practicarlos.

Ruta negativa: faltan competencias, niveles o fechas válidas. Se muestra el error y la sección no queda lista. Una sección deshabilitada no permite guardar. Un formulario antiguo produce conflicto; recargue y revise los valores actuales.

Reinicio: use otra base de demostración nueva. Deshabilitar una sección conserva el historial. No elimina grupos ni respuestas.

## 2. Grupo y título

Actor inicial: estudiante activo del padrón. Datos iniciales: sección habilitada y completa; integrantes sin grupo en esa sección; línea y tipo de producto existentes.

1. Abra «Mis cursos» y «Formar grupo».
2. Ingrese nombre y título. Marque los integrantes.
3. Seleccione línea de investigación y tipo de producto.
4. Guarde. El creador queda como coordinador fijo y miembro.
5. Recargue y vuelva a iniciar sesión. El grupo debe conservarse.
6. Como docente responsable, abra «Grupos» y el grupo creado.
7. Acepte el título o solicite un cambio con motivo.
8. Como coordinador, consulte el resultado. Si corresponde, edite el título y guarde.

Resultado: grupo, integrantes, proyecto y revisión se guardan de forma atómica. Un cambio de título vuelve a `Registrado`. El historial conserva las revisiones anteriores. Un integrante consulta el grupo, pero no edita el título.

Ruta negativa: estudiante retirado, integrante ya agrupado, sección incorrecta o configuración incompleta. La operación se rechaza sin crear un grupo parcial. No existe pantalla para transferir la coordinación ni modificar la membresía. El título aceptado queda fijo tras la primera entrega. El vencimiento de la primera unidad también puede impedir su edición.

Reinicio: cree un lote y una base nuevos. No borre grupos para repetir el recorrido.

## 3. Tres avances y archivos: pendiente

Actor previsto: coordinador. El esquema y las políticas representan versiones de entrega. Sin embargo, no hay una ruta funcional de carga o descarga ni un escritor compartido aprobado.

La integración G4 debe resolver el registro de metadatos, el archivo privado y sus fallos antes de aceptar este flujo. No escriba en `comun.archivo` para simularlo. El PDF `tests/Fixtures/InvestigacionFormativa/avance-demo.pdf` es una muestra para la futura prueba; no demuestra una carga funcional.

Reglas propuestas para esa integración: PDF de hasta 10 MiB, tres ventanas ordenadas y versiones inmutables. No se presentan como reglas institucionales aprobadas ni como funciones terminadas. Las pruebas de configuración sí verifican fechas crecientes, hora de Lima, congelación de rúbrica y extensiones de plazo tras entregas de prueba.

## 4. Evaluación y corrección

Actor: docente responsable. Datos iniciales: entrega existente con proyecto, sección y rúbrica válidos. La demostración vacía no crea entregas. Este recorrido se verifica con datos de prueba en `EvaluacionesTest`; la demostración completa en navegador depende de G4.

1. Abra el grupo y seleccione la versión de entrega que va a revisar.
2. Marque un nivel de cada criterio.
3. Agregue observaciones o marque que no hay observaciones.
4. Guarde la evaluación.
5. Corrija una evaluación existente y guarde de nuevo.
6. Consulte el historial. Las observaciones anteriores deben permanecer.

Resultado: los puntos se derivan de la rúbrica; no se acepta una nota arbitraria del cliente. La versión de evaluación aumenta. La entrega anterior conserva su evaluación cuando llega una versión nueva.

Ruta negativa: criterio ausente o ajeno, nivel inválido, docente distinto o versión desactualizada. La evaluación no queda parcialmente guardada. La nueva entrega sin evaluación aparece pendiente, aunque una versión anterior tenga nota.

Reinicio: ejecute la prueba en una base nueva o use otro proyecto de prueba. No sustituya archivos ni evaluaciones para reiniciar.

## 4b. Aprobación final y trabajo destacado

Actor: docente responsable. Datos iniciales: las tres unidades con su versión más reciente evaluada. Este recorrido se verifica con datos de prueba en `DecisionesTrabajoTest`; el recorrido de navegador se completa en la aceptación final.

1. Abra el grupo y confirme que las tres unidades están evaluadas en su versión más reciente.
2. Pulse «Aprobar trabajo final».
3. Pulse «Marcar como trabajo destacado» en un grupo con aprobación vigente.
4. Repita la selección con otro grupo. El trabajo anterior deja de estar destacado, pero su registro permanece en el historial.
5. Retire la selección indicando un motivo.
6. Cambie una entrega o evaluación. La aprobación y la selección deben aparecer como desactualizadas.
7. Confirme la aprobación y vuelva a seleccionar.

Resultado: cada decisión guarda actor, fecha, versión y la instantánea de las tres unidades. La selección es manual: no se elige por la nota más alta. El historial conserva reemplazos y retiros.

Ruta negativa: falta una unidad evaluada, no hay aprobación vigente, la sección está cerrada o deshabilitada, el docente no es el responsable o la versión del formulario está desactualizada. La decisión no se registra.

Reinicio: use una base nueva o continúe el historial con una decisión nueva. No borre filas de `decision_trabajo`.

## 5. Encuesta individual

Actor inicial: director. Datos iniciales: sección habilitada y padrón activo.

1. Abra «Encuesta» en la sección.
2. Guarde de 1 a 50 preguntas; cada una admite de 1 a 1000 caracteres.
3. Publique. Las preguntas quedan fijas.
4. Como estudiante activo, responda cada pregunta con un entero de 1 a 5.
5. Agregue un comentario opcional de hasta 2000 caracteres y envíe.
6. Recargue y vuelva a iniciar sesión. Debe aparecer la confirmación.
7. Como director, cierre la encuesta cuando corresponda.

Resultado: se guarda una cabecera de respuesta y todos sus ítems en una sola transacción. Reenviar no reemplaza la respuesta original. El cierre impide nuevas respuestas y no ofrece reapertura. Las respuestas enviadas siguen disponibles después de deshabilitar la sección.

Ruta negativa: falta una respuesta, se repite una pregunta, la encuesta está cerrada o el estudiante se retiró. No se registra una respuesta parcial. Los comentarios son confidenciales; la base conserva identidad para impedir duplicados. No se promete anonimato.

Reinicio: use un lote nuevo. No existe una acción de borrado o reapertura para repetir la encuesta.

## 6. Resultados e historial

Actor: director. Datos iniciales: escuela asignada y periodos reales con secciones.

1. Abra el resumen de dirección y seleccione escuela, periodo, curso o línea de investigación.
2. Revise los totales y las tarjetas descriptivas: proyectos por línea, umbrales de nota y decisiones docentes.
3. Abra «Ver resultados» de una sección.
4. Consulte la encuesta, sus promedios y la distribución por pregunta. Con un filtro de línea activo, la encuesta sigue siendo de la sección completa.
5. Cambie de página o tamaño en comentarios. Los filtros deben conservarse.
6. Consulte las notas por unidad, la nota final y todos los empates.
7. Consulte los conteos de trabajos aprobados y destacados, y el trabajo destacado de la sección.
8. Vuelva al historial y abra una sección cerrada de otro periodo.
9. Pulse «Descargar PDF» para guardar el reporte con los filtros aplicados. El archivo no incluye los comentarios confidenciales de la encuesta y no crea un registro de archivo.

Resultado: se cuentan cabeceras de respuesta, no ítems. La participación usa el padrón activo actual. Puede superar el 100% después de retiros. Sin participantes activos, la tasa no se calcula. Los proyectos incompletos no entran en la clasificación ni se convierten en ceros. El promedio indica cuántos proyectos incluye. El PDF exporta los mismos datos visibles con los filtros activos, sin comentarios confidenciales.

El tablero muestra barras y tablas equivalentes. «> 16» y «> 18» son umbrales estrictos y superpuestos: un proyecto con nota mayor a 18 también cuenta en mayor a 16. La base de ambos es el total de proyectos completos. Los conteos son estadística descriptiva, no los tres indicadores oficiales de Calidad. La línea restringe grupos, notas y decisiones; no desagrega la encuesta, que se prepara por sección.

Ruta negativa: escuela, curso o línea fuera del alcance, sección ajena o permiso ausente. Se devuelve 403. Una consulta válida sin datos muestra un estado vacío. No existen tendencias ni notas de ejemplo en las pantallas reales.

## Verificación de navegador

El recorrido admite Chromium o Firefox. Con Chromium, inicie ChromeDriver en una terminal: `chromedriver --port 9516`. Con Firefox, inicie `geckodriver --host 127.0.0.1 --port 4449`. Mantenga el servidor local iniciado con `--no-reload` para conservar las variables de conexión. En otra terminal, defina la contraseña del mismo lote y ejecute:

```bash
export IF_BROWSER=chromium
export CHROMIUM_BINARY=/ruta/a/chromium
export WEBDRIVER_URL=http://127.0.0.1:9516
export IF_DEMO_MANIFEST=/ruta/al/lote/demo.json
export IF_BROWSER_OUTPUT=/ruta/nueva/para/evidencia
python3 tests/Browser/investigacion_formativa.py
```

Use un lote sin grupos ni encuestas previas. El destino de evidencia debe ser nuevo y quedar fuera de los archivos versionados. Se guardan HTML, capturas y `results.json`. El recorrido empieza habilitando y configurando la sección de demostración `IFD-deshabilitada`. Ese nombre describe el estado inicial. Guarda competencias, rúbrica y cronograma, comprueba fechas fuera de orden y forma un grupo en esa misma sección. Después solicita un cambio de título con motivo, lo corrige como coordinador, lo acepta como docente y verifica la consulta del integrante sin controles de edición. También publica y cierra una encuesta, registra una respuesta y filtra el historial. La secretaría prepara y publica la encuesta de otra sección `incompleta` sin poder cerrarla; un integrante responde, la dirección ve el agregado sin el nombre del estudiante y cierra la encuesta. Comprueba persistencia mediante páginas nuevas, recarga de la ficha del grupo e inicio de sesión. Compara anchos de 1280 y 390 píxeles. No comprueba carga, descarga ni evaluación en navegador mientras falte G4. Un resultado de este script no equivale a aceptación completa ni a una auditoría de accesibilidad.
