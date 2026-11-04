# Base de datos general

PostgreSQL 17 con un único catálogo (`portal_gestion_direccion`) dividido en esquemas. Cada módulo es dueño de su esquema; los datos que comparten varios módulos viven en esquemas generales.

## 1. Esquemas

| Tipo | Esquema | Tablas | Contenido |
| --- | --- | ---: | --- |
| General | `public` | 14 | Infraestructura Laravel: `users`, `sessions`, `cache`, `jobs`, `migrations`, roles y permisos de Spatie |
| General | `core` | 22 | Datos académicos compartidos: persona, estudiante, docente, facultad, escuela, plan de estudios, curso, período, sección, ubigeo |
| General | `seguridad` | 10 | Identidad y acceso: usuario, rol, permiso, tokens, registro de acceso, auditoría inalterable, `modulo_sistema` |
| General | `comun` | 7 | Utilidades: archivo, empresa, contacto de empresa, notificación, parámetro del sistema, día no laborable, sector económico |
| Módulo | `prematricula` | 3 | Pre Matrícula |
| Módulo | `bienestar` | 18 | Bienestar |
| Módulo | `investigacion_formativa` | 11 | Investigación Formativa |
| Módulo | `seguimiento_estudiante` | 23 | Seguimiento al Estudiante |
| Módulo | `web_institucional` | 13 | Web Institucional |
| Módulo | `calidad` | 12 | Calidad |
| Módulo | `gestion_horarios` | 15 | Gestión de Horarios |
| Módulo | `cotecu` | 10 | COTECU |
| Módulo | `gestion_practicas` | 11 | Gestión de Prácticas |
| Módulo | `tesis` | 22 | Tesis y Proyecto de Tesis |
| Módulo | `gestion_matriculas` | 9 | Gestión de Matrículas (más la vista `v_historial_academico`) |
| Módulo | `seguimiento_egresado` | 40 | Seguimiento al Egresado |

`config/modules.php` es el inventario oficial de módulos y esquemas. La tabla `seguridad.modulo_sistema` se siembra con los 12 módulos.

## 2. Reglas de dependencia

1. Las claves foráneas de un módulo solo pueden apuntar a su propio esquema, a `core`, `seguridad`, `comun` o `public`.
2. Nunca hay FK entre esquemas de módulos distintos. Si un módulo necesita identificar un registro de otro, guarda el id como referencia lógica (sin FK) y lo resuelve en la capa de servicio. Ejemplo: `gestion_matriculas.id_solicitud_prematricula`.
3. Todas las tablas se escriben calificadas: `esquema.tabla`.
4. `seguridad.auditoria` es inalterable: un trigger (`seguridad.auditoria_inalterable`) bloquea `UPDATE` y `DELETE`.
5. La autenticación de Laravel y los roles de Spatie siguen en `public`. La identidad académica (`seguridad.usuario`) convive con `public.users`; aún no se reemplazó una por otra.

```text
        módulo A          módulo B
           │                 │
           └───────┬─────────┘
                   ▼
      core · seguridad · comun · public
```

## 3. Tablas por esquema

| Esquema | Tablas |
| --- | --- |
| `core` | ambiente, area_formacion, curso, curso_plan, curso_prerequisito, departamento_academico, docente, docente_linea, equivalencia_curso, escuela_profesional, estudiante, facultad, linea_investigacion, modalidad_ingreso, pais, periodo_academico, persona, plan_estudios, seccion, ubigeo_departamento, ubigeo_distrito, ubigeo_provincia |
| `seguridad` | auditoria, modulo_sistema, permiso, registro_acceso, rol, rol_permiso, solicitud_acceso, token_usuario, usuario, usuario_rol |
| `comun` | archivo, contacto_empresa, dia_no_laborable, empresa, notificacion, parametro_sistema, sector_economico |
| `prematricula` | cronograma_prematricula, detalle_solicitud, solicitud_prematricula |
| `bienestar` | beneficio, caso_bienestar, cita, deporte, derivacion, especialidad, especialista, estudiante_condicion, estudiante_deporte, historial_estado_caso, horario_disponible, perfil_socioeconomico, reporte, seguimiento_derivacion, solicitud_apoyo, solicitud_documento, tipo_caso, tipo_condicion |
| `investigacion_formativa` | criterio, entrega, entregable, evaluacion, evaluacion_criterio, grupo, grupo_estudiante, observacion, proyecto, rubrica, tipo_producto |
| `seguimiento_estudiante` | alerta_sat, atencion, atencion_compromiso, atencion_derivacion, atencion_evidencia, atencion_motivo, brecha_ingreso, dimension_brecha, motivo_atencion, nivelacion_asistencia, nivelacion_calificacion, nivelacion_criterio_evaluacion, nivelacion_grupo, nivelacion_material, nivelacion_participante, nivelacion_sesion, parametro_sat, perfil_ingreso, programa_nivelacion, situacion_academica, solicitud_estudiante, tipo_atencion, tutor_asignacion |
| `web_institucional` | campana_admision, campana_modalidad, categoria_publicacion, consulta_contacto, cronograma_admision, docente_especialidad, documento_oficial, especialidad, perfil_docente_web, plan_estudios_publicacion, publicacion, publicacion_archivo, registro_visita |
| `calidad` | accion_mejora, comite_calidad_miembro, dimension_acreditacion, estandar, evidencia, indicador, indicador_estandar, medicion_indicador, meta_indicador, modelo_acreditacion, plan_mejora, proceso |
| `gestion_horarios` | actividad_academica, ambiente_bloqueo, apertura_repeticion, asignacion_docente, bloque_horario, conflicto_horario, declaracion_disponibilidad, detalle_horario, detalle_restriccion, disponibilidad_docente, horario, horario_revision, oferta_curso, restriccion, validacion_horario |
| `cotecu` | lista_cotejo_items, lista_cotejo_silabos_items, listas_cotejo, listas_cotejo_silabos, planes_curriculares, resultados_items, resultados_silabos_items, revisiones_plan_curricular, revisiones_silabo, silabos |
| `gestion_practicas` | convocatoria, criterio_evaluacion, documento_practica, empresa_usuario, evaluacion, evaluacion_criterio, observacion_convocatoria, observacion_practica, postulacion, practica, seguimiento |
| `tesis` | acta, actividad_cronograma, asesor_proyecto, colaborador, criterio_rubrica, detalle_evaluacion, entrega, historial_estado, jurado_proyecto, observacion, partida_presupuesto, proyecto_estudiante, proyecto_tesis, recurso, reporte_similitud, resolucion, resultado_jurado, revision, rubrica, solicitud, sustentacion, tipo_solicitud |
| `gestion_matriculas` | acta_notas, asistencia, componente_evaluacion, cronograma_matricula, limite_creditos, matricula, matricula_detalle, nota, reserva_matricula |
| `seguimiento_egresado` | 40 tablas: catálogos `cat_*`, campañas, cohortes, egresado, contacto, formación posterior, medición laboral, competencias, objetivos educacionales, satisfacción, importación de padrón, planes de acción, reportes e indicadores |

## 4. Funciones, triggers y vistas

| Objeto | Descripción |
| --- | --- |
| `core.validar_curso_prerequisito_mismo_plan` | Un prerrequisito debe pertenecer al mismo plan de estudios |
| `seguridad.auditoria_inalterable` | Trigger que impide modificar o borrar la auditoría |
| `gestion_matriculas.calcular_creditos_matriculados` | Créditos matriculados de un estudiante |
| `gestion_matriculas.v_historial_academico` | Vista del historial académico |
| `gestion_practicas.calcular_nota_final` | Nota final de una práctica |
| `calidad.calcular_valor_indicador` | Valor calculado de un indicador |
| `tesis.calcular_nota_sustentacion` | Nota de sustentación |
| `gestion_horarios.fn_*` | Huella, validación, conflictos y transiciones de horarios |
| `web_institucional.sp_responder_consulta_contacto` | Procedimiento para responder una consulta de contacto |

## 5. Migraciones

```text
database/migrations/                         # public: infraestructura Laravel
app/Shared/Database/Migrations/              # core, seguridad, comun
app/Modules/{Modulo}/Database/Migrations/    # esquema de cada módulo
```

Laravel mezcla y ordena todas las rutas de migración solo por nombre, y eso permitiría crear una tabla de módulo antes que `core`. Por eso el proyecto usa su propio comando, que ejecuta los grupos por separado y en orden: infraestructura, `app/Shared` y, por último, el módulo pedido.

```bash
php artisan module:migrate all               # todo, en orden
php artisan module:migrate gestion-practicas # un módulo (aplica antes lo compartido)
php artisan module:migrate:status all        # estado agrupado
```

- El historial es único: `public.migrations`.
- El contenedor `app` ejecuta `module:migrate all --force` al arrancar. Es idempotente.
- Una migración ya aplicada en un entorno compartido no se edita: se crea una nueva.
- Cada migración debe tener `down()` y usar nombres calificados (`esquema.tabla`).
- Una migración con FK hacia otro esquema debe tener un timestamp posterior a la que crea la tabla referenciada.
- Las migraciones de un módulo solo tocan su esquema. Las tablas de `core`, `seguridad` y `comun` se modifican únicamente desde `app/Shared/Database/Migrations`.

## 6. Operación

```bash
# conectar
docker compose exec postgresql sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'

# dentro de psql
\dn                       -- esquemas
\dt bienestar.*           -- tablas de un esquema
\d  seguridad.usuario     -- estructura de una tabla
```

Datos en el volumen `app_postgresql_data`. `docker compose down` los conserva; `docker compose down -v` los borra.

Validación aislada (no toca el volumen real): `./scripts/validate-modular-database.ps1`. Levanta un entorno temporal en los puertos `18000` y `15433`, aplica dos pasadas de migraciones, comprueba conteos, FK y funciones, prueba todos los `down()` y limpia al terminar.
