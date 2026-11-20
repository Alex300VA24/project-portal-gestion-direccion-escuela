# Migraciones de Gestión de Horarios: revisión para PR

Hay 16 migraciones de octubre ya seguidas por Git y cuatro de noviembre sin seguimiento. Todas deben contrastarse con el esquema y registro de migraciones del entorno de destino; esta revisión no acredita compatibilidad ni autoriza desplegarlas. PostgreSQL, el esquema `gestion_horarios`, tablas y claves de Core y Seguridad deben existir previamente. En particular, la FK compuesta de repetición exige una clave compatible en `core.seccion`. La identidad `users` frente a `seguridad.usuario` requiere acuerdo institucional.

| Migración | Objeto o modificación | Dependencias y puntos de revisión |
|---|---|---|
| 2026_10_29_000001 | `oferta_curso` | Periodo y curso-plan Core; unicidad por tipo de oferta. |
| 2026_10_29_000002 | `actividad_academica` | Sección Core y tipos/duraciones. |
| 2026_10_29_000003 | `asignacion_docente` | Actividad y docente Core. |
| 2026_10_29_000004 | `bloque_horario` | Periodo Core; unicidad y franjas. |
| 2026_10_29_000005 | `disponibilidad_docente` | Periodo, docente y bloque; FK de declaración se añade en 000016. |
| 2026_10_29_000006 | `ambiente_bloqueo` | Ambiente y periodo Core, usuario Seguridad. |
| 2026_10_29_000007 | `horario` | Periodo Core y usuarios Seguridad; estados y publicación. |
| 2026_10_29_000008 | `detalle_horario` | Horario, actividad y ambiente Core. |
| 2026_10_29_000009 | `restriccion` | Catálogo propio; acordar semántica académica. |
| 2026_10_29_000010 | `detalle_restriccion` | Restricción, periodo, docente y ambiente. |
| 2026_10_29_000011 | `horario_revision` | Horario y usuario Seguridad. |
| 2026_10_29_000012 | `apertura_repeticion` | Periodo, curso-plan, FK compuesta de sección, docente y usuario. |
| 2026_10_29_000013 | `declaracion_disponibilidad` | Periodo, docente y usuario; distinguir declaración ausente y vacía. |
| 2026_10_29_000014 | `validacion_horario` | Horario y usuario; revisar contrato de huella y versión de reglas. |
| 2026_10_29_000015 | `conflicto_horario` | Validación, detalles, docente y ambiente. |
| 2026_10_29_000016 | Índices, restricciones, funciones, triggers y comentarios | Requiere los SQL `03_restricciones_indices.sql` y `04_comentarios.sql` y tablas anteriores. El `down` elimina objetos expresamente listados; no se ha demostrado aquí que revierta todos los comentarios. |
| 2026_11_16_000001 | `demanda_instantanea`, `demanda_curso` y columna/FK `horario.id_demanda` | Contrato y procedencia de Prematrícula aún no aprobados; altera una tabla existente. |
| 2026_11_16_000002 | `revision_oferta_pendiente` | `oferta_curso`; revisar identidad del actor y retención. |
| 2026_11_17_000003 | `oferta_seccion` | `oferta_curso`, unicidad parcial e identidad del actor; asociación con Core necesita contrato. La ejecución comunicada de `OfertaSeccionPostgresTest` pasó con 9 pruebas y 35 aserciones en PostgreSQL aislado; no se repitió en la revisión documental. |
| 2026_11_17_000004 | `ejecucion_cpsat_prueba` | Tabla exclusiva de ensayo; decidir si debe existir en migraciones de despliegue. No asumir que la protección de entorno impide crear la tabla. |

## Aplicación y reversión

Solo después de aprobación y respaldo, el responsable debe comparar esquema y tabla `migrations`, ensayar el orden de dependencias en una copia desechable y ejecutar exclusivamente el plan de despliegue acordado. No ejecutar migraciones ni reiniciar contenedores compartidos como parte de esta revisión. El `down()` de cada migración creadora elimina su tabla; demanda también retira la FK y columna. Estas operaciones pueden perder datos o fallar por dependencias: la reversión debe planificarse en orden inverso y ensayarse con respaldo, nunca asumirse inocua.

Salvo la asociación indicada, no se ha ejecutado en esta intervención una prueba individual de aplicación y reversión de cada migración. Las pruebas de lectura sobre esquemas ficticios no demuestran compatibilidad de las migraciones con el portal ni su reversión institucional.
