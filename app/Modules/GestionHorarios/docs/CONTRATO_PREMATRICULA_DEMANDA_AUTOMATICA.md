# Contrato propuesto: demanda automática de Prematrícula

La demanda agregada vigente no demuestra población por sección. `id_seccion_preferida` en una solicitud es una preferencia y no debe transformarse en una asignación. Para población real o estimada por sección se necesita un proveedor y un contrato institucionales independientes, con cobertura, estados y corte verificables; consulte `POBLACION_COHORTES_SUBGRUPOS.md`.

Estado: propuesta pendiente de acuerdo e implementación por el proveedor. No existe sincronización automática activa en el portal.

El consumidor no debe importar clases de Prematrícula ni consultar sus tablas internas. El proveedor institucional debe publicar una interfaz autorizada que entregue una instantánea coherente por periodo y un mecanismo de notificación durable. La API independiente de referencia describe demanda estimada; no debe confundirse con matrícula definitiva.

## Datos mínimos

- Identificador institucional del periodo, estado y versión monotónica de la instantánea.
- Identificador institucional de curso-plan y curso, con totales de solicitudes y preferencias de mañana y tarde, desglosados por estado de validación.
- Política explícita de inclusión de solicitudes pendientes, modificaciones y anulaciones. Los totales deben representar el estado vigente, no sumas de eventos.
- Identificador único del evento, periodo, versión y tipo de cambio. La versión debe cambiar por cualquier modificación relevante, incluida una anulación.

El proveedor debe garantizar que todas las páginas pertenezcan a la misma versión, definir autenticación y autorización entre servicios, paginación, retención, límites, errores y recuperación desde una versión anterior. Los identificadores deben corresponder a los catálogos institucionales; no se debe inferir una equivalencia por nombre. No se requieren datos personales para los totales.

## Comportamiento requerido del consumidor

Un proceso de servidor debe recibir las notificaciones o consultar cambios mediante un programador y una cola operativos sin sesión de navegador. Los eventos se tratan como aviso para obtener la instantánea completa; su recepción no certifica que la lectura esté completa. Debe haber reintentos con espera, registro de errores y conciliación periódica que recupere notificaciones perdidas.

La aplicación debe serializar las actualizaciones por periodo, confirmar la versión aplicada en la misma transacción que los datos, ignorar duplicados y versiones anteriores, y rechazar lecturas incompletas o inconsistentes. El consumidor debe conservar trazabilidad de versión y resultado. La aplicación de una demanda nueva no puede alterar horarios aprobados o publicados: debe conservarlos y registrar que existe una revisión posterior para una nueva propuesta.

El transporte, la política de versiones de origen y la operación del worker y programador requieren coordinación institucional antes de activar la integración automática.

## Preparación local implementada

`InstantaneaDemanda` acepta un periodo y versión enteros positivos y una lista completa de cursos con `id_curso_plan`, `total`, `manana` y `tarde` enteros. Rechaza cursos duplicados y preferencias inconsistentes y ordena los cursos para obtener una huella estable. Este formato es un **subconjunto provisional**, no sustituye los desgloses por estado ni acredita que la instantánea esté completa.

`ProcesarDemanda` comprueba identidades mediante `CatalogoDemanda` y distingue versión nueva, antigua, duplicada o conflictiva mediante `RepositorioDemanda`. `ProcesadorInstitucional` conecta el catálogo compartido y el repositorio PostgreSQL mediante una conexión explícita. El consumidor no está registrado como listener ni expuesto como endpoint; no hay conexión con el proveedor, reintentos de entrega ni registro durable de fallos.

## Almacenamiento y concurrencia

La migración incremental crea `gestion_horarios.demanda_instantanea` con periodo, versión, huella SHA-256, fecha de recepción y bandera `requiere_revision`; `demanda_curso` conserva los agregados por curso-plan, sin datos personales. Cada par periodo-versión y cada par instantánea-curso son únicos. La versión vigente es la mayor versión aceptada de cada periodo; las anteriores permanecen como historial. La bandera queda activa cuando existía cualquier horario del periodo al guardar la instantánea. Es una señal conservadora, no un flujo de revisión ni una comparación contra la demanda usada por un horario.

El repositorio ejecuta la decisión y escritura en una transacción PostgreSQL con `pg_advisory_xact_lock` por periodo, también para la primera versión. Una versión igual y huella igual devuelve `duplicada`; una igual con contenido diferente genera conflicto; una menor devuelve `antigua`; una mayor se guarda. Un error revierte cabecera y cursos. Laravel reintenta hasta tres veces los errores de concurrencia que reconoce; el futuro mecanismo de entrega deberá gestionar fallos y reintentos de origen de forma durable. Solo los escritores que utilicen este mismo bloqueo participan en la serialización; las restricciones únicas ofrecen una defensa adicional.

La migración añade `horario.id_demanda` opcional, con clave compuesta que exige el mismo periodo. No se rellena automáticamente y la generación todavía debe guardar la instantánea utilizada. Recibir demanda no actualiza ni elimina horarios. Para garantizar la señal de revisión frente a la creación o aprobación simultánea de horarios, el futuro flujo de generación y publicación debe coordinarse con el mismo bloqueo y comparar la versión vigente; esa carrera aún no está resuelta. La validación actual comprueba existencia de periodo y curso-plan, no su relación académica o vigencia. Los campos adicionales del proveedor no forman parte de la huella provisional y no deben considerarse procesados.

La migración solo se ha ejecutado en PostgreSQL aislado de prueba, con tablas mínimas de catálogos y la migración real de `horario`. No se ejecutó sobre la base institucional. La instalación requiere desplegar la migración de forma coordinada; el contenedor existente puede ejecutar migraciones al iniciar.

El proveedor debe acordar además cómo identifica cada estado de validación, el significado de preferencias ausentes, las anulaciones y los cambios de plan; proporcionar un identificador y revisión estables de evento, snapshot completo y consistente, versión monotónica por periodo, autenticación entre servicios, mecanismo durable de recuperación y garantías de paginación. Los equipos deben acordar la correspondencia de IDs institucionales y la política de publicación antes de conectar datos reales.
