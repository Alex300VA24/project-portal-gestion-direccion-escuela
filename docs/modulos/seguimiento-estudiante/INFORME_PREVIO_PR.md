# Informe previo al PR de Seguimiento al Estudiante

**Fecha de revisión:** 24 de noviembre de 2026  
**Estado:** integración parcial. El PR #12 está abierto para revisión. Este informe describe su contenido actual y el alcance previsto para presentarlo a revisión final.

## Objetivo

Integrar en el portal los procesos de SSE-EPI para Estudiante, Docente, Tutor, Consejero, Coordinador, Secretaría y Administrador. Cada proceso debe conservar su pantalla, reglas de negocio, permisos, datos y resultado observable. Superadministrador queda fuera del alcance. La correspondencia entre Administrador y Dirección requiere confirmación.

| Referencia | Versión revisada | Función |
| --- | --- | --- |
| [SSE-EPI original](https://github.com/zoldrix05/SSE_EPI/tree/67381dd43887686b40dce6ec248132f5dc68e479) | `main` en `67381dd` | Fuente funcional para comparar los recorridos y la interfaz. |
| [Rama de integración](https://github.com/zoldrix05/project-portal-gestion-direccion-escuela/tree/8b68b50ee327ceacc956ed431b684d692356cf5d) | `feature/seguimiento-estudiante-integracion` en `8b68b50` | Rama que contiene la adaptación parcial y alimenta el PR #12. |
| [PR #12 del portal](https://github.com/Alex300VA24/project-portal-gestion-direccion-escuela/pull/12) | Destino `develop` | Solicitud que se actualizará cuando se verifique el alcance final. |

## Cambios clave

| Proceso | Incluido en el PR #12 | Pendiente para la entrega completa |
| --- | --- | --- |
| Acceso y expediente | Sesión del portal, permisos y consulta del expediente propio o asignado. | Probar los siete perfiles por escuela, período y asignación. Definir las funciones de Dirección y comprobar sus permisos. |
| Secretaría | Prevalidación de CSV, revisiones de lote y confirmación interna. | Acordar con Matrículas la aplicación oficial, la regla de retiro y la recuperación ante fallos. Mostrar a Secretaría el resultado de la aplicación. |
| Tutoría | Asignación manual, agenda, cambios de cita, atención, compromisos, avisos, cierre y constancia. | Incorporar la asignación automática y el seguimiento entre períodos. Verificar capacidad e historial tras un relevo. |
| Consejería y alertas | El tutor puede consultar alertas registradas en el expediente autorizado. | Completar solicitud, agenda, evaluación de riesgo, evidencia, intervención, derivación y respuesta de Bienestar. |
| Ingreso, Nivelación y Docencia | Consulta de ingreso y nivelación, materiales y PDF privados. | Conectar puntajes, diagnóstico, oferta, inscripción, actividad por sección y publicación de resultados. Verificar el recorrido del docente desde su carga académica. |
| Coordinación, Administración y cierre | Aportes al plan tutorial, revisión y registro versionado del plan general F4. | Completar supervisión, informes, indicadores I1–I8, continuidad, cierre y rectificación con trazabilidad. |

### Documentación incorporada después de la versión revisada

Se actualizó la referencia del sistema original de `0fe2fbe` a `67381dd`. La revisión de paridad deberá incluir los cambios de navegación, los estilos de gestión y el panel de Tutoría presentes en esa versión. También se retiró Superadministrador de la matriz de perfiles previstos. La búsqueda en el código y las vistas del módulo integrado no encontró referencias a ese perfil. El inventario histórico conserva las referencias a estructuras del sistema original.

Esta actualización documenta el alcance y los pendientes de la entrega. La guía explica la organización del código y el inventario detalla qué datos y permisos intervienen en cada flujo. [Guía del módulo](README.md) · [Datos y accesos](DATOS_Y_ACCESOS.md) · [Compatibilidad y configuración](COMPATIBILIDAD_Y_CONFIGURACION.md)

### Funciones del original que requieren comprobación individual

Estas acciones existen en el código original. Su equivalencia en el portal queda por verificar. Se incluyen para que el coordinador pueda distinguir una función trasladada, una cubierta por el portal y una pendiente.

| Área | Comprobación para el traspaso |
| --- | --- |
| Cuenta y funciones | Identificar el destino del contacto, actividad, funciones, preferencias y cambio de contraseña. Con una persona que ejerce dos funciones, verificar el alcance efectivo en cada una. |
| Directorio y exportaciones | Probar listado, ficha rápida, expediente y exportación. Verificar que el archivo descargado respete el mismo ámbito que la pantalla. |
| Consultas API | Identificar consumidores y destino para resumen estudiantil, turnos de Consejería y resumen administrativo. Verificar las respuestas, la autorización y el límite de solicitudes. |
| Ejecución automática | Distinguir comandos operativos de escenarios de demostración. Verificar la actualización horaria de Nivelación y la sincronización de fuentes habilitadas, incluido el comportamiento al reintentar. |
| Consejería y documentos | Comprobar programación por lote, sesión conjunta, PDF, adjunto firmado y descarga por lote. Verificar quién puede generar y recuperar cada documento. |
| Secretaría y Dirección | Localizar plantillas, análisis de cobertura, contacto, registro y revisión de indicadores, respaldo documental, designaciones y auditoría académica. |

Fuente de comparación: [rutas de SSE-EPI en la versión de referencia](https://github.com/zoldrix05/SSE_EPI/tree/67381dd43887686b40dce6ec248132f5dc68e479/src/routes).

### Implementación posterior a la referencia inicial

- El directorio busca por nombre o código en los expedientes autorizados. Un tutor con permiso de consulta puede descargar en CSV los resultados filtrados del directorio. La prueba vuelve a descargar el archivo tras vencer la asignación y comprueba que el expediente desaparece.
- El estudiante consulta las sesiones del grupo publicado en que está inscrito y distingue la asistencia pendiente de una falta registrada.
- La agenda docente lista las sesiones próximas en los grupos publicados del docente activo vinculado a la cuenta. Requiere los permisos `seguimiento-estudiante.view` y `seguimiento-estudiante.nivelacion.agenda-docente`. El responsable de permisos del portal debe asignar este último a quienes gestionen sus sesiones.
- Secretaría descarga plantillas de matrículas, notas y asistencia desde la pantalla de prevalidación. Sus columnas se obtienen de las mismas reglas que utiliza el lector del portal. La interfaz de aplicación oficial y la regla de retiro siguen pendientes de decisión de Matrículas.

Se compiló el frontend y se ejecutaron las pruebas de Seguimiento, rutas, estructura y aislamiento en una base descartable con PHP 8.5.4 y PostgreSQL 18.6. Resultado de esta revisión: 67 pruebas y 743 aserciones. Sigue pendiente repetir esta versión en PHP 8.4 y PostgreSQL 17.

## Cómo probar

El PR #12 registra **66 pruebas y 715 aserciones** sobre `8b68b50`, con PHP 8.4.28 y PostgreSQL 17.12. También registra una instalación limpia y una actualización desde `develop`. Esos resultados corresponden al código ya publicado. [Validación del PR #12](VALIDACION_PR.md)

Para la aceptación final se usarán cuentas y registros sintéticos. Los siguientes recorridos son pruebas previstas para el código completo.

1. **Acceso.** Entrar con cada uno de los siete perfiles. Abrir un recurso permitido e intentar acceder a otro fuera de su ámbito, incluso mediante su URL directa.
2. **Tutoría y Consejería.** Seguir una solicitud desde el estudiante hasta la atención y el resultado visible. Comprobar quién puede consultar la evidencia y cómo se conserva el historial tras una reasignación o derivación.
3. **Secretaría.** Procesar un CSV con errores y otro válido. Comprobar los estados de validación, confirmación interna y aplicación oficial. Interrumpir y reintentar la aplicación para verificar que los registros se aplican una sola vez.
4. **Nivelación.** Seguir una oferta desde Coordinación hasta la inscripción, el registro docente y la consulta del resultado publicado por el estudiante.
5. **Dirección y cierre.** Rastrear un indicador hasta sus datos de origen y verificar que la rectificación de un cierre conserva responsable, motivo y fecha.
6. **Entorno.** Sobre el commit final, compilar el frontend y comprobar migraciones, actualización desde `develop`, aislamiento entre módulos, descargas privadas y procesamiento de recordatorios. Registrar los resultados junto con el commit probado.

## Impacto y riesgos

- **Datos oficiales.** El #12 incluye 23 migraciones de Seguimiento y una de Gestión de Matrículas con dos vistas de consulta. Quedan por revisar con el equipo propietario esas vistas, la regla de retiro y quién aplica o recupera los lotes.
- **Dependencias operativas.** La carga y las secciones docentes dependen de la fuente acordada con Horarios. Las derivaciones y los indicadores requieren definir los intercambios con Bienestar y Calidad.
- **Despliegue.** La entrega requiere permisos y habilitaciones por ámbito, almacenamiento privado persistente y un trabajador para los recordatorios. Las cuentas reales, los expedientes históricos y sus archivos quedan fuera de la entrega de código.

**Criterio para presentar el PR como completo.** Cada acción del inventario de los siete perfiles debe tener un recorrido verificable en el portal o un equivalente institucional comprobado. Todo proceso que aún dependa de una decisión externa figurará como pendiente, indicando qué operación queda afectada. [Guía de módulos](https://github.com/Alex300VA24/project-portal-gestion-direccion-escuela/blob/develop/GUIA_MODULOS.md) · [Convenciones de PR](https://github.com/Alex300VA24/project-portal-gestion-direccion-escuela/blob/develop/docs/PC-Convenciones-Git.md)
