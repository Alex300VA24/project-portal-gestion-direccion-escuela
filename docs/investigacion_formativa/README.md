# Investigación Formativa — persistencia

Implementa exclusivamente `investigacion_formativa`, según §5.3, p. 14 de
`modelo_bd_modular_12_esquemas.pdf` y §B.3, p. 7 de
`esquemas_y_origen_de_tablas.pdf`. Las referencias locales no forman parte de la implementación.

## Tablas y origen

| Tabla | Uso / origen |
| --- | --- |
| `tipo_producto` | Catálogo de productos; G10: tipo_producto. |
| `grupo` | Equipo por sección; G10: grupo. |
| `grupo_estudiante` | Integrantes y bandera de coordinador; G10: grupo_estudiante. |
| `proyecto` | Propuesta, línea y producto; G10: proyecto. Un proyecto por grupo. |
| `rubrica` | Instrumento y docente autor; G10: rubrica. |
| `criterio` | Descripción, máximo y orden por rúbrica; G10: criterio. |
| `entregable` | Cronograma por sección, plazo y rúbrica; G10: entregable. |
| `entrega` | Archivo y versión por proyecto/entregable; G10: entrega. |
| `evaluacion` | Evaluador y comentario; G10: evaluacion. Una por entrega. |
| `evaluacion_criterio` | Nuevo detalle normalizado de G10: evaluacion. |
| `observacion` | Observaciones, estado y autor sobre una entrega; G10: observacion. |

## Referencias compartidas pendientes

Todos estos IDs son BIGINT, documentados también mediante comentarios SQL, sin FK físicas externas:

| Columna local | Destino oficial |
| --- | --- |
| `grupo.id_seccion`, `entregable.id_seccion` | `core.seccion(id_seccion)` |
| `grupo_estudiante.id_estudiante` | `core.estudiante(id_estudiante)` |
| `proyecto.id_linea` | `core.linea_investigacion(id_linea)` |
| `rubrica.id_docente_autor`, `evaluacion.id_docente_evaluador` | `core.docente(id_docente)` |
| `entrega.id_archivo` | `comun.archivo(id_archivo)` |
| `observacion.registrado_por` | `seguridad.usuario(id_usuario)` |

`core.seccion` unifica Gestión de Horarios y Prematrícula y proporciona curso,
periodo y docente. Estudiante y docente son entidades compartidas; la línea
unifica Investigación Formativa y Tesis. Seguridad centraliza identidades y
Común, documentos. No se duplican entidades ni se enlazan módulos independientes.
Cada referencia tiene un índice propio o un UNIQUE cuyo primer campo la cubre.
La existencia externa queda sin validar hasta incorporar las tablas oficiales.

## Migraciones y rutinas

Las 11 migraciones individuales están en
`app/Modules/InvestigacionFormativa/Database/Migrations/`, seguidas de la
complementaria `2026_11_03_000012_install_rutinas_investigacion_formativa.php`.
El proveedor modular existente las descubre automáticamente. Requieren el esquema
creado por la migración global existente; no crean ni eliminan esquemas compartidos.
SQL PostgreSQL explícito conserva IDENTITY BIGINT, TIMESTAMPTZ y restricciones.
Todas las FK internas son RESTRICT; el rollback elimina primero rutinas y luego
tablas en orden inverso, sin borrado en cascada. Revertir tablas elimina sus datos.

SQL inmutable de esta versión: `database/investigacion_formativa/v1_rutinas_up.sql`
y `v1_rutinas_down.sql`; cualquier cambio posterior requiere otra versión/migración.

- `fn_validar_entrega()` / `trg_validar_entrega`: antes de insertar o actualizar
  una entrega comprueban igualdad de sección por proyecto → grupo y entregable.
- `fn_validar_evaluacion_criterio()` / `trg_validar_evaluacion_criterio`:
  antes de insertar o actualizar un detalle comprueban rúbrica y rango 0..máximo.
- `fn_calcular_puntaje_entrega(BIGINT)`: suma NUMERIC sin persistir total;
  devuelve NULL sin evaluación (también para ID inexistente), 0 si no hay detalles.
  Es suma disponible, no certificación de evaluación completa.
- `sp_registrar_puntaje_criterio(BIGINT, BIGINT, NUMERIC)`: upsert sujeto al mismo trigger.

```sql
CALL investigacion_formativa.sp_registrar_puntaje_criterio(1, 2, 8.50);
SELECT investigacion_formativa.fn_calcular_puntaje_entrega(1::BIGINT);
```

## Diferencias y límites

Frente al SQL G10 de 15 tablas: periodo, curso, docente, estudiante y línea se
externalizan; sección reemplaza curso-periodo/docente-curso. Se añade el detalle
por criterio, se invierte la relación evaluación-entrega y se elimina
`puntaje_total`, pesos y campos antiguos no incluidos en el modelo oficial.
Se incorporan versiones, archivo, autoría, estados y plazos. PK compuestas,
UNIQUE, NOT NULL, CHECK de textos requeridos, orden/versión positivos y puntajes
finitos protegen el modelo; las descripciones auxiliares, resumen y comentario
son opcionales. Los máximos conservan NUMERIC(5,2) del original.

El mapa queda cubierto para equipos, propuestas, producto/línea, cronograma,
observaciones de entregas, correcciones mediante nuevas versiones y evaluación
por rúbrica con suma calculada. No define ponderación entre entregables,
selección de versión final, escala aprobatoria ni evaluación completa: la suma
de una entrega no establece por sí sola el resultado final del proyecto.
Tampoco se modelan observaciones previas a una entrega ni notificaciones.

Pendientes: vocabularios/transiciones de estado, cantidad de coordinadores,
pertenencia de estudiantes a secciones y política de entregas tardías. No se
imponen reglas académicas no documentadas. Los triggers validan las escrituras
de entrega/detalle, no revalidan cambios posteriores en sección del grupo,
grupo del proyecto, rúbrica del entregable, entrega de la evaluación ni rúbrica/máximo
del criterio; falta definir su política de modificación tras registrar resultados.

Competencias, niveles de logro y satisfacción aparecen en el mapa, pero el nuevo
modelo ubica `competencia`, `cat_nivel_logro` y `cat_nivel_satisfaccion` en
Seguimiento al Egresado. Su aplicabilidad, encuestas y relación con este módulo
quedan pendientes: no se crean tablas ni dependencias físicas hacia ese módulo.

Validación inicial exclusivamente estática: conteo, sintaxis PHP, orden de
dependencias y whitespace. No se han ejecutado PostgreSQL, migraciones ni Docker.
