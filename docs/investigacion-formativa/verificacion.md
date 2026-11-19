# Verificación y límites de aceptación

Fecha: 18 de noviembre de 2026. Este registro distingue funciones verificadas, cobertura parcial y requisitos pendientes. No declara el módulo listo para producción.

## Documentación entregada

- [Flujos, preparación y recorrido de navegador](manual-flujos.md).
- [Arquitectura, datos, cálculos e integración](manual-arquitectura.md).
- [Instrucciones de usuario por actor](manual-usuario.md).
- [Roles, permisos y diagnóstico](roles-y-permisos.md).

## Evidencia ejecutada

| Comprobación | Resultado | Alcance |
|---|---|---|
| Suite completa sobre PostgreSQL 17.12 y PHP 8.4.27 | 266 aprobadas, 1 omitida, 1227 aserciones | Base exclusiva `portal_gestion_direccion_test_demo_suite_20261118133057`. La base incluye `_test_demo_`, por lo que el generador de demostración se ejecuta dentro de la suite. |
| Generador de demostración | 2 aprobadas, 28 aserciones | Topología, conservación de cuentas, contraseña local y cuatro CSV válidos en modo de validación. Incluidas en la suite completa. |
| Reportes | 18 aprobadas, 162 aserciones | Última versión, notas parciales, empates, redondeo, conteos, retiros, historial, consultas por lote, filtros por curso y línea, umbrales superpuestos, agregados de encuesta y estados vacíos. Incluidas en la suite completa. |
| Exportación a PDF | 8 aprobadas, 59 aserciones | Cabecera adjunta, tipo de contenido, datos de sección y filtros activos, generación con hora, tablas de calificaciones, distribuciones de encuesta, datos pendientes sin ceros inventados, exclusión de comentarios confidenciales, filtro de línea, ausencia de registros de archivo, rechazo de sección fuera del alcance y conservación del filtro en el botón. Incluidas en la suite completa. |
| Concurrencia exclusiva | 1 aprobada, 69 aserciones | Ejecutada por separado en `portal_gestion_direccion_test_concurrency_20261118133129`. La lógica concurrente del dominio no cambió después. |
| Decisiones de trabajo (Packet C) | 10 aprobadas, 90 aserciones | Aprobación explícita, rechazo sin las tres unidades evaluadas, reemplazo y retiro del trabajo destacado con historial, evidencia desactualizada, formulario con versión antigua, dos selecciones con la misma versión, denegaciones por docente ajeno y sección cerrada, y salida en el reporte del director. |
| Suite completa tras Packet C | 274 aprobadas, 3 omitidas, 1286 aserciones | Base exclusiva `portal_gestion_direccion_test_packetc_20261118134344`, migrada con `migrate` y luego `module:migrate`. Las tres omitidas son las pruebas que exigen una base `_test_demo_` o el indicador de concurrencia. |
| Suite del módulo tras Packet E | 162 aprobadas, 3 omitidas, 1061 aserciones | `tests/Feature/InvestigacionFormativa` en la base exclusiva `portal_gestion_direccion_test_packete_20261118162407`, migrada con `migrate` y luego `module:migrate`; 24 tablas del módulo y 3 tablas de seguridad. |
| Suite completa tras Packet E | 291 aprobadas, 3 omitidas, 1428 aserciones | Misma base exclusiva. El tablero de dirección y sus filtros por curso y línea quedan cubiertos por pruebas funcionales. |
| Chromium 157.0.8103.78 con ChromeDriver 157.0.8103.78 | 247 comprobaciones aprobadas | Formularios reales; persistencia tras recarga e inicio de sesión; 38 capturas de página en dos anchos, incluidos 390 px reales. |
| HTML de navegador | 38 páginas, 318 IDs, 132 etiquetas, 797 controles | Etiquetas cerradas, IDs únicos y referencias ARIA. Las tres coincidencias de texto `id` son enlaces de plantilla Alpine `:id`, no IDs renderizados. |
| JavaScript incluido | 79 scripts con sintaxis válida | Extraídos del HTML capturado y revisados con `node --check`; sin errores y sin `getElementById` sin destino. |
| Compilación y formato | Vite, Pint y sintaxis PHP aprobados | Solo archivos PHP nuevos; sin cambios de formato en otros módulos. |
| Cachés y estructura | Blade, rutas y prueba SQL aprobados | Las cachés se limpiaron tras verificar; `modular_database_smoke: OK`. |
| Manuales | Ortografía, enlaces y referencias revisados | Cinco documentos, cuatro capturas y diez enlaces locales comprobados. |
| PDF de muestra | Una página A4, 601 bytes | `pdfinfo`, extracción de texto y hash coincidente. No es evidencia de una carga. |

La prueba omitida en la suite requiere una base exclusiva de concurrencia. Su resultado separado se indica sin sumarlo a la suite. Las comprobaciones de ancho verifican ausencia de desplazamiento horizontal de toda la página a 1280 y 390 píxeles. No equivalen a una auditoría completa de accesibilidad, lectores de pantalla o todos los navegadores. La ficha de grupo incluye una recarga explícita; los formularios de configuración se vuelven a abrir mediante peticiones nuevas y cambio de sesión. El recorrido resuelve los clics con consultas DOM nuevas, de modo que una actualización de Alpine no deja una referencia de elemento obsoleta.

La última ejecución recorre una misma sección desde su habilitación y configuración hasta la creación del grupo, solicitud de cambios, corrección del coordinador, aceptación, consulta del integrante, encuesta y resultados. La base conserva las cuatro revisiones del título: `Registrado → Cambio solicitado → Registrado → Aceptado`, con versión final 4 y el motivo original. La corrección persiste después de volver a iniciar sesión. El integrante consulta el contenido y el historial sin controles para editar el título.

Después, la secretaría prepara las preguntas, guarda el borrador y publica la encuesta de la sección `incompleta`; no puede cerrarla. Un integrante responde con comentario, la dirección ve el agregado `4.00` y los comentarios confidenciales sin el nombre del estudiante, y cierra la encuesta.

Esta ampliación de la encuesta por secretaría modificó el recorrido de navegador y su documentación. En esa ejecución, la lógica de dominio cambió solo en el filtro de línea de investigación de la lista de grupos; el resto de los cambios fueron pruebas, configuración y documentación. Los resultados de la suite y del navegador indicados arriba corresponden a ejecuciones del 18 de noviembre de 2026. Las decisiones de aprobación y trabajo destacado, y el tablero descriptivo de dirección, se verificaron con pruebas funcionales; su recorrido de navegador se completa en la etapa de aceptación final.

## Trazabilidad de las 14 pantallas

Las páginas de origen corresponden al informe de interfaces. Las rutas Blade son relativas a `resources/views/modules/investigacion-formativa/`.

| Origen | Pantalla | Implementación | Evidencia y estado |
|---|---|---|---|
| Página 3 | Habilitar cursos | `director/cursos.blade.php`; `DirectorController::habilitacion` | Pruebas de configuración aprobadas; habilitación, deshabilitación y persistencia comprobadas en Firefox. |
| Página 4 | Diseñar encuesta | `director/encuesta.blade.php`; `EncuestaService` | Guardar y publicar mediante formularios reales; cierre mediante formulario real y pruebas de conflictos aprobadas. |
| Página 5 | Resultados | `director/resultados.blade.php`; `ReporteService::porSeccion`; `DecisionTrabajoService::estadoDeSeccion` | Encuesta en Firefox; calificaciones y empates con datos independientes de prueba. Filtro por línea, conservación de filtros en paginación y enlaces, y trabajo destacado de la sección verificados en pruebas funcionales. |
| Página 6 | Resumen e historial | `director/index.blade.php`; `ReporteService::resumenDirector` | Periodos reales y sección cerrada en Firefox; filtros y consultas por lote en pruebas. Filtros por curso y línea, tarjetas descriptivas, umbrales superpuestos, tablas equivalentes y conteos de decisiones verificados en pruebas funcionales. |
| Página 7 | Cursos del docente | `docente/cursos.blade.php` | Firefox y pruebas de asignación. |
| Página 8 | Competencias | `docente/competencias.blade.php`; `ConfiguracionService::guardarCompetencias` | Guardado, lectura posterior y cambio de sesión verificados en Firefox y pruebas funcionales. |
| Página 9 | Rúbrica | `docente/rubrica.blade.php`; `ConfiguracionService::guardarRubrica` | Guardado de los cuatro niveles y persistencia en Firefox; validaciones en pruebas funcionales. |
| Página 10 | Cronograma | `docente/cronograma.blade.php`; `ConfiguracionService::guardarCronograma` | Guardado, persistencia en hora de Lima y rechazo de fechas fuera de orden en Firefox y pruebas funcionales. |
| Página 11 | Grupos del docente | `docente/grupos.blade.php` | Firefox y pruebas de pertenencia. |
| Página 12 | Revisar grupo y evaluar | `docente/grupo.blade.php`; `GrupoService::revisarTitulo`; `EvaluacionService::guardar`; `DecisionTrabajoService` | Solicitud de cambio, motivo, corrección del coordinador y aceptación final verificados en Firefox. Evaluación con entregas de prueba aprobada; recorrido real de entrega/evaluación pendiente de G4. Aprobación final, selección manual del trabajo destacado, reemplazo, retiro e indicador de evidencia desactualizada verificados en pruebas funcionales. |
| Página 13 | Cursos del estudiante | `alumno/cursos.blade.php` | Firefox y pruebas de configuración incompleta. |
| Página 14 | Crear grupo | `alumno/grupo-crear.blade.php`; `GrupoService::crear` | Creación, validación de campos y persistencia en Firefox; atomicidad en pruebas. |
| Página 15 | Trabajo del estudiante | `alumno/grupo.blade.php` | Título corregido, historial y ausencia de controles de edición para el integrante verificados en Firefox. Carga y descarga pendientes; pantalla parcialmente cubierta. Los indicadores de trabajo aprobado y destacado se verifican en pruebas funcionales. |
| Página 16 | Encuesta individual | `alumno/encuesta.blade.php`; `EncuestaService::responder` | Envío y persistencia en Firefox; validaciones, duplicados y transacción en pruebas. |

## Trazabilidad de reglas

Los códigos B01–B24 identifican los requisitos y decisiones de implementación. «Verificada» significa comprobada en el alcance indicado, no aprobada como regla académica. Los nombres de prueba se resuelven dentro de `tests/Feature/InvestigacionFormativa/`.

| Regla | Implementación | Evidencia | Estado / acción pendiente |
|---|---|---|---|
| B01: habilitación por sección | `ConfiguracionService::cambiarHabilitacion` | `ConfiguracionSeccionTest::test_director_habilita_seccion_y_la_recarga_muestra_habilitada` | Verificada. |
| B02: detener escrituras deshabilitadas | Servicios de configuración, evaluación y encuesta | `test_una_seccion_deshabilitada_rechaza_cambios_del_docente`; `test_las_respuestas_enviadas_siguen_visibles_tras_deshabilitar_la_seccion` | Verificada para las operaciones implementadas. Archivos pendientes. |
| B03: configuración completa | `ConfiguracionService::estaConfigurada` | `test_completitud_y_version_compartida_entre_los_pasos` | Verificada. |
| B04: creador coordinador | `GrupoService::crear` | `GruposTitulosTest::test_la_creacion_de_grupo_guarda_miembros_proyecto_y_historial` | Verificada. |
| B05: grupo único y coordinador único | Servicio y restricciones de grupo | `test_un_integrante_no_puede_formar_un_segundo_grupo`; prueba de concurrencia | Verificada. |
| B06: membresía fija | Rutas y `GrupoService` | Inventario de rutas y creación de grupo | No hay acciones de incorporación, transferencia ni eliminación. Decisión local propuesta. |
| B07: título por coordinador | `ProyectoPolicy::update`; `GrupoService::actualizarTitulo` | `test_un_integrante_no_coordinador_no_puede_editar_el_titulo` | Verificada. |
| B08: nueva revisión tras editar | `GrupoService::actualizarTitulo` | `test_el_coordinador_puede_editar_y_el_docente_acepta_el_titulo` | Verificada. |
| B09: tres estados de título | `GrupoService::revisarTitulo` | `test_el_docente_responsable_acepta_y_solicita_cambios` | Verificada. |
| B10: título fijo tras entrega | `GrupoService::motivoBloqueoTitulo` | `test_el_titulo_no_se_rea_bre_despues_de_una_entrega` | Verificada con entrega de prueba; carga real pendiente. |
| B11: PDF, tamaño y coordinador | Contrato de archivo propuesto | PDF de muestra y políticas previas | Pendiente de G4. Falta servicio y ruta de carga. |
| B12: ventanas de tres avances | Contrato propuesto | Cronograma persistido | Pendiente de G4 y del servicio de entregas. Guardar fechas no verifica ventanas de carga. |
| B13: fechas crecientes y Lima | `ConfiguracionService::guardarCronograma` | `test_cronograma_guarda_tres_fechas_en_hora_lima_y_no_duplica_entregables` | Verificada. |
| B14: versiones inmutables de entrega | Esquema y lectura de versiones | `EvaluacionesTest::test_la_version_anterior_conserva_su_nota_y_la_mas_reciente_queda_pendiente` | Lectura verificada; creación y ciclo de archivos pendientes. |
| B15: criterios y nivel por versión | `EvaluacionService::guardar` | `test_exige_criterios_exactos_niveles_validos_y_observaciones_explicitas` | Verificada con datos de prueba. |
| B16: última versión vigente | `EvaluacionService`; `ReporteService` | `ReportesTest::test_la_version_nueva_sin_evaluar_no_reutiliza_la_nota_anterior` | Verificada. |
| B17: congelación y extensión | `ConfiguracionService` | `test_rubrica_se_congela_cuando_existen_entregas`; `test_el_cronograma_solo_se_extiende_despues_de_una_entrega` | Verificada con datos de prueba. |
| B18: publicar y cerrar sin reabrir | `EncuestaService` | `test_la_publicacion_exige_preguntas_guardadas_y_luego_fija_el_contenido`; `test_una_encuesta_se_cierra_sin_reabrir_y_el_mensaje_bloquea_nuevas_respuestas` | Verificada. |
| B19: respuesta individual única | `EncuestaService::responder` | `test_el_estudiante_ve_las_preguntas_y_guarda_una_sola_respuesta` | Verificada. |
| B20: confidencialidad | Vistas de encuesta y resultados | `test_pagina_comentarios_sin_identidades_y_conserva_filtros_y_tamano`; comprobación de reporte en Firefox | Verificada para la salida mostrada. Identidad almacenada; no se promete anonimato. |
| B21: resultados reales y vacíos | `ReporteService` | `test_muestra_estados_vacios_sin_notas_inventadas`; `test_incluye_todos_los_empates_y_excluye_el_proyecto_incompleto` | Verificada. |
| B22: periodo y sección reales | `ReporteService::resumenDirector` | `test_el_historial_usa_periodos_reales_e_incluye_secciones_cerradas` | Verificada. |
| B23: línea y tipo de producto | Formulario y `GrupoService::crear` | Creación en Firefox y prueba de creación de grupo | Verificada. |
| B24: no cambiar notas de matrícula | Servicios y esquema propio | Pruebas de aislamiento de esquema y revisión de escrituras | No se escriben notas de matrícula. |
| Cálculo y clasificación | `CalificacionService`; `ReporteService` | `test_un_proyecto_completo_ocupa_ambos_extremos`; `test_el_promedio_del_curso_redondea_medios_hacia_arriba` | Verificada con expectativas numéricas independientes. |
| Conteos y denominadores | `ReporteService` | `test_cuenta_cabeceras_y_calcula_la_media_por_pregunta`; `test_los_retiros_cambian_el_denominador_sin_limitar_la_tasa` | Verificada. |
| Tablero descriptivo de dirección | `ReporteService::resumenDirector`; `ReporteService::porSeccion`; `director/index.blade.php` | `test_agrupa_proyectos_por_linea_y_separa_completos_de_pendientes`; `test_los_umbrales_de_nota_son_estrictos_y_superpuestos`; `test_filtra_por_curso_y_linea_y_conserva_los_filtros_en_los_enlaces`; `test_rechaza_curso_y_linea_fuera_del_alcance`; `test_los_resultados_por_seccion_filtran_por_linea_y_lo_avisan` | Verificada como estadística descriptiva. Los umbrales se superponen; la encuesta no se desagrega por línea. No son los indicadores oficiales de Calidad. |
| Aprobación final y trabajo destacado | `DecisionTrabajoService`; `DecisionController` | `DecisionesTrabajoTest::test_la_aprobacion_final_exige_las_tres_unidades_evaluadas_y_registra_evidencia`; `test_la_seleccion_manual_reemplaza_al_anterior_y_conserva_el_historial`; `test_la_evidencia_cambiada_marca_las_decisiones_desactualizadas` | Verificada con datos de prueba. Son decisiones explícitas del docente; no se deducen de la nota. |

## Pendientes que impiden la aceptación completa

1. G4: aprobar e implementar el contrato del archivo compartido. Completar carga, descarga, versiones, fallos y auditoría de archivos sin escrituras directas desde este módulo.
2. Ejecutar el recorrido de tres entregas y evaluación en navegador con esa integración real. El generador de demostración no inserta entregas falsas para aparentarlo.
3. Confirmar G1, G2, G3 y G5 con los responsables de identidad, matrícula, contenido académico y asignaciones.
4. Validar las decisiones académicas propuestas, incluidas membresía fija, ventanas, congelación, versión vigente y promedio sin ponderación.
5. Construir y comprobar la imagen de producción en un entorno de contenedores configurado. Se intentó con Podman temporal; falló antes de ejecutar el Dockerfile porque falta `policy.json` en el host. No se cambió la configuración del sistema.
6. Realizar la revisión de seguridad dedicada que fue diferida. Se conservaron los controles existentes; las pruebas funcionales no sustituyen esa revisión.

P09 tiene demostración y recorrido parcial verificados; su aceptación completa sigue bloqueada por G4. P10 tiene los cuatro manuales y el registro de regresión; la aceptación final conserva los bloqueos anteriores.
