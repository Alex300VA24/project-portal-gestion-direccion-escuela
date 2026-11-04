# Catálogo de tablas del sistema

Inventario de tablas activas definido por las migraciones del proyecto al 3 de noviembre de 2026.
Los nombres están calificados como `esquema.tabla` para evitar ambigüedad en PostgreSQL.

## Resumen

| Área | Esquema | Cantidad | Estado |
| --- | --- | ---: | --- |
| Infraestructura Laravel, autenticación y permisos | `public` | 14 | Activa |
| Datos académicos compartidos | `core` | 21 | Activa |
| Seguridad compartida | `seguridad` | 2 | Activa |
| Utilidades compartidas | `comun` | 7 | Activa |
| Pre Matrícula | `prematricula` | 3 | Activa |
| Gestión de Matrículas | `gestion_matriculas` | 9 | Activa |
| COTECU | `cotecu` | 10 | Activa |
| Gestión de Horarios | `gestion_horarios` | 15 | Activa |
| Gestión de Prácticas | `gestion_practicas` | 11 | Activa |
| Calidad | `calidad` | 12 | Activa |
| Tesis y Proyecto de Tesis | `tesis` | 22 | Activa |
| **Total** | **11 esquemas** | **126** | **Activas** |

## `public` — infraestructura Laravel

1. `public.migrations`
2. `public.users`
3. `public.password_reset_tokens`
4. `public.sessions`
5. `public.cache`
6. `public.cache_locks`
7. `public.jobs`
8. `public.job_batches`
9. `public.failed_jobs`
10. `public.permissions`
11. `public.roles`
12. `public.model_has_permissions`
13. `public.model_has_roles`
14. `public.role_has_permissions`

`public.migrations` es creada por el migrador de Laravel. Las demás se definen en `database/migrations`.

## `core` — datos académicos compartidos

1. `core.pais`
2. `core.ubigeo_departamento`
3. `core.ubigeo_provincia`
4. `core.ubigeo_distrito`
5. `core.persona`
6. `core.facultad`
7. `core.escuela_profesional`
8. `core.departamento_academico`
9. `core.modalidad_ingreso`
10. `core.periodo_academico`
11. `core.plan_estudios`
12. `core.area_formacion`
13. `core.curso`
14. `core.curso_plan`
15. `core.curso_prerequisito`
16. `core.estudiante`
17. `core.docente`
18. `core.seccion`
19. `core.ambiente`
20. `core.linea_investigacion`
21. `core.docente_linea`

## `seguridad` — seguridad compartida

1. `seguridad.usuario`
2. `seguridad.modulo_sistema`

La autenticación Laravel y las tablas de Spatie permanecen en `public`.

## `comun` — utilidades compartidas

1. `comun.sector_economico`
2. `comun.empresa`
3. `comun.contacto_empresa`
4. `comun.archivo`
5. `comun.notificacion`
6. `comun.parametro_sistema`
7. `comun.dia_no_laborable`

## `prematricula` — Pre Matrícula

1. `prematricula.cronograma_prematricula`
2. `prematricula.solicitud_prematricula`
3. `prematricula.detalle_solicitud`

## `gestion_matriculas` — Gestión de Matrículas

1. `gestion_matriculas.cronograma_matricula`
2. `gestion_matriculas.limite_creditos`
3. `gestion_matriculas.matricula`
4. `gestion_matriculas.matricula_detalle`
5. `gestion_matriculas.componente_evaluacion`
6. `gestion_matriculas.nota`
7. `gestion_matriculas.asistencia`
8. `gestion_matriculas.acta_notas`
9. `gestion_matriculas.reserva_matricula`

## `cotecu` — COTECU

1. `cotecu.listas_cotejo`
2. `cotecu.lista_cotejo_items`
3. `cotecu.planes_curriculares`
4. `cotecu.revisiones_plan_curricular`
5. `cotecu.resultados_items`
6. `cotecu.listas_cotejo_silabos`
7. `cotecu.lista_cotejo_silabos_items`
8. `cotecu.silabos`
9. `cotecu.revisiones_silabo`
10. `cotecu.resultados_silabos_items`

## `gestion_horarios` — Gestión de Horarios

1. `gestion_horarios.oferta_curso`
2. `gestion_horarios.actividad_academica`
3. `gestion_horarios.asignacion_docente`
4. `gestion_horarios.bloque_horario`
5. `gestion_horarios.disponibilidad_docente`
6. `gestion_horarios.ambiente_bloqueo`
7. `gestion_horarios.horario`
8. `gestion_horarios.detalle_horario`
9. `gestion_horarios.restriccion`
10. `gestion_horarios.detalle_restriccion`
11. `gestion_horarios.horario_revision`
12. `gestion_horarios.apertura_repeticion`
13. `gestion_horarios.declaracion_disponibilidad`
14. `gestion_horarios.validacion_horario`
15. `gestion_horarios.conflicto_horario`

## `gestion_practicas` — Gestión de Prácticas

1. `gestion_practicas.empresa_usuario`
2. `gestion_practicas.convocatoria`
3. `gestion_practicas.observacion_convocatoria`
4. `gestion_practicas.practica`
5. `gestion_practicas.postulacion`
6. `gestion_practicas.seguimiento`
7. `gestion_practicas.documento_practica`
8. `gestion_practicas.observacion_practica`
9. `gestion_practicas.criterio_evaluacion`
10. `gestion_practicas.evaluacion`
11. `gestion_practicas.evaluacion_criterio`

## `calidad` — Calidad

1. `calidad.proceso`
2. `calidad.indicador`
3. `calidad.meta_indicador`
4. `calidad.medicion_indicador`
5. `calidad.modelo_acreditacion`
6. `calidad.dimension_acreditacion`
7. `calidad.estandar`
8. `calidad.indicador_estandar`
9. `calidad.evidencia`
10. `calidad.plan_mejora`
11. `calidad.accion_mejora`
12. `calidad.comite_calidad_miembro`

## `tesis` — Tesis y Proyecto de Tesis

1. `tesis.proyecto_tesis`
2. `tesis.proyecto_estudiante`
3. `tesis.tipo_solicitud`
4. `tesis.solicitud`
5. `tesis.resolucion`
6. `tesis.asesor_proyecto`
7. `tesis.jurado_proyecto`
8. `tesis.colaborador`
9. `tesis.actividad_cronograma`
10. `tesis.recurso`
11. `tesis.partida_presupuesto`
12. `tesis.entrega`
13. `tesis.reporte_similitud`
14. `tesis.rubrica`
15. `tesis.criterio_rubrica`
16. `tesis.revision`
17. `tesis.detalle_evaluacion`
18. `tesis.observacion`
19. `tesis.historial_estado`
20. `tesis.sustentacion`
21. `tesis.resultado_jurado`
22. `tesis.acta`

## Objetos relacionados que no son tablas

- Vista `gestion_matriculas.v_historial_academico`.
- Función `gestion_matriculas.calcular_creditos_matriculados`.
- Función `gestion_practicas.calcular_nota_final`.
- Función `calidad.calcular_valor_indicador`.
- Función `tesis.calcular_nota_sustentacion`.
- Funciones y triggers de validación del esquema `gestion_horarios`.

## Módulo pendiente

Bienestar no forma parte de este inventario activo. Sus migraciones legacy están en
`app/Modules/Bienestar/Database/MigrationsPending` y el runner modular no las ejecuta.
La migración principal disponible define 17 tablas en `public`, mientras la documentación
requiere 18 tablas dentro del esquema `bienestar`.

## Fuente del inventario

- `database/migrations`
- `app/Shared/Database/Migrations`
- `app/Modules/*/Database/Migrations`
- `config/modules.php`

Para verificar el estado aplicado en PostgreSQL:

```bash
php artisan module:migrate:status all
```
