# Cierre de Prematrícula y consolidación de vacantes

**Naturaleza:** regla funcional requerida. La compuerta local de consolidación y creación automática se implementa en este ensayo; queda pendiente validarla en PostgreSQL y conectar la asignación docente institucional.

## Secuencia obligatoria

1. **Prematrícula abierta:** recibir automáticamente demanda agregada versionada por curso-plan. Permitir consulta y cálculo preliminar por aforo, sin modificar demanda manualmente, crear/ajustar secciones definitivas ni asignar docentes para el nuevo ciclo de planificación.
2. **Cierre oficial:** Prematrícula confirma que el proceso concluyó, incluidas ventanas ordinarias, extraordinarias y de corrección. La ausencia de una ventana activa por sí sola no acredita cierre. En QA, `CierreDemandaPrematricula::confirmado()` comprueba `EN_CURSO`, existencia de ventana ordinaria y ausencia de ventanas con fin futuro; esta equivalencia debe acordarse institucionalmente. No se exige cerrar el semestre académico.
3. **Consolidación de demanda y vacantes:** sincronizar la última instantánea completa y verificar que coincide con la fuente cerrada. Dirección o secretaría, con permiso y escuela autorizados, revisa los totales por curso, resuelve inconsistencias y registra ajustes justificados sin sobrescribir la fuente. Calcular y confirmar los cupos/vacantes a ofertar y los aforos compatibles para el plan y periodo. Registrar usuario, fecha, versión de origen, ajustes efectivos y huella de la propuesta consolidada. La confirmación debe quedar persistida y distinguida del mero cierre.
4. **Creación de secciones:** solo después de cierre y consolidación vigentes, confirmar y aplicar el cálculo automático por aforo a los cursos regulares; asociar cada sección a su oferta. Conservar identificadores y no eliminar registros automáticamente. Resolver excepciones antes de considerar listo el plan.
5. **Asignación docente:** habilitar la asignación por sección y actividad T/P/L después de crear y verificar las secciones y los subgrupos necesarios. No usar docentes mínimos como entrada manual de la oferta. La prioridad docente no sustituye la asignación ni la disponibilidad.
6. **Preparación y generación:** verificar carga, disponibilidad, calendario, restricciones, recursos y vigencia de la consolidación; generar CP-SAT únicamente tras completar las etapas requeridas. La revisión y publicación conservan sus controles propios.

## Diferencia entre cantidades

- Demanda de origen: solicitudes agregadas de Prematrícula, no matrícula definitiva.
- Demanda efectiva: demanda de origen más los ajustes vigentes autorizados (o total ajustado registrado).
- Vacantes consolidadas: cupos que se aprueba ofertar; deben cubrir la demanda efectiva planificada y respetar capacidades de ambientes. No equivalen a estudiantes matriculados.
- Población estimada: reparto no nominal por sección/subgrupo; no sustituye los padrones oficiales.

Ejemplo ilustrativo: 85 solicitudes efectivas y aforo máximo compatible de 63 permiten proponer dos secciones (43 y 42 estudiantes estimados); los cupos de cada sección se revisan y consolidan antes de persistir las secciones. No se asignan alumnos individuales a partir de estas cifras.

## Compuertas de autorización y vigencia requeridas

| Condición | Consultar y simular | Crear/ajustar secciones | Asignar docentes |
| --- | --- | --- | --- |
| Prematrícula abierta, futura o cierre no acreditado | Sí, según autorización | No | No |
| Cierre confirmado, demanda sin consolidar o desactualizada | Sí | No | No |
| Cierre y consolidación vigentes; secciones pendientes | Sí | Sí, con autorización y confirmación | No |
| Secciones verificadas para la consolidación vigente | Sí | Solo mediante revisión si hay dependencias | Sí, con autorización |
| Reapertura o cambio de demanda/ajuste/aforo relevante | Sí | No hasta reconsolidar | No nuevas asignaciones hasta revisar y verificar secciones |

Las comprobaciones se requieren en servidor (no solo ocultar botones), bajo una lectura coherente y bloqueo transaccional. La confirmación debe referir exactamente el periodo, plan, escuela, versión y huella vigentes. Si cambia la fuente, ajustes o aforos relevantes, rechazar propuestas antiguas; conservar secciones, asignaciones y horarios existentes sin borrarlos ni sobrescribirlos. Cuando existan dependencias o publicaciones, tramitar una revisión autorizada. No alterar automáticamente una matrícula o un horario publicado.

## Pruebas de aceptación por implementar/verificar

- Con Prematrícula abierta y demanda creciente, la consulta se actualiza y la simulación se recalcula; la interfaz y las llamadas directas de escritura rechazan altas de secciones y asignaciones.
- Con todas las ventanas finalizadas pero sin cierre oficial, las escrituras siguen bloqueadas.
- Con cierre confirmado y última demanda sincronizada, sin consolidación, las escrituras siguen bloqueadas.
- Tras consolidar, crear las secciones por aforo y verificar cobertura; una confirmación repetida no duplica registros. Asignar docentes solo tras verificar la preparación de secciones.
- Si cambia la demanda, se reabre Prematrícula o se modifica un ajuste o aforo relevante, invalidar la consolidación anterior y bloquear nuevas escrituras hasta revisar; preservar dependencias existentes.
- Rechazar usuario no autorizado, otro plan/escuela/periodo y propuestas con huella desactualizada. Ejecutar pruebas de concurrencia con el proveedor de cierre y el repositorio de demanda.

## Estado de implementación

La pantalla de secciones ahora exige cierre y una confirmación persistida en `gestion_horarios.consolidacion_vacantes`. La huella incluye versión, revisión PostgreSQL (`xmin`) del periodo/ventanas, ajustes vigentes, aforos y cupos propuestos. Se revalida la fuente bajo bloqueo antes de consolidar o aplicar. La marca `xmin` es conservadora y exclusiva del ensayo PostgreSQL: cualquier actualización del periodo o sus ventanas exige reconsolidar. En producción se necesita una revisión de cierre explícita y estable emitida por Prematrícula.

Las altas y asociaciones manuales de Gestión de Horarios en la base local de integración se rechazan; el servicio automático solo habilita esas operaciones dentro de su transacción autorizada. No se habilita asignación docente institucional: el flujo existente solo permite reasignaciones en la base QA histórica independiente. La pantalla indica si las secciones están cubiertas; falta comprobar subgrupos y desarrollar el servicio institucional de asignación. No ejecutar scripts externos antiguos que escriben directamente en Core (incluido `preparar_docentes_calendario_integracion.php --aplicar`): no pasan por estas compuertas. Los registros de QA anteriores se conservan. El script de aforo dentro del módulo ya rechaza `--aplicar`. Las excepciones especiales impiden consolidar automáticamente el plan hasta que exista un mecanismo de revisión autorizado.

Pruebas previas reportadas: 163 correctas, 115 omitidas, 790 aserciones; arquitectura 5/5. Esta evidencia es anterior a la presente compuerta. Ejecutar las nuevas pruebas y el flujo real con una base PostgreSQL aislada antes de integrar. No habilita generación ni publicación institucional.
