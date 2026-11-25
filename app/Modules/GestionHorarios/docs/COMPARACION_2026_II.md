# Gestión de Horarios · comparación con la referencia 2026-II

## Matriz funcional y visual

La referencia monta Inertia en `resources/views/app.blade.php`. Las cuatro páginas Vue, los componentes de `resources/js/Components`, los composables del editor, los estilos de impresión y el JavaScript de consulta contienen la interfaz funcional. El portal usa el layout institucional, Blade, rutas Laravel, Livewire y repositorios sobre Core y Gestión de Horarios.

| Referencia | Equivalente del portal | Diferencia y estado |
| --- | --- | --- |
| Workspace, Inicio, menú de tareas | inicio-contenido, index | La portada diferencia perfiles. El rol de dirección o secretaría permite ingresar, pero el plan requiere adscripción verificable. |
| PeriodoAcademico, Preparacion, ComprobadorPreparacion | periodos, oferta-academica, preparacion | La consulta por plan y el resumen parcial sustituyen al asistente. El calendario completo y el presupuesto por periodo requieren persistencia. |
| Cursos, OfertaAcademica, DemandaEditable | oferta-academica | Se conservan filtros por plan, ciclo, estado y tipo. Prematrícula registra solicitudes y sección preferida, sin publicar un agregado versionado con preferencias de turno. |
| Secciones, DistribucionCurso, SeccionesAvanzadas | secciones | Core aporta periodo, curso-plan, código, capacidad y responsable. La asociación explícita por tipo es un contrato distinto de compartir curso y periodo. |
| GruposCurso, GrupoAsesoria, RepeticionNueva | secciones, oferta-academica | La oferta distingue repetición. Apertura autorizada, ciclo asistente y partición acreditada requieren datos adicionales. Un cupo de sección no acredita población ni subgrupo. |
| Docentes, AsignacionCurso, ResponsableActividadCurso | asignacion-docente | La responsabilidad de sección de Core y la asignación de actividad son distintas. La consulta de recursos compartidos necesita alcance por escuela. |
| Docente, DisponibilidadSemanal, FichaDisponibilidad | mi-disponibilidad, disponibilidad-docente | Declaración y franjas con estados diferenciados. Edición del calendario y vínculo de identidad requieren respaldo institucional. |
| PrioridadDocentes | sin editor institucional | El motor admite prioridad SOFT. La rama carece de cola institucional versionada. |
| Ambientes, Restricciones | ambientes, restricciones | Hay catálogos, bloqueos y severidades. Falta alcance administrativo para operaciones globales. La ocupación publicada se consulta por ambiente. |
| NuevaPropuesta, Elaboracion, EstadoGeneracion | preparacion, horarios-registrados | Prevalidación parcial. No hay solicitud productiva de ejecución, seguimiento de cola o presupuesto editable con persistencia. |
| EditorSemanal, FijarCurso, CompararVersiones | semana-publicada, horarios-registrados | La consulta semanal permite filtrar por ciclo/sección, docente o ambiente. Edición manual con revalidación, fijación y versionado siguen pendientes. |
| Revision, PropuestaConjunta | revisiones, horarios-registrados | Revisiones pendientes de oferta y estados de horario son entidades distintas. El flujo completo necesita identidad de actor, validación e historial. |
| Publico, CalendarioConsulta | publicados, consulta-publicados, semana-publicada | Los metadatos están disponibles públicamente. El detalle semanal requiere sesión web. |
| ImpresionHorario, exportacionHorario, impresion.css | ExportacionHorarios, exportacion-pdf | Existen generadores y pruebas de exportación. La entrega de documentos con datos privados depende del alcance de la consulta. |

## Motor y datos

Los paquetes Python HARD, SOFT y exporters implementan el motor. El adaptador de preparación PHP verifica huellas y exige complementos. El ensayo de una entrada completa en Python y la generación desde datos institucionales son verificaciones independientes. La fuente requerida para la demostración es `demo_2026_ii/origen_2013_ii.json` procesada por `Demo2026IISeeder`. La carpeta hermana `2026_ii` y `Semestre2026IISeeder` corresponden a otro conjunto.

## Alcance de la entrega

- La planificación manual crea y edita código y capacidad de secciones en `core.seccion`, sin exigir la persistencia de demanda. No crea automáticamente actividades T/P/L ni subgrupos.
- El seeder `Database\Seeders\GestionHorariosDemo2026IISeeder` requiere PostgreSQL local, la base aislada `horarios_demo_2026_ii`, un catálogo curricular y las variables `HORARIOS_DEMO_ARCHIVO` y `HORARIOS_DEMO_PLAN`. No cargar en producción ni distribuir el archivo fuente con datos personales.
- El ensayo de Python con entrada completa no acredita la generación desde el portal. La creación productiva de horarios permanece deshabilitada.
- Los proveedores institucionales de escuela y permiso siguen siendo necesarios para las operaciones protegidas. Los dobles de prueba no deben registrarse en producción.
- Las migraciones complementarias y las dependencias compartidas excluidas del PR anterior permanecen pendientes de revisión.
- Antes de aprobar este PR, repetir las pruebas Laravel y la inspección visual con una base aislada y documentar aprobadas, fallidas y omitidas. La revisión de sintaxis no sustituye esa validación.
