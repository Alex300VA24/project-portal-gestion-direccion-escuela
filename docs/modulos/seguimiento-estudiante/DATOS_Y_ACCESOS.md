# Datos y accesos de Seguimiento al Estudiante

Estado al 19 de noviembre de 2026. Este documento registra decisiones de diseño para integrar el código. La provisión de registros de negocio, cuentas y archivos históricos queda fuera de la entrega solicitada.

Consulta el [mapa de tablas y atributos](#mapa-de-tablas-y-atributos-de-sse-epi-al-monolito) para revisar cada campo y sus pendientes.

## Inventario verificado por flujo

Los archivos de la columna SSE-EPI se consultaron en el árbol equivalente a `main` en `0fe2fbe`. Las rutas de origen se ubican en `src/routes/`, los servicios en `src/app/Services/` y las pruebas citadas en `src/tests/Feature/`. Los destinos son propuestas de integración. El portal partía de 23 tablas en `seguimiento_estudiante`. Las seis migraciones de la etapa 3 elevan el total a 29. Las rutas actuales y la tarjeta del panel ya registran la entrada `seguimiento-estudiante.index`.

| Entrega y superficie | SSE-EPI y dependencias de datos | Destino previsto y comprobación |
| --- | --- | --- |
| A. Entrada, cuenta y expediente | `web.php`, `auth.php`, `account.php`, `estudiante.php`, `staff_students.php`. `AccessContextService` y `AcademicAccessRepository` dependen de `usuario`, `usuario_roles`, `dicta`, `habilita`, `asigna` y `matricula`. `StudentWorkspace` usa `estudiante_acompanamiento.datos`. | Sesión del portal, Spatie, vínculo con `core.persona` y perfiles académicos. Clasificar cada pantalla de cuenta como función institucional o preferencia propia. Reutilizar los escenarios de `PortalAccessTest` y `AccessBoundaryC07Test` para probar acceso propio, ajeno y sin vínculo. |
| A. Espacio docente y consultas API | `profesor.php` y `api.php`. Carga, alumnos y secciones. La API ofrece tres lecturas de resumen estudiantil, turnos y resumen administrativo con sesión web y límite de peticiones. | Alcance académico desde `core.seccion`, Horarios y Matrículas. Preservar respuestas de dato desconocido y ausencia de acceso. Referencia de prueba `Phase4ModernizationApiTest`. |
| B. Secretaría y fuentes | `secretaria.php`, `seguimiento.php`. `ReceiptWorkflow`, `ReceiptProcessing`, `ReceiptTaskQueue` y `SourceIntegration`. Borradores y lotes en `sse_operacion.datos`, CSV privados y datos de procedencia. | Persistencia de recepción propia y contratos de fuente. Definir el destino de matrículas, notas y asistencia con el equipo dueño. Preservar una aplicación por revisión, recuperación y procedencia. Referencias `ReceiptConcurrencyTest` y `SecretariaReceiptAsyncProcessingTest`. |
| C. Tutoría, asignaciones y atención | `tutoria.php`, `coordinacion.php`, `estudiante.php`. `TutorRoster`, `TutoringAssignments`, `TutorialCases`. Operan sobre `habilita`, `asigna`, `atencion`, `atencion_programaciones` y `participa_en`. | `tutor_asignacion`, `solicitud_estudiante`, `atencion` y `atencion_compromiso`. Definir historial de roles en la atención, vigencia, reprogramaciones y cancelaciones. Referencias `AssignmentCapacityConcurrencyTest`, `AssignmentClosureTest` y `TutorialAcceptanceClosureTest`. |
| D. Consejería, alertas y derivación | `consejeria.php`, `coordinacion.php`, `seguimiento.php`. `WorkspaceRepository`, `RiskEvaluator`, `CounselingReferral` y `ReferralReceipts`. Usan `consejeria_ambito`, `consejeria_registro`, `consejeria_criterio`, datos de carga y filas de derivación. La agenda usa bloqueo transaccional. | `parametro_sat`, `alerta_sat`, `atencion_derivacion` y contrato con Horarios y Bienestar. Conservar evidencia, transiciones, alcance por sección y respuesta del caso. Referencias `CounselingAgendaTest` y `CounselingReferralWorkflowTest`. |
| E. Perfil de ingreso y nivelación | `seguimiento.php`, `estudiante.php`, `profesor.php`, `admin.php`. `AdmissionProcess`, `LevelingScheduleReceipt` y `LevelingResultReceipt`. Perfil y puntajes por área, programas, inscripciones, resultados y fuentes autorizadas. | `perfil_ingreso`, `brecha_ingreso`, `programa_nivelacion` y `nivelacion_*`. Definir evaluaciones por período, procedencia, aprobaciones y enlaces con horarios y resultados oficiales. Referencias `AdmissionSp1Test` y `LevelingWorkflowTest`. |
| F. Planes, informes y documentos | `tutoria.php`, `coordinacion.php`, `admin.php`. `TutoringPlan`, `GeneralTutoringPlan`, `ActivityReports` e `InstitutionalArchive`. Planes, revisiones y respaldos en almacenamiento operativo y privado. Plantillas F1–F7 y Dompdf. | Persistencia propia de planes e informes y catálogo `comun.archivo`. Revisar versiones, aprobaciones, alcance de descarga y empaquetado de plantillas. Referencias `TutoringDocumentsAndContinuityTest` y `CounselingDocumentWorkflowTest`. |
| F. Indicadores y predicción | `admin.php`, `coordinacion.php`, `secretaria.php` y comando `prediccion:desaprobacion`. `MeasurementRegister`, `IndicatorBoard` y `FailurePilot`. Indicadores I1–I8, cortes y respaldos en `sse_operacion`. El piloto usa Python y scikit-learn. | Registro de Seguimiento y contrato con Calidad para publicar resultados sin perder población, corte o revisión. El piloto requiere validación separada de binarios y datos de entrada. Referencia `IndicatorMeasurementWorkflowTest`. |
| G. Continuidad, cierre y rectificación | `seguimiento.php` y `staff_students.php`. `LongitudinalProcess`, `ContinuityProcess` y `ClassificationEngine`. Expediente y ciclo de vida en `estudiante_acompanamiento.datos`, dependientes de evidencia recibida. | `situacion_academica` y estructura propia para evidencia, transiciones e historial. Contrato con Matrículas para continuidad. Verificar cierre y rectificación con los escenarios de `AcceptanceAcademicClosureTest` y `AcceptanceSupportClosureTest`. |
| Transversal. Dirección y administración técnica | `admin.php` y `superadmin.php`. Cuentas, designaciones, configuración, conexiones y auditoría. | Separar las acciones académicas del módulo de la gestión institucional del portal. Acordar responsable de conexiones y permisos administrativos antes de habilitar sus rutas. |

En todas las entregas, los controladores, las vistas Blade y sus recursos se adaptarán juntos. Cada PR registrará la correspondencia de rutas y validará los formularios y enlaces de las pantallas afectadas.

### Dependencias de ejecución por entrega

| Entrega | Recursos que requiere la función |
| --- | --- |
| A–G | Namespaces del módulo, prefijo de rutas, entradas Vite del portal y autenticación compartida. `sse.php` aporta el período y los interruptores de origen. |
| B | `ProcessReceiptImportJob`, `secretaria:recuperar-recepciones`, almacenamiento privado de lotes y reglas de `sources.php`. La recuperación se programa cada minuto al habilitar el procesamiento asíncrono. |
| C–D | Asignación tutorial programada, agenda con zona horaria explícita, recordatorios y notificaciones. `GenerateCounselingDocumentPdfJob` y `counseling_documents.php` intervienen en los documentos de consejería. |
| E | `nivelacion.php`, catálogos y formatos de ingreso y nivelación, registro de fuentes autorizadas definido en `seguimiento.php` y tarea `nivelacion:actualizar`. |
| F | Plantillas institucionales, almacenamiento privado, Dompdf, gráficos y, en la función correspondiente, Python. El importador curricular depende de `pdftotext`. |
| G | `RunClassificationBatchJob`, `seguimiento:actualizar` y reglas de fuentes y salida en `seguimiento.php`. |

Los comandos de instalación y datos de ejemplo del origen sirven como referencia de pruebas. La estructura integrada se prepara con migraciones modulares. Los contratos con los equipos dueños de los datos deben quedar acordados antes de habilitar el flujo correspondiente.

## Estructura incorporada en la etapa 3

Las seis migraciones agregan tablas vacías al esquema `seguimiento_estudiante`. El catálogo actualizado está en la [documentación general de base de datos](../../base_de_datos.md).

| Tabla | Identidad y alcance | Contenido y reglas |
| --- | --- | --- |
| `operacion` | PK compuesta por `id_escuela` (FK a `core.escuela_profesional`) y `clave` de hasta 255 caracteres | Un objeto JSONB `datos` por agregación operativa y su `actualizado_en`. Recibe el diseño de lotes y borradores de Secretaría, planes, informes, configuraciones operativas e indicadores por ventana y alcance. La escuela forma parte de toda lectura y escritura. |
| `expediente` | PK `id_estudiante`, FK a `core.estudiante` | Objeto JSONB para el contexto longitudinal y `revision` entera no negativa. El servicio obtiene la escuela del estudiante. La recepción de perfil, observaciones, seguimientos, derivaciones, notificaciones y auditoría conservarán sus claves y fechas dentro del flujo correspondiente. |
| `consejeria_ambito` | PK `id_ambito` y combinación única de escuela, docente y período | Las tres referencias apuntan a `core`. El ámbito fija el conjunto de registros y ofrece una fila para serializar sus cambios. |
| `consejeria_registro` | PK `(id_ambito, clave)`. Sección y estudiante opcionales, con FK a `core` | Objeto JSONB, fecha de actualización y tipo entre `case`, `alert`, `plan`, `report`, `audit`, `notification`, `meeting`, `document` y `event`. El servicio debe comprobar la correspondencia de escuela, sección, período y estudiante antes de escribir. |
| `atencion_evento` | PK `id_evento`. Cada fila referencia exactamente una solicitud o una atención | Programación, reprogramación o cancelación. Las dos primeras requieren `fecha_programada`. Motivo, modalidad y actor de `public.users` son opcionales. `registrado_en` conserva el momento en que se anotó el evento. |
| `atencion_participante` | PK `(id_atencion, id_persona, papel)`, con FK a la atención y a `core.persona` | Una persona puede cumplir varios papeles. `asistio` es booleano opcional y distingue asistencia pendiente de asistencia negativa. |

### Transacciones y límites de responsabilidad

- Para cambiar `operacion` o `expediente`, el servicio insertará la fila inicial con manejo de conflicto, adquirirá un bloqueo `FOR UPDATE` sobre su clave y persistirá el cambio dentro de la misma transacción. El expediente incrementará la revisión en esa escritura y los flujos de reintento comprobarán la revisión o el identificador de origen. Para recepciones con cola de base de datos, el estado de la tarea y su fila de cola usarán la misma conexión PostgreSQL si se aplica el despacho transaccional de SSE-EPI.
- Consejería bloqueará la fila de `consejeria_ambito` antes de modificar varios registros. La validación de cruces de agenda seguirá usando un bloqueo transaccional compartido que cubra escuela y período. Estos mecanismos se conectarán y probarán con sus servicios al trasladar los flujos.
- Las tablas de atenciones y sus relaciones conservan los datos vigentes, mientras que `atencion_evento` conserva el historial. Un cambio de programación se guardará junto con su evento. Los datos académicos oficiales conservan su fuente institucional. El JSONB guarda el contexto y la evidencia de Seguimiento que requiere cada flujo, con una fuente definida para cada hecho.
- Las políticas de la etapa 4 resolverán los permisos por expediente. Los contratos de Matrículas, Horarios, Bienestar y Calidad y las reglas cardinales de tutoría se resuelven con cada flujo. La elección de persistencia no autoriza cambios en los esquemas de esos módulos.

Se verificaron el esquema completo, los conteos y el aislamiento de claves foráneas en PostgreSQL 18.6 temporal. La segunda pasada detectó cero migraciones pendientes. La reversión retiró las seis tablas nuevas y la reaplicación restauró el total de 29. PostgreSQL rechazó un arreglo JSON en un expediente, un evento sin atención ni solicitud y un tipo de consejería inválido.

### Ampliación de tutoría y atenciones

- `tutor_asignacion` tiene `id_usuario_asignador` como referencia a la cuenta que realizó la asignación. Los campos `fecha_inicio`, `fecha_fin`, `motivo`, `causa_fin` y `observacion` conservan la vigencia y su justificación. El inicio de vigencia es independiente de `fecha_asignacion`. Los campos admiten valores ausentes para las asignaciones existentes. Si se conocen las dos fechas de vigencia, PostgreSQL exige que el fin sea igual o posterior al inicio.
- `atencion` tiene cuatro campos de texto opcionales para la ficha de sesión. `actividad`, `aspectos_tratados`, `observaciones` y `resultado` permiten registrar cada dato por separado conforme al origen.
- La autorización del asignador y la vigencia efectiva se comprobarán al conectar el servicio. El historial de reasignaciones al mismo docente y el estado y curso de una atención todavía necesitan diseño por flujo.

## Persistencia por flujo

| Área | Base disponible en el portal | Adaptación que debe resolver el módulo |
| --- | --- | --- |
| Identidad académica | `core.persona`, `core.estudiante`, `core.docente`, `seguridad.usuario` | Vincular la cuenta del portal con su identidad académica y alcance |
| Cuenta y autorización | `public.users` y tablas de Spatie | Reutilizar la sesión y resolver permisos por acción y expediente |
| Perfil y brechas | `perfil_ingreso`, `dimension_brecha`, `brecha_ingreso` | Definir los puntajes separados, completitud y procedencia requeridos por SSE-EPI |
| Situación académica | `situacion_academica` y contrato de historial con Matrículas | Distinguir resultados recibidos, calculados y evidencia ausente |
| Asignación tutorial | `tutor_asignacion` | Resolver habilitación, fechas de vigencia, responsable, motivo e historial |
| Solicitudes y atenciones | `solicitud_estudiante`, `atencion` y sus detalles | Conservar programación, reprogramación, cancelación, participantes y compromisos |
| Alertas y seguimiento | `parametro_sat`, `alerta_sat`, `atencion_compromiso` | Conservar evidencia, revisiones, responsables y cierre longitudinal |
| Derivaciones | `atencion_derivacion` y contrato con Bienestar | Definir referencia, recepción, contrarreferencia e historial de recomendaciones |
| Nivelación | `programa_nivelacion` y tablas `nivelacion_*` | Resolver objetivos, aprobación, informes, cursos y resultados del proceso original |
| Planes e informes | Persistencia propia a completar | Definir versiones, metas, aprobación, revisión y archivos asociados |
| Indicadores | Registro operativo propio y contrato con Calidad | Conservar población, corte, método, revisión y evidencia de cada medición |
| Archivos | `comun.archivo` y almacenamiento privado | Resolver responsable de carga, acceso, ubicación y persistencia física |
| Estados JSONB | Persistencia propia a definir | Inventariar contenido de `sse_operacion.datos`, consultas y bloqueos utilizados |

Las tablas sin esquema indicadas en la columna del portal pertenecen a `seguimiento_estudiante`. Cada campo que se incorpore se documentará con tipo, nulabilidad, clave, valores admitidos, origen y regla de negocio. Las migraciones propias se guardarán en `app/Modules/SeguimientoEstudiante/Database/Migrations/`.

## Restricciones que afectan al diseño

- `perfil_ingreso` admite un perfil por estudiante. El origen contiene información de período que requiere una decisión explícita de conservación estructural.
- `atencion` exige asignación y fecha de atención. Las solicitudes pendientes deben tener una representación válida antes de programarse.
- `tutor_asignacion` impone unicidad por estudiante, docente, período y tipo. La regla de una asignación vigente se contrastará con la del proceso original.
- `calidad.medicion_indicador` impone una medición por indicador y período. El contrato de publicación debe contemplar el desglose y las revisiones que conserva SSE-EPI.
- Las relaciones de un módulo con otro se resuelven por contratos y referencias lógicas. Las FK se limitan al esquema propio y los esquemas compartidos permitidos por la guía.
- Los identificadores de persona, cuenta, estudiante y docente tienen funciones distintas. Las políticas deben obtener el perfil correspondiente a la escuela y período del recurso.

## Usuarios y permisos propuestos

| Perfil del origen | Integración propuesta | Alcance que debe comprobar el servidor |
| --- | --- | --- |
| `ESTUDIANTE` | Rol institucional `alumno` y permisos de consulta o solicitud | Identidad y expediente propios |
| `DOCENTE` | Rol institucional `profesor` y permisos docentes | Carga académica y secciones del período |
| `TUTOR` | Permisos de tutoría y habilitación | Asignaciones vigentes y estudiantes correspondientes |
| `CONSEJERO` | Permisos de consejería | Función habilitada y alcance académico |
| `COORDINADOR` | Rol o conjunto de permisos específico a acordar | Escuela, período y operaciones de coordinación |
| `ADMIN` | Correspondencia con Dirección a verificar | Acciones de gestión autorizadas para la escuela |
| `SECRETARIA` | Rol institucional `secretaria` con permisos del módulo | Recepción y registro dentro de su alcance |
| `SUPERADMIN` | Correspondencia con administración técnica a acordar | Atribuciones técnicas y combinaciones de roles permitidas |

Esta matriz es una propuesta. La equivalencia final se contrastará con las reglas de SSE-EPI y la administración del portal. Los nombres de permisos propios usarán el prefijo `seguimiento-estudiante.`. Las políticas comprobarán el registro concreto y la vigencia del alcance además del permiso general.

La definición técnica de roles y permisos se preparará para revisión. Su alta en una base compartida corresponde al aprovisionamiento del portal y requiere coordinación con su responsable. Las cuentas sintéticas pertenecen al entorno aislado de pruebas.

## Acceso implementado en la fase 4

`seguimiento_estudiante.identidad_cuenta` vincula una cuenta de `public.users` con una persona de `core.persona`. La cuenta es la clave primaria y la persona tiene una restricción de unicidad. El vínculo se registra por ID verificado por la administración. El correo, el código universitario y el nombre visible pueden cambiar durante la vida de la cuenta. La eliminación de la cuenta retira el vínculo y la relación protege la integridad de la persona mientras existe el vínculo. Esta tabla elevó el total del esquema de 29 a 30.

| Permiso en Spatie, guard `web` | Alcance habilitado |
| --- | --- |
| `seguimiento-estudiante.view` | Entrada al módulo y consulta de fichas propias identificadas por la persona vinculada. La cuenta debe tener una fila en identidad_cuenta. |
| `seguimiento-estudiante.tutoria.view` | En combinación con el permiso general, consulta de las fichas de estudiantes con asignación tutorial al docente vinculado. Se valida el docente activo, la asignación activa de tipo tutoría y las fechas de vigencia en un período activo. El cálculo de vigencia usa America/Lima. |

La lista y la consulta directa usan el mismo filtro por estudiante. La escuela de cada ficha se obtiene del registro académico alcanzado por el vínculo o por la asignación de tutor. La ficha muestra nombre, código y estado académico. Las consultas y acciones incorporadas en la fase 5 tienen permisos por acción y filtros por expediente y período.

El acceso sin sesión redirige al login del portal. La API exige la misma sesión y devuelve 401 en JSON para invitados. La falta de permiso o vínculo devuelve 403. La consulta fuera del alcance responde 404 igual que una ficha inexistente. La habilitación detallada por función de SSE-EPI, los permisos de escritura, los roles de Secretaría, Coordinación y Dirección y su alcance institucional se definen al integrar sus flujos.

Para habilitar una cuenta real, la administración verifica la identidad institucional, registra el vínculo por IDs y asigna los permisos efectivos acordados en Spatie. La migración solo incorpora la estructura de vínculos. El formulario de ingreso, recuperación, perfil y cierre de sesión corresponden al portal. Se retiraron las rutas de demostración de crear y guardar del módulo porque todavía carecían de persistencia.

## Solicitud de tutoría en la fase 5

| Acción | Permiso | Alcance |
| --- | --- | --- |
| Crear solicitud | `seguimiento-estudiante.view` y `seguimiento-estudiante.solicitud.create` | Cuenta vinculada, perfil propio activo y período académico activo. El perfil se resuelve desde la ruta y la identidad autenticada. |
| Consultar solicitudes propias | `seguimiento-estudiante.view` | Ficha académica de la persona vinculada. |
| Consultar solicitudes como tutor | `seguimiento-estudiante.view` y `seguimiento-estudiante.tutoria.view` | Estudiante con asignación vigente y mismo período de la solicitud. |
| Pedir cambio de cita | Permisos de creación de solicitud | Ficha propia activa, solicitud pendiente o programada y cita futura si existe. |
| Gestionar la cita y su atención | Permisos de consulta como tutor y `seguimiento-estudiante.tutoria.programar` | Solicitud de un estudiante activo con asignación vigente al docente autenticado en el período de la solicitud. Las citas ya programadas se editan únicamente desde la asignación que las creó. |

El formulario guarda el motivo como descripción de una solicitud de tipo `Tutoria` en estado `Pendiente`. El estado, el autor académico y el tipo se determinan en el servidor. La validación aplica un motivo de 5 a 2000 caracteres y fechas del período en America/Lima. Se conserva el límite de diez envíos por minuto del origen.

La migración `2026_11_20_000010` agrega `id_periodo`, `tipo` y `clave_envio`. Son opcionales para conservar las solicitudes anteriores. La vista muestra las solicitudes identificadas explícitamente como tutoría. La clave UUID es única por estudiante. La transacción bloquea el perfil y comprueba esa clave antes de insertar. Un reintento idéntico devuelve la confirmación y una clave reutilizada con otro período o motivo devuelve un error de validación.

La cita exige una fecha futura en America/Lima, una duración de 10 a 120 minutos, modalidad presencial o virtual, lugar o enlace de hasta 250 caracteres y una nota de 5 a 1000 caracteres. Se guardan la cita en `atencion`, el evento con el usuario y la nota en `atencion_evento` y el estado `Programada` en la solicitud dentro de la misma transacción. El estudiante puede leer los datos de su cita en el expediente. La nota se conserva en el historial.

La agenda del módulo compara intervalos de las atenciones del docente o del estudiante. Para atenciones previas sin duración se usan 30 minutos al comprobar cruces. Dos intervalos consecutivos pueden compartir el minuto de fin e inicio. El bloqueo de la solicitud y de la operación de agenda evita duplicar una programación al recibir escrituras simultáneas desde este flujo. La comprobación contra las agendas de Consejería y otras fuentes depende de su integración.

La migración `2026_11_20_000011` agrega `duracion_minutos` y `lugar` a las dos tablas de atención. Ambos campos quedan opcionales para conservar los registros anteriores. Antes de habilitar la acción, la administración debe confirmar que el catálogo `seguimiento_estudiante.tipo_atencion` contiene el tipo `Tutoría` y otorgar los permisos correspondientes a las cuentas vinculadas. Las migraciones solo aplican estructura.

La reprogramación conserva la cita original como evento y actualiza la atención vigente. La cancelación conserva el evento y libera la agenda. El estudiante puede pedir una reprogramación o cancelación antes de la cita, con una sola petición abierta por solicitud. El tutor puede atender la petición o mantener la cita con una respuesta visible para el estudiante. El resto de las notas de agenda se guarda en el historial. La migración `2026_11_20_000014` conserva motivo, solicitante, respuesta, responsable y fechas de cada petición.

Desde la hora de la cita, el tutor puede registrar asistencia, inasistencia o sesión no realizada. Con asistencia se exigen actividad y acuerdos. Las rectificaciones conservan una revisión anterior y exigen motivo. La migración `2026_11_20_000012` añade control de versión, revisiones de atención y una marca para identificar el compromiso originado en la sesión. El estudiante ve resultado y acuerdos vigentes. Las observaciones de sesión quedan reservadas al tutor de la cita. Si un compromiso de la sesión ya figura como cumplido, la corrección debe conservarlo.

## Coordinación de Tutoría

El permiso `seguimiento-estudiante.tutoria.asignar` permite operar si la persona vinculada tiene una designación en `coordinacion_ambito` para la escuela y el período activo. La elección del docente requiere una habilitación en `tutor_habilitado` para el mismo ámbito, un docente académico activo y ausencia de designación de Coordinación en ese ámbito. Las dos relaciones son propias del módulo. Administración debe provisionarlas a partir de designaciones verificadas. La migración `2026_11_20_000015` crea únicamente su estructura.

Se aplica la capacidad por defecto de SSE-EPI de 20 estudiantes distintos por docente y período. La carga incluye las asignaciones activas con fecha de fin vigente o sin fecha de fin, incluso las futuras y las de otras escuelas. Coordinación envía el identificador de la asignación observada para detectar cambios concurrentes. La reasignación deja un registro histórico del tutor anterior y su motivo, invalida las citas de solicitudes aún abiertas y devuelve esas solicitudes a `Pendiente`. El nuevo tutor las recibe por su asignación, y las atenciones finalizadas conservan su autor. La agenda excluye citas canceladas mediante sus eventos.

La restricción de unicidad se conserva para las asignaciones activas al mismo docente. Un docente puede volver a atender al mismo estudiante después de una reasignación con un nuevo registro, sin reutilizar el anterior. Si se acumulan varias asignaciones históricas del mismo estudiante, docente, período y tipo, la reversión de la migración se detiene porque la restricción anterior no admite esas filas.

Desde una atención en estado `Atendida`, el tutor puede marcar como cumplido el compromiso de esa sesión. El estudiante ve el estado del compromiso en su ficha. La migración `2026_11_20_000016` conserva la fecha y la cuenta que lo marcó. Una corrección de atención conserva los compromisos que ya se cumplieron.

La bandeja de avisos de Tutoría usa `tutoria_aviso`. Guarda avisos de asignación, solicitudes, cambios, resultados y compromisos en la misma transacción que la operación. Se entregan a la cuenta del portal vinculada al destinatario en el momento del evento. Cada aviso se consulta y se marca como leído desde esa cuenta y su vínculo académico vigente. Los mensajes son internos y genéricos, sin nombres, motivos, observaciones ni enlaces a expedientes. Si el destinatario todavía carece de cuenta vinculada, su expediente mantiene el estado de la operación.

## Otros recorridos implementados en la fase 5

| Acción | Permiso adicional a `seguimiento-estudiante.view` | Fuente y alcance |
| --- | --- | --- |
| Prevalidar CSV de Secretaría | `seguimiento-estudiante.secretaria.prevalidar` | Cuenta vinculada. Revisa archivos de hasta 2 MB y 3.000 filas. Conserva identificadores como texto. Muestra incidencias por fila. El archivo se procesa durante la petición HTTP, sin persistir filas ni consultar identidades. |
| Consultar alertas | `seguimiento-estudiante.tutoria.view` y `seguimiento-estudiante.alerta.view` | `alerta_sat`, por estudiante y período de la asignación tutorial vigente. La consulta muestra el valor detectado y el código del parámetro guardado. |
| Consultar situación académica | `seguimiento-estudiante.tutoria.view` y `seguimiento-estudiante.continuidad.view` | `situacion_academica`, por estudiante y período de la asignación tutorial vigente. Muestra la condición y promedio registrados, con su fecha de cálculo. |
| Consultar ingreso y nivelación | Ficha estudiantil de la persona vinculada | Perfil y brechas propios. Programas y notas requieren fecha de publicación, añadida por la migración `2026_11_20_000013`. Las filas anteriores esperan evidencia de publicación. |
| Guardar y presentar aporte al plan de tutoría | `seguimiento-estudiante.tutoria.view` y `seguimiento-estudiante.plan-tutoria.editar` | Aporte propio, por escuela y período de asignación vigente. Se conserva cada versión, el usuario, la población, los apartados y actividades en `operacion`. El identificador de envío hace idempotente el reintento. La presentación queda registrada para futura revisión por Coordinación. |

El esquema cuenta con 38 tablas tras las ampliaciones de esta fase. Las pruebas HTTP ejercitan cada recorrido integrado con PostgreSQL 18 y datos sintéticos en una instancia aislada. La validación posterior en PostgreSQL 17 está en [Validación y borrador de PR](VALIDACION_PR.md).

## Adaptación de autenticación

1. Definir cómo se relaciona `public.users` con `core.persona` y los perfiles académicos. La relación actual de `seguridad.usuario` con persona sirve como referencia para resolver el vínculo del portal.
2. Sustituir las lecturas de `roles.valor`, `cod_usuario` y sesiones de acceso propias por el usuario autenticado y el contexto académico definido para el módulo.
3. Conservar las restricciones de combinaciones de funciones y la comprobación de habilitación y período que aplica SSE-EPI.
4. Adaptar los destinos de login, registro, recuperación y logout a los del portal.
5. Retirar las rutas de autenticación duplicadas de la versión integrada cuando las pruebas de entrada y autorización estén verificadas.
6. Revisar qué restricciones de cuenta aplica el portal y qué restricciones de habilitación académica debe mantener el módulo.

## Contratos con otros módulos

| Fuente | Información a acordar | Uso de Seguimiento |
| --- | --- | --- |
| Matrículas | Correspondencia de código de estudiante, curso y sección con matrícula y detalle. Tipo de evaluación, asistencia completa, fecha de corte y estado oficial. Autoridad para aplicar cada fuente. | Secretaría, evaluación académica, alertas y continuidad |
| Horarios | Carga, sección, componente docente, disponibilidad y vigencia. Confirmar cuando hay más de un docente por sección. | Consejería y programación |
| Bienestar | Identificador de caso, recepción y respuesta autorizada | Derivación y contrarreferencia |
| Calidad | Alcance, corte, revisión y formato de publicación | Entrega de resultados respaldados |
| Coordinación, Secretaría y Dirección | Fuente de designaciones vigentes por escuela y período y autoridad para revisar/aplicar sus flujos. | Asignaciones de tutores, aplicación de recepciones, aprobación y revisión |

Cada contrato deberá identificar campos, responsable, periodicidad, fuente y comportamiento cuando la consulta falle. Se preservará la distinción entre cero confirmado, dato ausente y fuente no disponible.

### Referencias para aplicar una recepción de Secretaría

| Origen CSV | Referencia disponible en el portal | Acuerdo pendiente para aplicar |
| --- | --- | --- |
| Matrículas | `core.estudiante.codigo_universitario`, curso del plan y `core.seccion` del período. `gestion_matriculas.matricula` agrupa por estudiante y período. `matricula_detalle` identifica la sección. | Correspondencia de identificadores externos, cronograma y estado institucional. Determinar quién registra y rectifica. |
| Notas | `gestion_matriculas.nota` identifica cada valor por detalle de matrícula y componente de evaluación de la sección. | Equivalencias aprobadas entre unidad recibida y componente. Confirmar el tratamiento de finales y resúmenes. |
| Asistencia | `gestion_matriculas.asistencia` identifica un registro por detalle de matrícula y fecha. El CSV de SSE-EPI admite totales e inasistencias por corte. | Acordar si el corte agregado se conserva como evidencia propia de Seguimiento o si la fuente entregará fechas y estados por sesión para Matrículas. |

La prevalidación de formato sigue disponible sin guardar filas. Para validar y guardar lotes, la administración debe otorgar `seguimiento-estudiante.secretaria.lotes` y registrar `secretaria_ambito` por persona, escuela y período. El editor comprueba la tupla estudiante, curso del plan y sección. Para notas y asistencia también comprueba el detalle oficial de matrícula. Se admite `DETALLE:<id>` como referencia explícita del portal. Los códigos externos se conservan como procedencia y requieren la tupla para resolver su destino si no hay equivalencia registrada. La nota requiere el nombre exacto de un componente de la sección. La asistencia agregada conserva total y faltas.

El lote se identifica por escuela, período, tipo y referencia de origen. Cada revisión guarda fecha de corte, usuario, huella SHA-256, filas válidas normalizadas y errores genéricos por número de fila. El CSV original y los valores de filas rechazadas quedan fuera del almacenamiento. Repetir el mismo archivo y corte reutiliza la revisión vigente si su validación coincide. Si cambian los datos o la correspondencia institucional, se crea una versión. Solo se confirma para entrega la última versión sin errores que conserve sus referencias. El sistema guarda la cuenta y fecha de confirmación. La confirmación es interna y no escribe en Gestión de Matrículas. Sigue pendiente quién recibe y aplica el lote institucional.

La revisión de aportes de Tutoría requiere `seguimiento-estudiante.plan-tutoria.revisar` y `coordinacion_ambito` en un período activo. Coordinación consulta la última versión presentada. La revisión guarda comentario, decisión y responsable en esa versión. Las ediciones posteriores del borrador se mantienen privadas al tutor y una revisión con una versión desactualizada se rechaza.

El plan general F4 registra un PDF previamente aprobado y su referencia institucional. Publicar y retirar exige `seguimiento-estudiante.plan-general.publicar` y una designación vigente en `coordinacion_ambito`. Quien tenga `seguimiento-estudiante.tutoria.view` y una asignación tutorial vigente puede consultar y descargar publicaciones de su escuela y período. Se conservan las versiones, la vigencia y el retiro en `seguimiento_estudiante.operacion`. El PDF privado se guarda en `storage/app/private/seguimiento-estudiante/plan-general/`, con respaldo en el volumen privado del portal. La acción registra la aprobación externa que declara Coordinación.


## Aceptación

- Una cuenta accede únicamente a los registros de su identidad o alcance autorizado.
- Cambiar escuela, período o identificador de expediente en una URL conserva la autorización por recurso.
- Una cuenta sin relación académica recibe un estado explicativo con acceso restringido según la acción.
- Las combinaciones de roles mantienen las restricciones funcionales acordadas.
- Los estados vacíos del módulo explican qué información falta.
- La estructura y sus restricciones se verifican sobre PostgreSQL en el entorno de pruebas separado.

## Referencias

- [Plan de integración](README.md).
- [Base de datos del portal](../../base_de_datos.md).
- [Guía de desarrollo modular](../../../GUIA_MODULOS.md).
- [Esquema de origen revisado](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/database/schema.sql).
- [Contexto de acceso del origen](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/app/Services/AccessContextService.php).

## Mapa de tablas y atributos de SSE-EPI al monolito

Se reconstruyeron ambos esquemas en bases independientes de un PostgreSQL 18 temporal. El origen corresponde a `main` de SSE-EPI en `0fe2fbe` y al orden de instalación de su [script Docker](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/docker/postgres/init.sh). La inspección omitió las semillas y las instrucciones de alta de cuentas y valores de ejemplo. El destino corresponde a las migraciones del portal en `eebebd8` más las seis migraciones de creación de la etapa 3 y las dos ampliaciones posteriores verificadas en la instancia temporal. No se usaron las bases de trabajo ni se copiaron registros.

El origen reconstruido contiene **82 tablas y 3 vistas con 521 columnas**. La matriz incluye cada columna una vez. PostgreSQL acorta el nombre declarado `usuario_datos_estudiante_perfiles_ingreso_puntajes_conocimientos_por_area` a `usuario_datos_estudiante_perfiles_ingreso_puntajes_conocimiento`. Se usa el nombre del catálogo. En las tablas de abajo, `se` significa `seguimiento_estudiante`.

| Estado | Columnas del origen | Qué significa |
| --- | ---: | --- |
| Equivalente físico | 6 | El portal dispone de una columna equivalente. El flujo aún debe usarla. |
| Requiere adaptación | 116 | Las columnas de destino existen. Hay que resolver claves, cardinalidad, tipos o semántica. |
| JSONB por definir | 163 | Existe un contenedor JSONB. Falta definir el contrato de claves, tipos y validación del contenido. |
| Requiere contrato | 109 | El responsable institucional o el otro módulo debe confirmar el contrato o la división de datos. |
| Sin destino confirmado | 85 | Falta acordar una representación en el portal o la proyección de salida. |
| Función del portal | 42 | El portal asume la función técnica. Las filas antiguas quedan fuera de la entrega. |


<details>
<summary>Cuentas, permisos y administración</summary>


**`public.usuario`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_usuario`, `codigo_institucional` | Requiere adaptación | `public.users.id`, `core.persona.id_persona`, `core.estudiante.codigo_universitario` y `core.docente.codigo_docente` | Son identificadores distintos. Resolver según el papel y una identidad institucional verificada. |
| `nombre_nombres`, `nombre_apellido_paterno`, `nombre_apellido_materno` | Requiere adaptación | `core.persona.nombres`, `core.persona.apellido_paterno`, `core.persona.apellido_materno` y `public.users.name` | El nombre visible de la cuenta se compone a partir de los datos confirmados. |
| `correo_institucional` | Requiere adaptación | `public.users.email` y, si corresponde al vínculo aprobado, `seguridad.usuario.correo_institucional` | Deduplicar por identidad verificada. El correo personal de core tiene otro significado. |
| `password_hash`, `verification_token` | Función del portal | Autenticación, verificación y recuperación del portal | Cada persona usa la cuenta y el flujo del portal. Las credenciales de SSE-EPI no se trasladan. |
| `email_verified_at` | Equivalente físico | `public.users.email_verified_at` | Hay un campo equivalente. La verificación depende del flujo del portal. |
| `estado_cuenta`, `failed_attempts`, `lockout_cycles`, `lockout_until` | Sin destino confirmado | Política institucional de cuenta y bloqueo | Comprobar qué estados y reglas admite el portal antes de habilitar el acceso. |
| `datos_estudiante_cohorte` | Requiere adaptación | `core.estudiante.id_periodo_ingreso` | Derivar la cohorte cuando su significado coincida con el período de ingreso. |
| `administracion_revision` | Sin destino confirmado | Control de edición de cuentas | Definir quién administra la cuenta y cómo detecta modificaciones concurrentes. |

**`public.usuario_roles`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_roles`, `cod_usuario`, `valor` | Requiere adaptación | `public.roles.name`, `public.model_has_roles.role_id` y `public.model_has_roles.model_id` | Resolver el nombre del rol y la cuenta. El ID de la fila original no identifica una asignación en Spatie. |

**`public.usuario_preferencia`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_usuario`, `tema`, `actualizado_en` | Sin destino confirmado | Preferencia de tema por cuenta | Acordar si pertenece al portal o al módulo, y preservar el valor y la fecha de actualización. |

**`public.usuario_recuperacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_usuario`, `token_hash`, `expira_en`, `creado_en` | Función del portal | Flujo de restablecimiento del portal | La cuenta integrada emite sus propios tokens. |

**`public.auditoria_incidentes_seguridad`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_incidente`, `cod_usuario`, `correo_afectado`, `tipo_evento`, `ip_address`, `user_agent`, `dispositivo_info`, `fecha_hora`, `detalles_json` | Requiere contrato | `seguridad.registro_acceso` y sistema de auditoría del portal | Acordar qué eventos conservan correo intentado, IP, agente y detalle. Un intento de acceso y un incidente pueden representar hechos distintos. |

**`public.administracion_auditoria`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `actor`, `accion`, `entidad`, `antes`, `despues`, `motivo`, `fecha` | Requiere contrato | `seguridad.auditoria` o auditoría operativa del módulo | Acordar el actor y normalizar acción, entidad, antes/después, motivo y fecha. |

**`public.superadmin_audit_log`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `cod_usuario`, `accion`, `entidad`, `entidad_id`, `descripcion`, `payload_json`, `ip_address`, `realizado_en` | Requiere contrato | `seguridad.auditoria` y administración institucional | Acordar la titularidad de las operaciones técnicas y el tratamiento de cada evento. |

**`public.sistema_configuracion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `clave`, `valor`, `descripcion`, `tipo`, `modificado_por`, `modificado_en` | Sin destino confirmado | Configuración del módulo y, cuando corresponda, `comun.parametro_sistema` | Separar parámetros propios y del portal. Registrar tipo, valor, responsable y fecha antes de publicar. |

**`public.sistema_conexiones_externas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_conexion`, `nombre`, `tipo`, `descripcion`, `dsn_encriptado`, `estado`, `creado_por`, `creado_en`, `ultima_prueba`, `ultimo_estado_json` | Requiere contrato | Configuración institucional de conectores | Acordar responsable, estado, diagnóstico y gestión de secretos. El DSN cifrado depende de la configuración de origen. |
</details>


<details>
<summary>Currículo, carga y matrícula</summary>


**`public.curso`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_curso` | Equivalente físico | `core.curso.codigo` | Validar longitud y catálogo oficial. |
| `nombre` | Equivalente físico | `core.curso.nombre` | Usar la denominación institucional. |
| `naturaleza` | Requiere adaptación | `core.curso_plan.tipo` | La naturaleza depende del plan. Verificar la clasificación oficial. |

**`public.curso_pertenencias_curriculares`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_pertenencias_curriculares`, `cod_curso`, `referencia_plan`, `version_plan` | Requiere adaptación | `core.curso_plan.id_curso_plan`, `core.curso_plan.id_curso` y `core.curso_plan.id_plan` | Resolver código y versión con la escuela. |
| `ciclo_curricular`, `caracter` | Requiere adaptación | `core.curso_plan.ciclo` y `core.curso_plan.tipo` | Normalizar el ciclo y el carácter. |
| `creditos` | Requiere contrato | `core.curso.creditos` o contrato curricular | Confirmar si el valor debe poder variar por plan. creditos_requeridos tiene otro significado. |

**`public.curso_procedencias`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_procedencias`, `cod_curso`, `fuente`, `fecha_recepcion`, `informacion_referida` | Requiere contrato | Contrato curricular y evidencia documental | Conservar fuente, fecha y dato referenciado. El curso institucional no incluye esa procedencia. |

**`public.referencia_documental`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `titulo`, `sha256` | Requiere adaptación | `comun.archivo.id_archivo`, `comun.archivo.nombre_original` y `comun.archivo.hash_sha256` | Resolver el objeto archivado y distinguir el título del nombre del archivo. |
| `fecha_documento`, `estado_validacion`, `texto_extraido`, `recibido_en` | Sin destino confirmado | Respaldo curricular | Definir el registro documental. Fecha de emisión, recepción y carga son eventos distintos. |

**`public.curso_detalle_documental`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_curso`, `referencia_plan`, `version_plan`, `tipo_formacion` | Requiere adaptación | `core.curso_plan.id_curso`, `core.curso_plan.id_plan` y `core.curso_plan.id_area` | Validar equivalencia de tipo de formación y área. |
| `horas_teoria`, `horas_practica`, `horas_laboratorio`, `horas_total`, `requisitos_texto`, `departamento` | Requiere contrato | Contrato curricular con `core.curso` y `core.curso_prerequisito` | Preservar los valores documentados por versión. El total se calcula de los componentes cuando son comparables. |
| `documento_id`, `pagina_pdf`, `transcripcion_fuente` | Sin destino confirmado | Referencia de evidencia curricular | Hace falta representar la relación entre transcripción y página de origen. |

**`public.dicta`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_dictado`, `cod_docente`, `cod_curso`, `periodo_anio`, `periodo_tipo`, `seccion`, `componente_docencia` | Requiere contrato | Contrato con Horarios y `core.seccion` | Resolver docente, curso, período y sección. Responsable de sección y titular de cada componente son conceptos distintos. |
| `fuente_carga`, `fecha_recepcion_carga`, `responsable_consejeria`, `fuente_responsabilidad`, `fecha_recepcion_responsabilidad` | Requiere contrato | Contrato de carga y habilitación de consejería | Preservar procedencia y vigencia antes de usar la carga para conceder acceso. |

**`public.matricula`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `identificador_matricula`, `periodo_anio`, `periodo_tipo`, `referencia_plan`, `seccion`, `condicion`, `cod_estudiante`, `cod_curso`, `version_plan` | Requiere contrato | Contrato con Gestión de Matrículas, `core.seccion` y `core.curso_plan` | El origen es por estudiante y curso. En destino se separa cabecera por período y detalle por sección. |
| `numero_vez` | Sin destino confirmado | Historial académico o cálculo con regla aprobada | La cantidad de intentos no se almacena en la vista publicada. |

**`public.matricula_asistencias`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_asistencias`, `identificador_matricula`, `corte_o_fecha`, `valor_estado` | Requiere contrato | `gestion_matriculas.asistencia` | Distinguir evento por fecha y corte agregado antes de transformar. |
| `fuente`, `fecha_recepcion` | Sin destino confirmado | Evidencia de recepción | No equivale a fecha de clase. Conservar junto al lote o corte. |

**`public.matricula_notas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_notas`, `identificador_matricula`, `evaluacion_o_unidad`, `valor` | Requiere contrato | `gestion_matriculas.nota` y `gestion_matriculas.componente_evaluacion` | Resolver el detalle y el componente. Conservar diferencia entre parcial y resultado final. |
| `fuente`, `fecha_recepcion` | Sin destino confirmado | Evidencia de recepción | La fecha en que el portal registra una nota tiene otro significado. |

**`public.matricula_dato_recibido`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `identificador_matricula`, `fuente`, `fecha_recepcion`, `documento_sha256`, `datos` | JSONB por definir | `se.operacion.datos`, lote y evidencia de Secretaría | Propuesta de contexto por escuela. Preservar el identificador recibido, la huella y el payload de origen. |

**`public.matricula_historial_situacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_historial_situacion`, `identificador_matricula`, `estado`, `fecha_recepcion`, `fuente` | JSONB por definir | `se.expediente.datos`, evidencia de situación recibida | Propuesta. Distinguir el historial de declaraciones recibidas de la condición calculada. |
</details>


<details>
<summary>Tutoría, atención y bienestar</summary>


**`public.asigna`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_asignacion`, `cod_tutor`, `cod_estudiante`, `periodo_anio`, `periodo_tipo`, `fecha_inicio` | Requiere adaptación | `se.tutor_asignacion.id_asignacion`, `se.tutor_asignacion.id_docente`, `se.tutor_asignacion.id_estudiante`, `se.tutor_asignacion.id_periodo` y `se.tutor_asignacion.fecha_inicio` | Resolver identidades y período. Conservar el inicio de vigencia. |
| `vigente` | Requiere adaptación | `se.tutor_asignacion.estado` | Resolver derivación con fechas y preservar la historia. |
| `cod_coordinador`, `fecha_fin`, `motivo`, `causa_fin`, `observacion` | Requiere adaptación | `se.tutor_asignacion.id_usuario_asignador`, `se.tutor_asignacion.fecha_fin`, `se.tutor_asignacion.motivo`, `se.tutor_asignacion.causa_fin` y `se.tutor_asignacion.observacion` | Resolver la cuenta del asignador y su permiso. Acordar reasignaciones al mismo docente en el mismo período. |

**`public.habilita`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_habilitacion`, `cod_coordinador`, `cod_docente`, `periodo_anio`, `periodo_tipo`, `fecha`, `estado` | Sin destino confirmado | Habilitación tutorial por docente, escuela y período | Hace falta una decisión de estructura y vigencia. Una asignación a un alumno tiene otra función. |

**`public.atencion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_atencion`, `tipo_atencion` | Requiere adaptación | `se.atencion.id_atencion` y `se.atencion.id_tipo_atencion` | Resolver ID de destino y catálogo. |
| `motivo`, `solicitud_fecha_hora`, `solicitud_motivo` | Requiere adaptación | `se.solicitud_estudiante.asunto`, `se.solicitud_estudiante.solicitada_en`, `se.solicitud_estudiante.descripcion` y `se.atencion_motivo.id_motivo` | Separar solicitud y atención. Conservar texto libre y registrar el catálogo cuando aplique. |
| `origen_tipo_origen`, `origen_referencia_origen` | Requiere adaptación | `se.atencion.id_alerta` y `se.atencion.id_solicitud` | Resolver los orígenes soportados. Otros requieren conservar el tipo y la referencia en el expediente. |
| `periodo_anio`, `periodo_tipo` | Requiere adaptación | `se.tutor_asignacion.id_periodo` | Resolver período y asignación. Una solicitud previa a la asignación puede necesitar contexto de período propio. |
| `sesion_fecha_hora` | Requiere adaptación | `se.atencion.fecha_hora` | Fecha de la atención con zona horaria. |
| `cancelacion_fecha`, `cancelacion_motivo` | Requiere adaptación | `se.atencion_evento.registrado_en` y `se.atencion_evento.motivo` | Usar evento de cancelación en la solicitud o atención que corresponda. |
| `sesion_actividad`, `sesion_aspectos_tratados`, `sesion_observaciones`, `sesion_resultado` | Requiere adaptación | `se.atencion.actividad`, `se.atencion.aspectos_tratados`, `se.atencion.observaciones` y `se.atencion.resultado` | Aplicar los campos de ficha desde el servicio de atención. |
| `estado`, `cod_curso` | Sin destino confirmado | Estado de atención y vínculo académico | Definir las transiciones y la relación con curso o sección. |

**`public.atencion_programaciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_programaciones`, `id_atencion` | Requiere adaptación | `se.atencion_evento.id_evento` y `se.atencion_evento.id_atencion` | Resolver la atención. Para el tramo previo usar la FK de solicitud. |
| `fecha_hora`, `modalidad_medio`, `motivo_cambio` | Requiere adaptación | `se.atencion_evento.fecha_programada`, `se.atencion_evento.modalidad` y `se.atencion_evento.motivo` | Distinguir registro del evento de la fecha programada. |

**`public.participa_en`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_participacion`, `cod_usuario`, `id_atencion`, `papel_en_atencion`, `asistencia` | Requiere adaptación | `se.atencion_participante.id_persona`, `se.atencion_participante.id_atencion`, `se.atencion_participante.papel` y `se.atencion_participante.asistio` | Resolver la persona y usar la clave compuesta. Verificar duplicados y significado de asistencia. |

**`public.atencion_sesion_acuerdos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_acuerdos`, `id_atencion`, `valor` | Requiere adaptación | `se.atencion.acuerdos` y `se.atencion_compromiso.descripcion` | Distinguir acuerdos narrativos de compromisos con control de cumplimiento. Conservar multiplicidad. |

**`public.atencion_derivaciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_derivaciones`, `id_atencion`, `fecha_emision`, `destino`, `motivo_general` | Requiere adaptación | `se.atencion_derivacion.id_derivacion`, `se.atencion_derivacion.id_atencion`, `se.atencion_derivacion.derivado_en`, `se.atencion_derivacion.area_destino` y `se.atencion_derivacion.motivo` | Fecha frente a instante. Conservar la precisión original. |
| `referencia_derivacion`, `referencia_envio`, `estado`, `contrarreferencia_fecha_documento`, `contrarreferencia_fecha_recepcion` | Requiere contrato | Contrato con Bienestar y seguimiento del expediente | El ID de caso es una referencia lógica. El contrato debe distinguir estado de envío y respuesta. |

**`public.atencion_derivaciones_contrarreferencia_recomendaciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_recomendaciones`, `id_derivaciones`, `valor` | Requiere contrato | Contrato con Bienestar y seguimiento del expediente | Conservar las recomendaciones por respuesta y derivación. |
</details>


<details>
<summary>Consejería, alertas y expediente</summary>


**`public.consejeria_ambito`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `cod_docente`, `periodo_anio`, `periodo_tipo` | Requiere adaptación | `se.consejeria_ambito.id_ambito`, `se.consejeria_ambito.id_escuela`, `se.consejeria_ambito.id_docente` y `se.consejeria_ambito.id_periodo` | Resolver contexto institucional. La clave hash anterior se sustituye por el nuevo ámbito. |

**`public.consejeria_registro`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `ambito_id`, `cod_curso`, `seccion`, `cod_estudiante` | Requiere adaptación | `se.consejeria_registro.clave`, `se.consejeria_registro.id_ambito`, `se.consejeria_registro.id_seccion` y `se.consejeria_registro.id_estudiante` | Resolver código de curso y sección con el período. Conservar evidencia del curso si no hay sección verificada. |
| `tipo` | Equivalente físico | `se.consejeria_registro.tipo` | Usar los tipos admitidos por la restricción. |
| `datos` | JSONB por definir | `se.consejeria_registro.datos` | Existe el contenedor. Falta validar el contrato por tipo de registro. |
| `actualizado_en` | Requiere adaptación | `se.consejeria_registro.actualizado_en` | Interpretar la fecha sin zona del origen antes de registrar un instante. |

**`public.consejeria_criterio`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `periodo_anio`, `periodo_tipo`, `datos`, `cod_responsable`, `actualizado_en` | JSONB por definir | `se.operacion.datos`, criterios vigentes por período | Propuesta. Los criterios de consejería estructurados no se reducen a un único umbral SAT. |

**`public.consejeria_criterio_historial`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `periodo_anio`, `periodo_tipo`, `datos`, `cod_responsable`, `fecha` | JSONB por definir | `se.operacion.datos`, versiones de criterio | Propuesta. Preservar versión, actor y fecha junto al ámbito. |

**`public.consejeria_asistencia_corte`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `identificador_matricula`, `total_clases`, `inasistencias`, `completo`, `fuente`, `fecha_recepcion` | Requiere contrato | Contrato con Matrículas y evidencia de corte | Una asistencia diaria no representa el total de clases, inasistencias y completitud del corte. |

**`public.consejeria_antecedente_curricular`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `cod_curso`, `cod_antecedente`, `referencia_plan`, `version_plan`, `fuente`, `validado` | Requiere contrato | `core.curso_prerequisito` y referencia documental | Usar la relación oficial del plan cuando esté verificada. Conservar validación y fuente como evidencia. |

**`public.consejeria_importacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `cod_responsable`, `fuente`, `registros`, `fecha`, `huella` | JSONB por definir | `se.operacion.datos`, lote de recepción | Propuesta. Registrar responsable, fuente, conteo, fecha y huella. |

**`public.estudiante_acompanamiento`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_estudiante` | Requiere adaptación | `se.expediente.id_estudiante` | Resolver el estudiante académico. |
| `datos` | JSONB por definir | `se.expediente.datos` y `se.expediente.revision` | Separar el contador del contenido y validar las colecciones que consume cada servicio. |
| `actualizado_en` | Requiere adaptación | `se.expediente.actualizado_en` | Interpretar la fecha de origen según su zona constatada. |

**`public.usuario_alertas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_alertas`, `cod_usuario`, `periodo_anio`, `periodo_tipo`, `fecha_generacion` | Requiere adaptación | `se.alerta_sat.id_alerta`, `se.alerta_sat.id_estudiante`, `se.alerta_sat.id_periodo` y `se.alerta_sat.generada_en` | Resolver identidad y período. Interpretar fecha de origen. |
| `referencia_alerta`, `criterio`, `ambito`, `nivel` | Sin destino confirmado | `se.parametro_sat` y evidencia de evaluación | La regla referenciada debe existir y su versión debe corresponder a la evaluación. El nivel histórico y el ámbito requieren destino explícito. |

**`public.usuario_alertas_evidencias`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_evidencias`, `id_alertas`, `fuente`, `fecha_recepcion`, `descripcion` | JSONB por definir | `se.expediente.datos`, evidencia de alerta | Propuesta. Guardar fuente, recepción y descripción aun cuando no exista un archivo. La evidencia de atención tiene otra relación. |

**`public.usuario_alertas_movimientos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_movimientos`, `id_alertas`, `fecha`, `estado`, `responsable`, `motivo_observacion` | JSONB por definir | `se.expediente.datos`, historial de alerta | Propuesta. Mantener estados, fecha, actor y motivo. El estado vigente se representa en la alerta. |

**`public.usuario_seguimientos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_seguimientos`, `cod_usuario`, `referencia_seguimiento`, `objetivo`, `motivo_origen`, `fecha_apertura`, `responsable`, `estado`, `proxima_revision`, `cierre_fecha`, `cierre_responsable`, `cierre_motivo`, `cierre_resultado`, `cierre_observacion_final` | JSONB por definir | `se.expediente.datos`, colección followups | Propuesta. Conservar objetivo, motivo, revisiones futuras, responsables y cierre sin reducir el expediente a una atención. |

**`public.usuario_seguimientos_compromisos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_compromisos`, `id_seguimientos`, `valor` | JSONB por definir | `se.expediente.datos`, compromisos del seguimiento | Propuesta. Mantener el vínculo al seguimiento y distinguirlo de compromisos de una atención. |

**`public.usuario_seguimientos_revisiones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_revisiones`, `id_seguimientos`, `fecha`, `responsable`, `avance`, `observaciones`, `proxima_revision` | JSONB por definir | `se.expediente.datos`, revisiones de cada seguimiento | Propuesta. Mantener fecha, responsable, avance, observaciones y siguiente revisión. |

**`public.usuario_seguimientos_revisiones_nuevos_compromisos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_nuevos_compromisos`, `id_revisiones`, `valor` | JSONB por definir | `se.expediente.datos`, compromisos por revisión | Propuesta. Mantener el vínculo a la revisión que creó cada compromiso. |
</details>


<details>
<summary>Ingreso y situación académica</summary>


**`public.usuario_datos_estudiante_perfiles_ingreso`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_perfiles_ingreso`, `cod_usuario` | Requiere adaptación | `se.perfil_ingreso.id_perfil` y `se.perfil_ingreso.id_estudiante` | Resolver identidad y cardinalidad. La fila del portal es única por estudiante. |
| `periodo_anio`, `periodo_tipo` | Sin destino confirmado | Historial de evaluaciones de ingreso por período | Una referencia al período oficial de ingreso no cubre todas las evaluaciones posibles. |
| `modalidad_ingreso` | Requiere adaptación | `core.estudiante.id_modalidad_ingreso` | Usar el catálogo cuando se trate de la modalidad institucional confirmada. |
| `puntajes_total_recibido` | Requiere adaptación | `se.perfil_ingreso.puntaje_ingreso` | Confirmar escala. El origen tiene precisión NUMERIC(10,2) y el portal NUMERIC(6,2). |
| `puntajes_aptitud_academica`, `puntajes_procedimental`, `puntajes_actitudinal`, `estado_completitud` | Sin destino confirmado | Evidencia de evaluación de ingreso por componente | Mantener puntajes y completitud con precisión y versión. No rellenar un total si faltan datos. |

**`public.usuario_datos_estudiante_perfiles_ingreso_brechas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_brechas`, `id_perfiles_ingreso`, `valor` | Requiere adaptación | `se.brecha_ingreso.id_perfil`, `se.brecha_ingreso.id_dimension` y `se.brecha_ingreso.nivel` | Resolver el catálogo. Si valor contiene texto libre sin dimensión verificada, necesita preservarse como evidencia. |

**`public.usuario_datos_estudiante_perfiles_ingreso_procedencias`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_procedencias`, `id_perfiles_ingreso`, `componente`, `fuente`, `fecha_recepcion` | Sin destino confirmado | Evidencia de procedencia por evaluación y componente | Conservar componente, fuente y fecha de recepción. La fecha de evaluación tiene otro significado. |

**`public.usuario_datos_estudiante_perfiles_ingreso_puntajes_conocimiento`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_conocimientos_por_area`, `id_perfiles_ingreso`, `area`, `puntaje` | Requiere adaptación | `se.brecha_ingreso.id_perfil`, `se.brecha_ingreso.id_dimension` y `se.brecha_ingreso.puntaje` | Aplicar solo si el área representa una dimensión de brecha y la escala cabe. En otro caso, mantener el puntaje en la evaluación de origen. |

**`public.usuario_datos_estudiante_situaciones_academicas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_situaciones_academicas`, `cod_usuario`, `periodo_anio`, `periodo_tipo` | Requiere adaptación | `se.situacion_academica.id_estudiante` y `se.situacion_academica.id_periodo` | Identidad lógica del resumen. Varias recepciones del período deberán conservarse aparte. |
| `estado_recibido`, `fuente`, `fecha_recepcion` | Sin destino confirmado | Evidencia de estado recibido por período | Conservar estado declarado, fuente y corte. El resumen académico guarda información calculada. |
</details>


<details>
<summary>Nivelación</summary>


**`public.nivelacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_nivelacion` | Requiere adaptación | `se.programa_nivelacion.id_programa` | Resolver ID y los datos obligatorios de nombre, período y dimensión. |
| `cobertura_fecha_inicio` | Equivalente físico | `se.programa_nivelacion.fecha_inicio` | Inicio de cobertura. |
| `cobertura_fecha_fin` | Equivalente físico | `se.programa_nivelacion.fecha_fin` | Fin de cobertura. |
| `estado` | Requiere adaptación | `se.programa_nivelacion.estado` | Normalizar la máquina de estados antes de escribir. |
| `justificacion` | Requiere adaptación | `se.programa_nivelacion.descripcion` | Sirve si se conserva la semántica del texto. Si descripción y justificación son independientes, mantener ambas. |
| `informe_final_metodologia`, `informe_final_analisis`, `cod_registrador` | Sin destino confirmado | Informe y autoría del programa | Conservar por versión y con identidad del registrador. |

**`public.incluye`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_nivelacion`, `cod_curso` | Sin destino confirmado | Relación de programa con curso o curso del plan | El grupo de nivelación identifica docente y programa. El modelo actual carece de FK al curso. |

**`public.participa_nivelacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_usuario`, `id_nivelacion` | Requiere adaptación | `se.nivelacion_participante.id_estudiante` y `se.nivelacion_participante.id_grupo` | Resolver un grupo real dentro del programa. |
| `resultado_individual` | Sin destino confirmado | Resultado publicado del participante | El resultado individual se distingue de la nota por criterio y del estado de inscripción. |

**`public.participa_nivelacion_brechas_atendidas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_brechas_atendidas`, `cod_usuario`, `id_nivelacion`, `valor` | Sin destino confirmado | Brechas atendidas por participante | Resolver dimensión y evidencia del resultado, además del programa. |

**`public.nivelacion_actividades`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_actividades`, `id_nivelacion`, `fecha`, `temario` | Requiere adaptación | `se.nivelacion_sesion.id_sesion`, `se.nivelacion_sesion.id_grupo`, `se.nivelacion_sesion.fecha` y `se.nivelacion_sesion.tema` | Resolver grupo y preservar el contexto de sesión. |
| `numero_sesion`, `responsable` | Sin destino confirmado | Orden y responsable por sesión | La responsabilidad del grupo no equivale siempre a quien ejecutó la sesión. |

**`public.nivelacion_formas_criterios_evaluacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_formas_criterios_evaluacion`, `id_nivelacion`, `valor` | Requiere adaptación | `se.nivelacion_criterio_evaluacion.id_criterio`, `se.nivelacion_criterio_evaluacion.id_programa` y `se.nivelacion_criterio_evaluacion.nombre` | Solo si representa un criterio ponderado conocido. El peso es obligatorio y carece de dato equivalente en esta tabla de origen. |

**`public.nivelacion_aprobaciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_aprobaciones`, `id_nivelacion`, `documento_aprobado`, `referencia`, `fecha`, `autoridad`, `observacion` | JSONB por definir | `se.operacion.datos`, aprobaciones por programa y versión; `comun.archivo` cuando exista respaldo | Propuesta. Mantener documento, referencia, fecha, autoridad y observación. |

**`public.nivelacion_objetivos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_objetivos`, `id_nivelacion`, `valor` | JSONB por definir | `se.operacion.datos`, objetivos por programa y versión | Propuesta. Mantener vínculo y orden. |

**`public.nivelacion_metas`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_metas`, `id_nivelacion`, `descripcion`, `resultado_esperado` | JSONB por definir | `se.operacion.datos`, metas por programa y versión | Propuesta. Mantener descripción y resultado esperado por separado. |

**`public.nivelacion_estrategias`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_estrategias`, `id_nivelacion`, `valor` | JSONB por definir | `se.operacion.datos`, estrategias por programa y versión | Propuesta. |

**`public.nivelacion_informe_final_resultados`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_resultados`, `id_nivelacion`, `categoria`, `descripcion`, `valor` | JSONB por definir | `se.operacion.datos`, resultados del informe de nivelación | Propuesta. Mantener categoría, descripción y valor. |

**`public.nivelacion_informe_final_conclusiones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_conclusiones`, `id_nivelacion`, `valor` | JSONB por definir | `se.operacion.datos`, conclusiones del informe de nivelación | Propuesta. |

**`public.nivelacion_informe_final_recomendaciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_recomendaciones`, `id_nivelacion`, `valor` | JSONB por definir | `se.operacion.datos`, recomendaciones del informe de nivelación | Propuesta. |

**`public.nivelacion_referencias`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_referencias`, `id_nivelacion`, `valor` | JSONB por definir | `se.operacion.datos`, referencias por programa; `comun.archivo` cuando exista respaldo | Propuesta. Una referencia de texto puede existir por sí sola. |
</details>


<details>
<summary>Planes, informes y mediciones</summary>


**`public.usuario_planes_acompanamiento`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_planes_acompanamiento`, `cod_usuario`, `referencia_plan`, `tipo_plan`, `cobertura_fecha_inicio`, `cobertura_fecha_fin`, `fundamentacion`, `evaluacion_resultados`, `aprobacion_referencia`, `aprobacion_fecha`, `aprobacion_autoridad`, `aprobacion_observacion` | JSONB por definir | `se.operacion.datos`, plan de acompañamiento por ámbito | Propuesta. Mantener versión, cobertura, fundamento, evaluación y aprobación. Resolver autor y referencia. |

**`public.usuario_planes_acompanamiento_acciones_mejora`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_acciones_mejora`, `id_planes_acompanamiento`, `valor` | JSONB por definir | `se.operacion.datos`, acciones del plan | Propuesta. Mantener el vínculo al plan. |

**`public.usuario_planes_acompanamiento_cronograma`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_cronograma`, `id_planes_acompanamiento`, `actividad`, `fecha` | JSONB por definir | `se.operacion.datos`, cronograma del plan | Propuesta. Conservar actividad y fecha. |

**`public.usuario_planes_acompanamiento_necesidades`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_necesidades`, `id_planes_acompanamiento`, `valor` | JSONB por definir | `se.operacion.datos`, necesidades del plan | Propuesta. |

**`public.usuario_planes_acompanamiento_objetivos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_objetivos`, `id_planes_acompanamiento`, `valor` | JSONB por definir | `se.operacion.datos`, objetivos del plan | Propuesta. |

**`public.usuario_informes_actividades`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_informes_actividades`, `cod_usuario`, `referencia_informe`, `tipo`, `cobertura_fecha_inicio`, `cobertura_fecha_fin`, `fecha_emision`, `antecedentes`, `resultados`, `observaciones` | JSONB por definir | `se.operacion.datos`, informes de actividades | Propuesta. Resolver actor, tipo, referencia, cobertura, fecha de emisión, antecedentes, resultados y observaciones. |

**`public.usuario_informes_actividades_conclusiones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_conclusiones`, `id_informes_actividades`, `valor` | JSONB por definir | `se.operacion.datos`, conclusiones por informe | Propuesta. |

**`public.usuario_informes_actividades_objetivos`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_objetivos`, `id_informes_actividades`, `valor` | JSONB por definir | `se.operacion.datos`, objetivos por informe | Propuesta. |

**`public.usuario_informes_actividades_recomendaciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_recomendaciones`, `id_informes_actividades`, `valor` | JSONB por definir | `se.operacion.datos`, recomendaciones por informe | Propuesta. |

**`public.usuario_evaluaciones_proceso`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_evaluaciones_proceso`, `cod_usuario`, `fecha`, `cobertura_fecha_inicio`, `cobertura_fecha_fin` | JSONB por definir | `se.operacion.datos`, evaluaciones de proceso | Propuesta. Mantener actor, fecha y ventana de cobertura. |

**`public.usuario_evaluaciones_proceso_acciones_mejora`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_acciones_mejora`, `id_evaluaciones_proceso`, `valor` | JSONB por definir | `se.operacion.datos`, acciones por evaluación | Propuesta. Una eventual publicación a Calidad requiere contrato. |

**`public.usuario_evaluaciones_proceso_areas_mejora`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_areas_mejora`, `id_evaluaciones_proceso`, `valor` | JSONB por definir | `se.operacion.datos`, áreas por evaluación | Propuesta. |

**`public.usuario_evaluaciones_proceso_mediciones`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id_mediciones`, `id_evaluaciones_proceso`, `indicador`, `poblacion_ambito`, `valor`, `estado_calculo`, `causa_no_calculo`, `fuente`, `fecha_actualizacion` | JSONB por definir | `se.operacion.datos`, medición por indicador y alcance | Propuesta. Mantener causa de falta de cálculo, fuente y actualización. La publicación en calidad.medicion_indicador exige numerador y denominador y admite una fila por indicador y período. |

**`public.sse_operacion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `ambito` | Requiere adaptación | `se.operacion.clave` y `se.operacion.id_escuela` | Resolver escuela junto a la clave de operación. |
| `datos` | JSONB por definir | `se.operacion.datos` | El contenedor ya está definido. Validar el contenido real por tipo de agregado y la transacción del escritor. |
| `actualizado_en` | Requiere adaptación | `se.operacion.actualizado_en` | Interpretar zona y conservar el instante. |
</details>


<details>
<summary>Infraestructura y vistas documentales</summary>


**`public.cache`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `key`, `value`, `expiration` | Función del portal | `public.cache` del portal | Las filas de caché se regeneran en el portal. |

**`public.cache_locks`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `key`, `owner`, `expiration` | Función del portal | `public.cache_locks` del portal | El portal administra sus bloqueos y expiraciones. |

**`public.failed_jobs`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at` | Función del portal | `public.failed_jobs` del portal | El historial viejo queda en SSE-EPI. El portal registra sus propios fallos. |

**`public.job_batches`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`, `options`, `cancelled_at`, `created_at`, `finished_at` | Función del portal | `public.job_batches` del portal | El portal genera lotes nuevos con sus CLIs y trabajadores. |

**`public.jobs`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at` | Función del portal | `public.jobs` del portal | Los payloads serializados contienen clases, actores y rutas de SSE-EPI. Se adapta el trabajo para ejecuciones nuevas. |

**`public.sessions`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` | Función del portal | `public.sessions` del portal | El acceso inicia una sesión nueva. La FK lógica del usuario tiene un tipo distinto. |

**`public.vista_formato_f5_ficha_atencion`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `numero_ficha_f5`, `fecha_atencion`, `codigo_estudiante`, `nombre_estudiante`, `cohorte_ingreso`, `nombre_atendedor`, `rol_atendedor`, `tipo_atencion`, `motivo_consulta`, `aspectos_tratados`, `resultado_sesion`, `estado_atencion`, `periodo_academico` | Sin destino confirmado | Proyección de ficha F5 desde solicitud, atención, eventos, expediente e identidad | Verificar todos los campos de salida, incluidos rol del atendedor, aspectos tratados y resultado. |

**`public.vista_formato_f6_derivacion_bienestar`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `numero_derivacion_f6`, `codigo_referencia`, `fecha_derivacion`, `codigo_estudiante`, `estudiante_derivado`, `tutor_derivador`, `area_destino`, `motivo_derivacion`, `estado_atencion_bienestar`, `fecha_respuesta_bienestar` | Requiere contrato | Proyección de F6 desde `seguimiento_estudiante.atencion_derivacion` y contrato con Bienestar | Preservar referencia, destino, respuesta y fecha de respuesta. |

**`public.vista_formato_f7_informe_semestral`**

| Atributos de origen | Estado | Destino | Condición o decisión |
| --- | --- | --- | --- |
| `cod_tutor`, `nombre_tutor`, `periodo_academico`, `total_estudiantes_asignados`, `total_sesiones_realizadas`, `total_derivaciones_bienestar`, `total_alertas_sat_atendidas` | Sin destino confirmado | Proyección semestral desde asignaciones, sesiones, derivaciones y alertas | Reconciliar cada conteo y su alcance antes de traducir la consulta. |

</details>

### Lectura en sentido inverso

Estas columnas de las 29 tablas de Seguimiento revisadas en la etapa 3 no se señalaron como destino directo en la matriz. Incluyen relaciones, campos calculados, controles del portal y atributos que requieren revisión por flujo. La lista ayuda a identificar qué puede exigir el monolito además de lo que provee el origen.

| Tabla del portal | Columnas sin referencia directa en la matriz |
| --- | --- |
| `alerta_sat` | `id_parametro`, `valor_detectado`, `estado`, `cerrada_en` |
| `atencion` | `id_asignacion`, `modalidad`, `descripcion` |
| `atencion_compromiso` | `id_compromiso`, `id_atencion`, `fecha_limite`, `cumplido` |
| `atencion_derivacion` | `id_caso_bienestar` |
| `atencion_evento` | `id_solicitud`, `id_usuario`, `tipo` |
| `atencion_evidencia` | `id_atencion`, `id_archivo` |
| `atencion_motivo` | `id_atencion` |
| `dimension_brecha` | `id_dimension`, `codigo`, `nombre` |
| `motivo_atencion` | `id_motivo`, `nombre` |
| `nivelacion_asistencia` | `id_sesion`, `id_participante`, `estado` |
| `nivelacion_calificacion` | `id_participante`, `id_criterio`, `nota` |
| `nivelacion_criterio_evaluacion` | `peso` |
| `nivelacion_grupo` | `id_grupo`, `id_programa`, `codigo`, `id_docente`, `capacidad` |
| `nivelacion_material` | `id_programa`, `id_archivo`, `titulo` |
| `nivelacion_participante` | `id_participante`, `estado` |
| `nivelacion_sesion` | `id_ambiente` |
| `parametro_sat` | `id_parametro`, `codigo`, `descripcion`, `operador`, `umbral`, `nivel_riesgo`, `activo` |
| `perfil_ingreso` | `colegio_procedencia`, `tipo_colegio`, `id_distrito_colegio`, `fecha_evaluacion` |
| `programa_nivelacion` | `id_periodo`, `id_dimension`, `nombre` |
| `situacion_academica` | `id_situacion`, `promedio_ponderado`, `creditos_aprobados`, `creditos_desaprobados`, `condicion`, `calculado_en` |
| `solicitud_estudiante` | `id_solicitud`, `id_estudiante`, `estado` |
| `tipo_atencion` | `id_tipo_atencion`, `nombre` |
| `tutor_asignacion` | `tipo` |
Tres casos requieren especial atención. `parametro_sat` exige operador, umbral y código de regla que la alerta antigua no identifica por sí sola. `situacion_academica` agrega promedio y créditos calculados que deben obtenerse de una fuente válida. `programa_nivelacion` requiere nombre, período y dimensión, y sus grupos y sesiones agregan relaciones académicas que hay que resolver antes de registrar un programa.

### Contratos JSONB pendientes

El catálogo de PostgreSQL solo expone `datos` como columna. En el código de origen se comprobaron estos consumidores para orientar el contrato posterior.

| Consumidor | Ámbitos o partes de la estructura de origen que deben conservarse |
| --- | --- |
| Secretaría y recepción | `secretaria\|<período>`, `secretaria-lote\|<período>\|<id>` y `fuentes\|<período>`. Borrador, mapeo, revisión, huella, procedencia y recuperación por lote. |
| Expediente y ciclo de vida | `process.admission`, `process.admission_history`, `process.continuity`, `process.continuity_history`, `process.cases`, `process.closure` y `process.closure_history`. Solicitudes, seguimientos, derivaciones, avisos y auditoría del expediente. |
| Consejería | `case`, `alert`, `plan`, `report`, `audit`, `notification`, `meeting`, `document` y `event`. Cada tipo necesita su validación y alcance por escuela, período, sección y estudiante cuando corresponda. |
| Nivelación | `nivelacion-gestion` y `nivelacion-alcance`. Programas, diagnóstico, inscripción, versiones, resultados, respaldo y reglas de alcance. |
| Planes, informes e indicadores | `plan-tutoria\|<período>`, `informes-actividades\|<período>` y `mediciones-indicadores\|<año>`. Versiones, métodos, corte, revisión, fuente y estado de publicación. |

La implementación de cada flujo debe elegir una ubicación autorizada por dato, definir claves y tipos concretos, y probar lectura, escritura, reintento y control por registro. Toda adaptación de identificadores, períodos, escalas y zonas horarias debe conservar la semántica del origen. El mapa técnico queda como referencia de cobertura. Los contratos intermodulares y el diseño de los campos pendientes siguen abiertos.

Fuentes de código para verificar los payloads. [Recepción de Secretaría](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/app/Services/Secretaria/ReceiptWorkflow.php) · [Expediente de estudiante](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/app/Services/Estudiante/StudentWorkspace.php) · [Nivelación](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/app/Services/Nivelacion/LevelingService.php) · [Mediciones](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/app/Services/Indicators/MeasurementRegister.php)
