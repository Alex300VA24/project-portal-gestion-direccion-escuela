# Validación y borrador de PR de Seguimiento al Estudiante

Fecha de validación 20 de noviembre de 2026. Rama local feature/seguimiento-estudiante. La rama develop de GitHub se verificó en eebebd81261cffca976cdbb5048413826aaca82b.

## Borrador para GitHub

**Título**

Integra los primeros flujos de Seguimiento al Estudiante en el portal

**Base**

develop

**Rama**

feature/seguimiento-estudiante

**Objetivo**

Integrar el código de SSE-EPI con la sesión y los permisos del portal. Las migraciones incorporan estructura y conservan los registros existentes. La entrega no requiere cargar cuentas, archivos ni datos históricos de SSE-EPI.

**Cambios incluidos**

- Identidad académica vinculada a la sesión del portal, acceso por permiso de Spatie y alcance de escuela, período y expediente.
- Tutoría con asignación y reasignación manual, solicitudes, agenda, atención, rectificaciones, compromisos, avisos y constancia personal.
- Secretaría con prevalidación, validación de referencias, lotes versionados y confirmación para futura entrega.
- Consulta del perfil de ingreso y resultados publicados de Nivelación. Documentos PDF y materiales privados con autorización por inscripción o ámbito de gestión.
- Presentación y revisión del aporte tutorial conservando versiones y la separación del borrador privado.
- Consulta de alertas y situación académica existentes. Cola del portal para recordatorios de citas.

**Verificación reproducida en un entorno descartable**

| Comprobación | Resultado |
| --- | --- |
| PostgreSQL 17.12 con instalación modular y pruebas de aislamiento | 113 pruebas y 693 aserciones |
| Sesión, navegación y perfil del portal sobre una segunda base PostgreSQL 17.12 | 29 pruebas y 90 aserciones |
| Instalación limpia y actualización desde develop | El smoke SQL pasó. La actualización conservó una solicitud sintética preexistente. |
| Imagen del portal con PHP 8.4.28 y Apache | Sesión HTTP, expediente propio, descarga PDF, rechazo de un recurso fuera de alcance y carga del JavaScript compilado. |
| Envíos y trabajadores simultáneos | Dos validaciones del mismo lote devolvieron una revisión. Dos trabajadores dejaron un solo aviso y ningún trabajo fallido. |
| Verificación estática | Composer, Pint del módulo, compilación de Vite y git diff --check. |

Se usaron contenedores aislados de Podman sin publicar puertos y con sockets locales descartables. El paquete de ejecución excluye el almacenamiento privado y los archivos generados por pruebas. El caso de una cuenta con expedientes propios en dos escuelas verifica el perfil seleccionado en Nivelación y la constancia que corresponde a la solicitud.

**Impacto operativo**

- Aplicar las 20 migraciones nuevas únicamente en el entorno aprobado. El esquema de Seguimiento queda con 38 tablas. La migración de asignaciones conserva los episodios previos al reasignar. Una reversión posterior a la creación de dichos episodios puede requerir un plan específico porque la restricción anterior no admite el historial repetido.
- Provisionar vínculos y permisos de Spatie por persona y sus ámbitos académicos con los responsables del portal.
- Respaldar y persistir el almacenamiento privado de Nivelación. Sus adjuntos usan el catálogo institucional. Programar el planificador del portal y un trabajador que consuma la cola seguimiento-estudiante.

**Límites de la entrega**

- Secretaría confirma el lote dentro de Seguimiento. Sigue pendiente el contrato de entrega, aplicación oficial y recuperación con Gestión de Matrículas.
- Consejería y Nivelación conservan los recorridos parciales registrados en el plan. El informe final se genera a partir de un cierre publicado. La aprobación y publicación operativa del cierre pertenece al flujo académico pendiente.
- La asignación automática, el plan general, los indicadores I1–I8, la continuidad y el cierre del proceso académico siguen pendientes.

Consulta [el estado de las fases 5 y 6](README.md#5-incorporar-los-procesos-por-entregas) y [los permisos y contratos](DATOS_Y_ACCESOS.md#contratos-con-otros-módulos).
