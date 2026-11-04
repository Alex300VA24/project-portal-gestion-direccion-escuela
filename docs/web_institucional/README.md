# Web Institucional: base de datos

Fuente: sección 5.5, página 17 de `modelo_bd_modular_12_esquemas.pdf`.
13 migraciones en `app/Modules/WebInstitucional/Database/Migrations/`, cargadas
automáticamente por `ModulesServiceProvider`. Requieren PostgreSQL y el esquema `web_institucional`.
La primera migración ejecuta `CREATE SCHEMA IF NOT EXISTS web_institucional`,
que también es compatible con la migración general del proyecto que crea esquemas.
El rollback elimina las 13 tablas, pero conserva el esquema.
No requieren tablas de `core`, `seguridad` ni `comun` para ejecutarse.

## Tablas (orden de creación)

1. `categoria_publicacion`
2. `publicacion`
3. `publicacion_archivo`
4. `campana_admision`
5. `campana_modalidad`
6. `cronograma_admision`
7. `documento_oficial`
8. `plan_estudios_publicacion`
9. `perfil_docente_web`
10. `especialidad`
11. `docente_especialidad`
12. `consulta_contacto`
13. `registro_visita`

## Relaciones internas implementadas

Todos los destinos pertenecen a `web_institucional`:

| Origen | Destino |
| --- | --- |
| publicacion.id_categoria | categoria_publicacion.id_categoria |
| publicacion_archivo.id_publicacion | publicacion.id_publicacion |
| campana_modalidad.id_campana | campana_admision.id_campana |
| cronograma_admision.id_campana | campana_admision.id_campana |
| docente_especialidad.id_especialidad | especialidad.id_especialidad |

FK sin borrado ni actualización en cascada (NO ACTION de PostgreSQL).
Las tablas padre se crean primero y el rollback revierte en orden inverso.
`docente_especialidad.id_docente` referencia al docente institucional, no al perfil web.

## Referencias externas pendientes (sin FK físicas)

Todos los orígenes pertenecen a `web_institucional`. Los identificadores son BIGINT;
cada referencia tiene índice propio o está cubierta por el prefijo de su PK.
Las columnas de destino deberán confirmarse al integrar los módulos oficiales.

| Columna de origen | Tabla de destino prevista |
| --- | --- |
| publicacion.id_archivo_portada | comun.archivo |
| publicacion.autor | seguridad.usuario |
| publicacion_archivo.id_archivo | comun.archivo |
| campana_admision.id_periodo | core.periodo_academico |
| campana_modalidad.id_modalidad | core.modalidad_ingreso |
| documento_oficial.id_archivo | comun.archivo |
| plan_estudios_publicacion.id_plan (PK, 1:1) | core.plan_estudios |
| plan_estudios_publicacion.id_archivo_pdf | comun.archivo |
| plan_estudios_publicacion.revisado_por | seguridad.usuario |
| perfil_docente_web.id_docente (PK, 1:1) | core.docente |
| perfil_docente_web.id_archivo_foto | comun.archivo |
| perfil_docente_web.revisado_por | seguridad.usuario |
| docente_especialidad.id_docente | core.docente |
| consulta_contacto.respondido_por | seguridad.usuario |

Pendiente: confirmar tipos/PK compartidas, sanear posibles referencias huérfanas y
agregar las FK mediante nuevas migraciones cuando existan las tablas oficiales.
Las PK de extensiones 1:1 no generan identificadores nuevos.

## Decisiones conservadoras

- Se conservan las columnas del PDF y se añaden metadatos opcionales para P1–P9, documentados abajo.
- PK propias autoincrementales BIGINT; puentes con PK compuestas por sus dos IDs.
  Atributos de cada relación dependen del par completo (3FN).
- VARCHAR(255) para textos cortos; estados VARCHAR(50) sin enumerar valores ni
  predeterminar flujos todavía no definidos. TEXT para contenido, reseñas, enlaces,
  rutas y user agent; teléfono VARCHAR(50), IP VARCHAR(45) para IPv4/IPv6.
- Fechas de campañas, actividades y emisión: DATE. Eventos de publicación,
  revisión, recepción y visita: TIMESTAMPTZ; recepción y visita usan CURRENT_TIMESTAMP.
- Opcionales (NULL): resumen y portada; requisitos; número documental;
  observaciones y datos de revisión; foto, reseña y enlace docente; teléfono y
  responsable de respuesta; IP y user agent. `publicada_en` admite borradores.
  Las demás columnas son NOT NULL. PDF del plan y archivo oficial obligatorios.
- `publicado` inicia en false para no publicar documentos involuntariamente.
  No se establecen otros valores predeterminados.
- CHECK mínimos: vacantes y orden no negativos (orden admite cero), fecha final
  no anterior a la inicial. Sin reglas de solapamiento ni unicidad de orden.
- UNIQUE únicamente en nombres de los dos catálogos y slug, además de las PK.
  Los índices de FK y referencias lógicas son explícitos; no se anticipan índices de consultas futuras.
- Cada `down()` elimina solo su tabla; no crea ni elimina esquemas compartidos.

## Ajustes derivados del mapa de procesos P1–P9

Comparación efectuada sobre los nueve diagramas entregados por el equipo:

- **P1/P2 (publicaciones)**: `publicacion` registra responsable y fecha de
  revisión, observaciones, programación y fecha de retirada. `publicada_en`
  representa el momento real de publicación (no la fecha programada).
- **P3 (docentes)**: `perfil_docente_web` incorpora fecha/observaciones de
  revisión y fecha de retirada; los datos académicos permanecen en `core.docente`.
- **P4 (contacto)**: `consulta_contacto` permite almacenar respuesta, fecha y
  área a la que se derivó la consulta.
- **P5 (roles/acceso)**: corresponde a `seguridad`, sin tablas duplicadas.
- **P6 (planes)**: `plan_estudios_publicacion` añade fecha de publicación;
  versiones, cursos y prerrequisitos pertenecen a `core`.
- **P7 (documentos)**: `documento_oficial` almacena inicio/fin de vigencia,
  versión anterior mediante FK a sí misma, responsable lógico y fecha de
  autorización. La comprobación de expiración y avisos la hará la aplicación.
- **P8 (admisión)**: `campana_admision` guarda URL de postulación, revisión,
  observaciones, publicación y cierre.
- **P9 (consulta pública)**: `registro_visita` permite registrar visitas;
  consultas, filtros y redirecciones los implementará la aplicación.

La documentación base describe 13 tablas sin todos esos metadatos;
los campos adicionales son una extensión acotada del modelo para representar
las actividades identificadas en los diagramas.

### Pendientes fuera de este alcance

- Las referencias a `core`, `seguridad` y `comun` siguen siendo **columnas
  lógicas sin FK físicas**; deberán vincularse cuando el equipo publique
  los esquemas oficiales.
- El mapa exige conservar históricos de ediciones, revisiones y bajas.
  Estas columnas registran el estado/última decisión, **no constituyen
  auditoría histórica completa**. La auditoría compartida (`seguridad.auditoria`)
  o el versionado aprobado por el equipo resolverá ese requisito.
- La publicación automática, procesamiento de imágenes 600×400,
  envío de correos, notificaciones y validación del contenido/PDF
  corresponden al código de la aplicación, no a triggers ni migraciones.
- Falta ejecutar estas migraciones y su rollback en una base PostgreSQL
  aislada: no afirmar que la prueba pasó sin ejecutarla.

## Verificación

- PASÓ: exactamente 13 archivos y 13 tablas; nombres y columnas contrastados
  con la sección 5.5 del PDF.
- PASÓ: cinco FK internas apuntan a tablas creadas previamente; ninguna FK
  física externa; cada `down()` elimina su correspondiente tabla.
- PASÓ: `php -l` en las 13 migraciones.
- PASÓ: `git diff --check` y revisión equivalente de archivos nuevos sin stage.

No se ejecutan migraciones reales ni se configura PostgreSQL; la validación de
 ejecución y rollback queda para otra fase. No se realiza commit ni push.
