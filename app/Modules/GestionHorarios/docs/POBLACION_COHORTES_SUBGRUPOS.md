# Población, cohortes y subgrupos

## Nota del ensayo de planificación por aforo

La nueva vista local estima población y subgrupos de forma numérica, sin crear matrículas ni acreditar disyunción nominal. Véase [Planificación automática de secciones](PLANIFICACION_AUTOMATICA_SECCIONES.md).


## Fuentes observadas y límites

La demanda recibida en `gestion_horarios.demanda_instantanea` y `demanda_curso` ofrece versión, fecha de recepción y totales agregados por curso-plan y preferencias. Representa solicitudes estimadas, no estudiantes asignados. `core.seccion` identifica periodo, curso-plan, sección y capacidad máxima, no población. `oferta_seccion` identifica la oferta solo cuando su vínculo vigente supera conciliación.

El esquema de Prematrícula contiene `solicitud_prematricula` (estudiante, periodo y estado) y `detalle_solicitud` (curso-plan, estado y `id_seccion_preferida` opcional). Una preferencia no acredita asignación. El esquema de Matrículas contiene `matricula` (estudiante, periodo y estado) y `matricula_detalle` (sección, estado y fecha de retiro): puede ser candidato para población efectiva, pero faltan un contrato de acceso y semántica de estados, corte temporal, retiros, duplicados y cobertura. La vista de historial disponible no identifica la sección en su salida y no es un contrato de población. No se consultan tablas internas de esos módulos en esta implementación.

No hay en los contratos revisados una relación acreditada de estudiantes con cada actividad, cohorte compartida entre cursos o subgrupo de práctica/laboratorio. Tampoco se identifica la población de repetición, el grupo asistente o la partición de cada laboratorio. Capacidad de sección o ambiente no sustituye cantidad de estudiantes. La ausencia de registros no prueba población cero.

## Preparación y contrato propuesto

`PoblacionAcademica` conserva una fila por sección con identidad, curso-plan, oferta si hay asociación vigente coherente, capacidad y estado. Sin un proveedor conectado devuelve `DESCONOCIDA` y cantidad nula. No distribuye demanda. Como contrato de prueba acepta registros agregados por sección con periodo, curso-plan, cantidad entera no negativa, estado `CONOCIDA` o `ESTIMADA`, fuente, validación `CONFIRMADA`, fecha de referencia y versión de fuente; detecta referencia o periodo incompatibles y duplicados. Un cero solo se presenta como conocido si una fuente explícita y confirmada lo proporciona. Población conocida superior a capacidad registrada positiva se señala como inconsistente; una estimación no demuestra un exceso real. Estos registros de prueba no constituyen una fuente institucional conectada ni autorizan generación.

El proveedor deberá definir identificadores institucionales, significado de estados, fecha de corte, versión monotónica o huella, completitud, correcciones y anulaciones, autorización, idempotencia, privacidad y recuperación; debe declarar si un cero representa ausencia confirmada. Para grupos de actividad deberá proporcionar identidad estable, actividad y sección, población o partición acreditada, docente y reglas de simultaneidad. Para cohortes y cursos únicos deberá proporcionar relaciones de pertenencia acreditadas o conflictos agregados autorizados, con control de acceso y minimización de datos. Repetición requiere además ciclo asistente autorizado. No se crea una migración: el contrato y el equipo responsable están pendientes de acuerdo.

La política del motor `SECCION_Y_COMPARTIDOS_V4` contempla itinerarios por sección y cursos compartidos, con tratamiento particular de repetición y alternativas de laboratorio; no se adopta automáticamente como política institucional. Las letras A/B, nombres de grupo y demanda por curso no acreditan cohortes o particiones.

## Instantánea y comprobación

La instantánea calcula este resumen a partir de sus secciones, demanda y asociaciones ya leídas en la misma transacción `REPEATABLE READ READ ONLY`; no incorpora datos personales ni rellena cohortes o subgrupos. La huella incluye el resumen. El adaptador sigue deshabilitado para ejecución institucional. En la pantalla Preparación de generación se muestra el número de secciones examinadas y sin población confirmada, junto con la disponibilidad de demanda y el estado pendiente de cohortes y subgrupos.

Las pruebas del contrato usan exclusivamente datos ficticios. Para verificar con PostgreSQL se requiere una base desechable y las migraciones del módulo; no aplicar migraciones a la base compartida. La ejecución automática desde Prematrícula y la provisión de población por Matrículas aún no están conectadas.
