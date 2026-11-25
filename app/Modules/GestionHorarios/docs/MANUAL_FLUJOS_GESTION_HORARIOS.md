# Manual de flujos y casos de comprobación — Gestión de Horarios

## Flujo adicional de integración local

Prematrícula abierta → demanda provisional sincronizada → cierre oficial de Prematrícula → consolidación de demanda efectiva y vacantes por curso → creación y verificación de secciones por aforo → asignación docente T/P/L → disponibilidad y restricciones → generación CP-SAT. El programador actualiza demanda, no autoriza escrituras de secciones ni asignaciones. Esta secuencia es obligatoria como requisito funcional; las compuertas de cierre y consolidación aún deben implementarse y probarse. Consulte [Cierre de Prematrícula y consolidación de vacantes](CIERRE_PREMATRICULA_CONSOLIDACION_VACANTES.md). Consulte [Planificación automática de secciones](PLANIFICACION_AUTOMATICA_SECCIONES.md) · [Ajuste de demanda](AJUSTE_DEMANDA_POST_PREMATRICULA.md) · [Prioridad docente](PRIORIDAD_DOCENTE_CPSAT.md).


Este documento permite al equipo de revisión recorrer las pantallas y verificar resultados esperados. **Los casos positivos que requieren adscripción deben ejecutarse solo cuando exista un proveedor oficial o un entorno de ensayo explícitamente aislado y ficticio.** No probar escrituras contra la base institucional.

| ID | Flujo | Condición previa | Pasos principales | Resultado esperado |
| --- | --- | --- | --- | --- |
| GH-01 | Portada pública | Sin sesión | Abrir `/gestion-horarios`. | Presentación pública sin navegación administrativa ni datos académicos privados. |
| GH-02 | Acceso administrativo denegado | Visitante o usuario sin rol | Abrir `/gestion-horarios/administracion`. | Denegación; no se muestra información reservada. |
| GH-03 | Rol sin adscripción | Director o secretaría de prueba, sin escuela acreditada | Abrir Oferta, Secciones, Revisiones y Horarios. | Acciones y consultas protegidas se bloquean o muestran aviso sin exponer datos. |
| GH-04 | Selección de periodo y plan | Identidad y escuela autorizadas en entorno ficticio | Abrir Oferta, seleccionar periodo y plan; cambiar plan y filtros. | Opciones limitadas al ámbito autorizado; se limpia el contexto anterior al cambiar plan. |
| GH-05 | Consulta de secciones | Mismo ámbito autorizado | Ir a Secciones, buscar curso o código y filtrar ciclo. | Solo se muestran registros del plan; no se asignan estudiantes. |
| GH-06 | Oferta y asociación | Permiso de gestión y escuela autorizada en una base desechable | Ensayar creación/edición de oferta y asociación; revocar permiso y repetir. | Escrituras válidas acotadas; revocación, otras escuelas y estados protegidos denegados. |
| GH-07 | Mi disponibilidad | Usuario con rol `profesor` sin vínculo docente verificable | Abrir `/gestion-horarios/mi-disponibilidad`. | Aviso seguro sin datos de terceros; una cuenta `alumno` debe ser denegada. |
| GH-08 | Preparación académica | Periodo, plan y fuentes ficticias controladas | Abrir Preparación, revisar faltantes y cambiar periodo. | Diagnóstico parcial; no se ejecuta motor ni se guarda horario institucional. |
| GH-09 | Consulta de horarios | Ámbito de lectura de prueba válido | Seleccionar periodo y versión; filtrar sección; observar sesiones simultáneas. | Vista de solo lectura; sesiones físicas diferentes no se pierden ni se fusionan indebidamente. |
| GH-10 | Descarga administrativa bloqueada | Rol sin contrato institucional de descarga | Intentar la descarga correspondiente. | Denegación; no se utiliza una prueba ficticia para abrir acceso global. |
| GH-11 | Creación institucional | Sesión administrativa | Intentar la ruta `/gestion-horarios/crear` y el guardado. | Respuesta HTTP 403; no se persisten horarios. |
| GH-12 | Metadatos publicados | Sesión pública | Consultar la ruta pública de horarios publicados. | Solo identificador, periodo, ámbito, versión y fecha de publicación para registros publicados; contrato público por aprobar. |
| GH-13 | Acceso temporal por rol | Cuentas ficticias de director, secretaria, profesor y alumno sin adscripción | Abrir la portada con cada rol y, para profesor y alumno, intentar `/gestion-horarios/mi-disponibilidad`. | Dirección y secretaría ven ingreso confirmado sin datos privados; profesor ve su espacio sin asignaciones si falta identidad; alumno no entra a la ruta docente. |
| GH-14 | Consulta semanal publicada | Usuario autenticado y datos publicados en un entorno aislado | Filtrar por ciclo/sección, ambiente y docente; cambiar periodo. | Solo actividades de versiones publicadas del periodo correcto, sin mostrar propuestas ni alterar datos. |

## Verificaciones de información y errores

**Ofertas y secciones:** una oferta cerrada o un horario aprobado/publicado debe respetar los bloqueos definidos. Una escuela autorizada no permite consultar recursos sin el contrato específico de recursos compartidos.

**Asignación docente:** consultar únicamente plan y periodo seleccionados; evitar presentar horas del plan como carga global del docente. Si falla el proveedor de identidad docente, no mostrar asignaciones.

**Disponibilidad:** distinguir (1) sin declaración, (2) declaración registrada sin horas y (3) declaración con franjas. La edición y la prioridad se han ensayado en un entorno ficticio con CP-SAT, no mediante una pantalla institucional habilitada.

**Demanda de Prematrícula:** las versiones iguales con contenido idéntico son duplicadas, las anteriores son antiguas y las iguales con diferente huella generan conflicto. La demanda agregada no asigna estudiantes a secciones.

**Motor y cola:** `OPTIMAL`, `FEASIBLE`, `INFEASIBLE` y `UNKNOWN` deben conservar su significado. Los trabajos reales de prueba utilizan escenarios ficticios; no deben ejecutarse desde una ruta pública ni persistir propuestas institucionales.

## Evidencia que debe guardar el revisor

- Perfil de usuario, ámbito de prueba, periodo y plan utilizados (sin contraseñas ni datos personales).
- Ruta y pasos reproducibles.
- Resultado esperado frente al observado, captura de pantalla si corresponde.
- Estado de acceso, filtro aplicado y mensaje de error.
- Confirmación de que la operación no modificó datos ajenos a su ámbito.

La revisión visual debe incluir escritorio, tableta y móvil; foco de teclado, etiquetas, tablas desplazables y actualización Livewire. Véase [Revisión visual en Windows](REVISION_VISUAL_WINDOWS.md).

## Alcance de las pruebas automatizadas

Los tests aislados de PostgreSQL y de Python/OR-Tools acreditan comportamientos concretos con datos ficticios, **no** la provisión institucional de escuelas, docentes, matrícula, recursos compartidos ni generación oficial. No ejecutar migraciones ni trabajadores sobre la base compartida del portal.

## Consulta institucional: alcance actual

Desde Gestión de Horarios, un usuario autenticado puede abrir «Horarios publicados», seleccionar una modalidad e introducir periodo y ciclo, ambiente o docente; para ciclo puede añadir sección. La consulta muestra exclusivamente actividades ligadas a versiones PUBLICADO con fecha de publicación y secciones del mismo periodo. La generación institucional y las consultas personales permanecen bloqueadas.

**Usabilidad pendiente:** reemplazar identificadores numéricos por selectores de periodos, ambientes y docentes obtenidos de catálogos autorizados. Hasta entonces, comprobar entradas erróneas y estados vacíos, sin exponer opciones fuera del ámbito del usuario.
