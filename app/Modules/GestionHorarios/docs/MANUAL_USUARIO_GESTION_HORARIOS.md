# Manual de usuario — Gestión de Horarios Académicos

**Dirigido a:** dirección de escuela, secretaría y docentes.
**Estado del módulo:** consulta y preparación parcial; varias acciones requieren adscripción y contratos institucionales que todavía no están conectados.

## 1. Ingreso

1. Acceda al portal con su cuenta institucional.
2. Abra **Gestión de Horarios Académicos** (`/gestion-horarios`).
3. Según su perfil y autorizaciones, la portada mostrará accesos a las consultas disponibles.

Los administradores necesitan sesión web y rol `director_escuela` o `secretaria`. **El rol no acredita por sí solo una escuela.** Si no se puede verificar la adscripción, el portal puede ocultar accesos, denegar operaciones o informar que faltan datos de autorización. Un usuario sin acceso no debe intentar ingresar mediante enlaces directos.

La navegación temporal confirma el ingreso de dirección y secretaría por rol, aunque no exista adscripción acreditada. El profesor con rol `profesor` puede ver su espacio sin datos personales hasta verificar su identidad docente; el alumno conserva su espacio académico y no accede a disponibilidad docente. **Confirmar el rol no habilita consultas privadas.**

## 2. Consultar un periodo y un plan

1. Si tiene permiso y adscripción verificados, desde la portada abra **Periodos Académicos** o una consulta autorizada; sin ellos, los enlaces a datos privados pueden permanecer ocultos.
2. Seleccione el **periodo** cuando la pantalla ofrezca el selector.
3. Seleccione el **plan de estudios autorizado** si la pantalla lo solicita.
4. Consulte el detalle mostrado y ajuste los filtros disponibles.

Un periodo puede contener más de un plan. Si el selector está vacío o aparece un aviso, puede faltar la adscripción institucional, el catálogo o la información del periodo: **no significa necesariamente que no existan registros**.

## 3. Preparación académica

| Pantalla | Qué permite consultar | Límite actual |
| --- | --- | --- |
| **Oferta Académica** | Cursos ofertados, estado, periodo, plan y filtros. | Crear/editar requiere permiso institucional de gestión y escuela autorizada; sin proveedor válido se rechaza. |
| **Secciones** | Secciones y datos académicos del plan seleccionado. | No asigna alumnos ni certifica ausencia de cruces. |
| **Asignación Docente** | Actividades y asignaciones visibles del plan y periodo. | La duración del plan no es necesariamente la carga global del docente. |
| **Disponibilidad Docente** | Franjas y estados de disponibilidad, si se autoriza su ámbito. | La consulta administrativa de recursos compartidos permanece restringida sin contrato. |
| **Ambientes y Laboratorios** | Catálogo, capacidades, bloqueos y restricciones autorizadas. | No sustituye la reserva física ni garantiza un ambiente libre. |
| **Restricciones Académicas** | Reglas y sus datos registrados. | No equivale a validación final de factibilidad. |
| **Revisiones de oferta** | Registros de revisión vinculados al ámbito autorizado. | Si no se acredita escuela, informa el bloqueo y no consulta datos. |

En las pantallas disponibles puede aplicar los filtros correspondientes. Si cambia de plan o periodo, verifique de nuevo la selección antes de interpretar los resultados.

## 4. Mi espacio docente

1. Ingrese con una cuenta autenticada con rol `profesor` y abra `/gestion-horarios/mi-disponibilidad`.
2. El portal intentará verificar su vínculo con un docente institucional.
3. Cuando no pueda comprobarlo, mostrará un mensaje y no revelará asignaciones de terceros. Una cuenta `alumno` no tiene acceso a esta ruta.

La consulta propia existe parcialmente. La **edición institucional de disponibilidad** y la **prioridad docente** no están habilitadas con proveedores oficiales; se han probado solamente con identidades ficticias. Una declaración ausente no es lo mismo que una declaración existente sin franjas disponibles.

## 5. Preparación de generación

1. Abra **Preparación de generación** desde la navegación administrativa autorizada.
2. Seleccione el periodo y plan que corresponda.
3. Revise errores, advertencias y faltantes de demanda, asignación, secciones, calendario, ambientes y otras fuentes.

La prevalidación es una **consulta parcial de diagnóstico**. No crea un horario ni afirma que el motor vaya a encontrar una solución. La generación, aprobación y publicación institucional permanecen deshabilitadas.

## 6. Consultar y descargar horarios

1. Desde un acceso autorizado abra **Horarios registrados**.
2. Seleccione periodo y versión; opcionalmente filtre por ciclo o código exacto de sección.
3. Revise curso, actividad, ambiente y docente confirmado. Las sesiones simultáneas se muestran separadas.

Las acciones PDF y Excel están implementadas y probadas con datos ficticios, pero **las descargas administrativas globales siguen denegadas sin contrato institucional de adscripción**. No se debe interpretar el éxito de una exportación en pruebas como acceso habilitado en el portal. La consulta pública muestra solamente metadatos publicados; existe aparte una consulta semanal detallada autenticada, no una cuadrícula pública institucional.

Consulte [Consulta de horarios](MANUAL_CONSULTA_HORARIOS.md) y [Exportación](MANUAL_EXPORTACION_HORARIOS.md).

## 7. Mensajes frecuentes

| Situación | Interpretación y acción |
| --- | --- |
| No se pudo verificar la escuela o el vínculo docente. | Falta un contrato o una adscripción verificable; comuníquese con la administración del portal. |
| No tiene permiso / HTTP 403. | El servidor rechazó la operación. No significa que deba repetirse con otro identificador. |
| No hay datos con el filtro. | Revise periodo, plan y filtros; diferencie un resultado vacío de un error de consulta. |
| Preparación con errores o advertencias. | Revise las fuentes académicas antes de solicitar generación; no es una propuesta de horario. |
| Creación de horario no habilitada. | Es una restricción deliberada hasta completar y aprobar la integración institucional. |

**Advertencia:** este manual describe el módulo actual. Los permisos, la adscripción, el catálogo institucional y el despliegue de dependencias deben aprobarse antes de su uso operativo. No introduzca datos reales en los entornos ficticios de prueba.

## Consulta institucional: alcance actual

La opción «Horarios publicados» para usuarios autenticados permite consultar versiones y clases publicadas por ciclo y sección, ambiente o docente. Actualmente exige introducir identificadores y periodo; queda pendiente reemplazarlos por selectores alimentados por catálogos autorizados para evitar errores. La consulta personal sigue pendiente de identidad acreditada.
