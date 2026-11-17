# Manual de flujos · casos de prueba

Módulo **Tesis y Proyecto de Tesis** (`/tesis-proyecto-tesis`). Cada flujo está escrito como un caso de prueba que se puede repetir. Indica qué demuestra, qué cuentas y escenarios usa, cómo se preparan los datos, los pasos, el resultado esperado y la prueba automática que lo cubre.

> Solo para los entornos `local` y `testing`. Los datos de ejemplo crean cuentas `*@portal.local` y nunca deben cargarse en producción.

## Contenido

- [Preparación](#preparación)
- [Cuentas de prueba](#cuentas-de-prueba)
- [Escenarios PT-DEMO](#escenarios-pt-demo)
- Flujos principales
  - [F-01 · Etapa 1 completa: del registro al paso a Tesis](#f-01--etapa-1-completa-del-registro-al-paso-a-tesis)
  - [F-02 · Etapa 2 completa: de la tesis a «Tesis aprobada»](#f-02--etapa-2-completa-de-la-tesis-a-tesis-aprobada)
  - [F-03 · Avance con observaciones → respuesta → levantada](#f-03--avance-con-observaciones--respuesta--levantada)
  - [F-04 · Rúbrica con «no cumple»](#f-04--rúbrica-con-no-cumple)
  - [F-05 · Turnitin mayor a 21 %](#f-05--turnitin-mayor-a-21-)
  - [F-06 · Asignación de jurado con validaciones](#f-06--asignación-de-jurado-con-validaciones)
  - [F-07 · Revisión intermedia referencial](#f-07--revisión-intermedia-referencial)
- Sustentación
  - [F-08 · Sustentación aprobada por unanimidad](#f-08--sustentación-aprobada-por-unanimidad)
  - [F-09 · Sustentación aprobada por mayoría](#f-09--sustentación-aprobada-por-mayoría)
  - [F-10 · Sustentación desaprobada y nueva fecha](#f-10--sustentación-desaprobada-y-nueva-fecha)
  - [F-11 · Mención honrosa](#f-11--mención-honrosa)
- Notificaciones y accesos
  - [F-12 · Notificaciones que llevan a la acción](#f-12--notificaciones-que-llevan-a-la-acción)
  - [F-13 · Alumno intenta abrir un proyecto ajeno](#f-13--alumno-intenta-abrir-un-proyecto-ajeno)
  - [F-14 · Jurado o profesor de otro proyecto](#f-14--jurado-o-profesor-de-otro-proyecto)
  - [F-15 · Id numérico en la URL y rol equivocado](#f-15--id-numérico-en-la-url-y-rol-equivocado)
- [Pruebas automáticas](#pruebas-automáticas)

---

## Preparación

1. Levanta el portal y aplica las migraciones del módulo:

   ```bash
   docker compose up -d --build app
   docker compose exec app php artisan module:migrate tesis-proyecto-tesis
   ```

   > **Nota:** si `docker compose up -d --build app` termina con `failed to execute bake`, construye la imagen aparte y luego levanta el servicio:
   >
   > ```bash
   > docker build -t portal-gestion-direccion:latest .
   > docker compose up -d app
   > ```

2. Siembra los escenarios **dentro** del contenedor `app` y como `www-data`, para que los PDF de ejemplo queden en el disco del portal. El seeder también siembra la rúbrica, los tipos de solicitud y las cuentas demo.

   ```bash
   docker compose exec -u www-data -e APP_ENV=local app php artisan db:seed --force \
     --class="App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder"
   ```

3. Entra en `http://localhost:8000/login`. Todas las cuentas usan la contraseña demo `Portal2026!`.

**Reglas para repetir los casos**

- El seeder es **idempotente**. Cada vez que se ejecuta:
  - borra los proyectos `PT-DEMO-*` y los proyectos de ensayo de las cuentas de los escenarios (`nuevo@`, `est01…11@`), con sus PDF y avisos; por ejemplo, el `PT-2026-002` que registró `nuevo@` durante un ensayo;
  - vuelve a crear los `PT-DEMO-*`, así `nuevo@` queda otra vez sin proyecto;
  - no toca los proyectos de las demás cuentas, como el `PT-2026-001` de `alumno@`.
- Vuelve a sembrar **antes de cada caso** que cambie el estado de un escenario. Por ejemplo, después de F-04 el escenario PT-DEMO-05 ya no sirve para F-05.
- Las fechas de las sustentaciones son relativas al día de la siembra: PT-DEMO-10 está programada **hoy** a las 09:00 y PT-DEMO-11 fue **ayer**. Si cambias de día, vuelve a sembrar.
- Los tres jurados demo integran todos los jurados PT-DEMO. Por eso, al programar una sustentación de prueba **hoy**, evita el rango de 07:00 a 11:00, que choca con PT-DEMO-10. Usa, por ejemplo, las 15:00.
- Si reconstruyes la imagen `app`, los PDF se pierden y las descargas dan 404 (ver [Limitaciones conocidas](MANUAL_ARQUITECTURA.md#limitaciones-conocidas)). Vuelve a sembrar.

## Cuentas de prueba

| Cuenta | Rol en el portal | Papel en el módulo |
|---|---|---|
| `director@portal.local` | director_escuela | Director de Escuela |
| `secretaria@portal.local` | secretaria | Secretaría de la Escuela |
| `asesor@portal.local` | profesor | Asesor de todos los PT-DEMO |
| `jurado1@portal.local` | profesor | Presidente del jurado en los PT-DEMO |
| `jurado2@portal.local` | profesor | Secretario del jurado en los PT-DEMO |
| `jurado3@portal.local` | profesor | Vocal del jurado en los PT-DEMO |
| `profesor@portal.local` | profesor | Docente sin asignaciones |
| `nuevo@portal.local` | alumno | Estudiante sin proyecto |
| `est01@` … `est11@portal.local` | alumno | Autor del escenario PT-DEMO-01 … PT-DEMO-11 |
| `alumno3@portal.local` | alumno | Coautor de PT-DEMO-03 |

### Docentes: cómo aparecen en las listas

Las listas de asesor (resolución de Secretaría) y de jurado (Director) muestran a cada docente como **grado + nombre completo**, sin el código, y en orden alfabético.

| Cuenta | Código | Nombre en las listas | Elegir en la demo como |
|---|---|---|---|
| `asesor@portal.local` | `DOC-101` | Mg. Carlos Medina Ruiz | Asesor (no aparece en la lista del jurado de sus proyectos) |
| `jurado1@portal.local` | `DOC-102` | Dra. Ana Paredes Luna | Presidente |
| `jurado2@portal.local` | `DOC-103` | Ms. Jorge Castillo Ríos | Secretario |
| `jurado3@portal.local` | `DOC-104` | Dra. Rosa Huamán Díaz | Vocal |
| `profesor@portal.local` | `DOC-105` | Dra. Elena Vargas Soto | Reemplazo de un jurado (F-06) o docente ajeno (F-14) |

## Escenarios PT-DEMO

| Escenario | Estado | Acción pendiente | Quién actúa |
|---|---|---|---|
| PT-DEMO-01 | Registrado | Solicitar el asesor al Decanato | director@ |
| PT-DEMO-02 | Registrado (solicitud en el Decanato) | Registrar la resolución de asesor | secretaria@ |
| PT-DEMO-03 | En desarrollo | Revisar el avance v2 | asesor@ |
| PT-DEMO-04 | En desarrollo | Responder 2 observaciones (est04@) y levantar 1 ya respondida (asesor@) | est04@, asesor@ |
| PT-DEMO-05 | En desarrollo (versión final v4) | Verificar con la rúbrica y pasar Turnitin | asesor@ |
| PT-DEMO-06 | Verificado por el asesor | Asignar el jurado | director@ |
| PT-DEMO-07 | En revisión del jurado | Presidente conforme; Secretario y Vocal verifican | jurado2@, jurado3@ |
| PT-DEMO-08 | Tesis en desarrollo (semana ≥ 8) | Revisar el avance v2 (asesor) y hacer la revisión intermedia (jurado) | asesor@, jurado1-3@ |
| PT-DEMO-09 | Listo para sustentar | Programar la sustentación | secretaria@ |
| PT-DEMO-10 | Sustentación programada (hoy, 09:00, LAB-8) | Presidente ya registró «Aprobatorio»; faltan Secretario y Vocal | jurado2@, jurado3@ |
| PT-DEMO-11 | Sustentado (unanimidad, acta ACT-DEMO-11) | Subir el acta firmada e informar al Director | secretaria@ |

---

## F-01 · Etapa 1 completa: del registro al paso a Tesis

| | |
|---|---|
| **Objetivo** | Recorrer la etapa Proyecto (F1) de punta a punta: registro, designación del asesor, avances, rúbrica, Turnitin, jurado y dictamen. |
| **Cuentas** | nuevo@, director@, secretaria@, asesor@, jurado1@, jurado2@, jurado3@ |
| **Escenario** | Ninguno (proyecto nuevo). Para empezar a mitad del camino: PT-DEMO-01 (paso 2), PT-DEMO-02 (paso 3), PT-DEMO-05 (paso 7), PT-DEMO-06 (paso 9) o PT-DEMO-07 (paso 10). |
| **Datos** | Siembra los escenarios. Ten a mano cualquier PDF de menos de 10 MB. |

**Pasos**

1. **nuevo@** → Inicio → **Registrar proyecto (F1)**.
   - Completa el título (20 palabras como máximo), la línea, los datos de la investigación y al menos una etapa del cronograma.
   - Adjunta el PDF, deja el coautor vacío y pulsa **Registrar proyecto**.
2. **director@** → Panel general → «Requieren tu acción» → **Solicitar** en el proyecto nuevo.
   - El docente sugerido es opcional.
   - Pulsa **Enviar solicitud al Decanato**.
3. **secretaria@** → Resoluciones → elige la solicitud en «Solicitudes esperando resolución».
   - Completa el N.° de resolución, la fecha (hoy o anterior), el órgano emisor y «Dirigida a».
   - En «Docente designado como asesor» elige **Mg. Carlos Medina Ruiz** (`asesor@`).
   - Completa el asunto, adjunta el PDF y pulsa **Registrar resolución**.
4. **nuevo@** → Avances → semana 1, «Avance semanal o corrección», PDF → **Enviar entrega**.
5. **asesor@** → Mis proyectos → **Revisar avance** → «Sin observaciones» → **Enviar revisión**.
6. **nuevo@** → Avances → «Versión final (para verificación con la rúbrica)» → **Enviar entrega**.
7. **asesor@** → Mis proyectos → **Verificar con rúbrica**.
   - Pulsa **Marcar los pendientes como «cumple»** y luego **Dar conformidad**.
8. **asesor@** → **Continuar con Turnitin y conformidad**.
   - Adjunta el PDF del reporte y escribe `14` en «Similitud (%)».
   - Marca la conformidad y pulsa **Dar conformidad y enviar**.
9. **director@** → Panel general → **Asignar**.
   - Presidente **Dra. Ana Paredes Luna**, Secretario **Ms. Jorge Castillo Ríos**, Vocal **Dra. Rosa Huamán Díaz**.
   - Pulsa **Asignar jurado y notificar**.
10. **jurado1@**, **jurado2@** y **jurado3@**: cada uno abre Proyectos asignados → **Revisar con la rúbrica**, marca los criterios como cumple y pulsa **Dar conformidad**.

**Resultado esperado**

| Después del paso | Estado del proyecto | Además |
|---|---|---|
| 1 | Registrado | Código `PT-AAAA-NNN`. Aviso «Nuevo proyecto …» al Director. |
| 2 | Registrado | La solicitud queda pendiente; aviso a la Secretaría. |
| 3 | Con asesor | Aviso «Fuiste designado asesor · …» al asesor. El estudiante ve a su asesor y el N.° de resolución. |
| 4 | En desarrollo | Aviso «Nueva entrega · … v1» al asesor. |
| 7 | En desarrollo | Rúbrica de **18 criterios** (etapa Proyecto). La versión final pasa a «Culminada». |
| 8 | Verificado por el asesor | Aviso «Conformidad del asesor · …» al Director. |
| 9 | En revisión del jurado | Aviso «Fuiste asignado como Presidente/Secretario/Vocal del jurado · …» a cada miembro. |
| 10 (tercer jurado) | **Tesis en desarrollo**, etapa **Tesis** | Se genera el dictamen `DIC-AAAA-NNN`, visible en Secretaría → Actas y dictámenes. El historial registra «Proyecto aprobado» y «Tesis en desarrollo». |

**Pruebas automáticas:** `FlujoCompletoTest::test_recorrido_completo_de_las_dos_etapas`, `RegistroProyectoTest::test_registro_individual_sin_coautor`, `DesignacionAsesorTest::test_flujo_solicitud_resolucion_asesor_activo_y_notificado`, `VerificacionAsesorTest::test_conformidad_completa_y_turnitin_apto_pasa_a_verificado`, `JuradoDictamenTest::test_tres_conformes_generan_dictamen_y_pasan_a_tesis`.

---

## F-02 · Etapa 2 completa: de la tesis a «Tesis aprobada»

| | |
|---|---|
| **Objetivo** | Recorrer la etapa Tesis (F2): versión final, rúbrica de 25 criterios, Turnitin, revisión final del jurado, sustentación, acta e informe al Director. |
| **Cuentas** | est08@, asesor@, jurado1-3@, secretaria@ |
| **Escenario** | PT-DEMO-08. Para empezar en la sustentación: PT-DEMO-09 (paso 5). |
| **Datos** | Siembra los escenarios. |

**Pasos**

1. *(Opcional)* **asesor@** → Mis proyectos → PT-DEMO-08 → **Revisar avance** → «Sin observaciones» → **Enviar revisión**.
2. **est08@** → Avances → «Versión final (para verificación con la rúbrica)» → PDF → **Enviar entrega**.
3. **asesor@** → **Verificar con rúbrica**: aparecen **25 criterios**. Marca todos como cumple y pulsa **Dar conformidad**.
4. **asesor@** → **Continuar con Turnitin y conformidad** → `12` %, PDF y conformidad → **Dar conformidad y enviar**.
5. **jurado1@**, **jurado2@**, **jurado3@** → Proyectos asignados → **Revisión final** → todo cumple → **Dar conformidad**.
6. **secretaria@** → Sustentaciones → «Por programar» → **Programar**.
   - Fecha de hoy, hora `15:00`, aula `INFO-5`.
   - Pulsa **Programar y notificar**.
7. **jurado1@**, **jurado2@**, **jurado3@** → Sustentaciones → **Registrar resultado**.
   - Elige «Aprobatorio» y una hora de término.
   - Pulsa **Registrar mi resultado**.
8. **secretaria@** → Actas y dictámenes → **Abrir** el acta.
   - Pulsa **Imprimir**, adjunta el escaneo y pulsa **Subir documento firmado**.
   - Pulsa **Informar al Director**.

**Resultado esperado**

| Después del paso | Estado |
|---|---|
| 4 | **En revisión del jurado**: en la etapa Tesis no se vuelve a pasar por el Director; el mismo jurado recibe «Tesis por revisar · …». |
| 5 (tercer jurado) | **Listo para sustentar**; aviso «Programar sustentación · …» a la Secretaría. |
| 6 | **Sustentación programada**; aviso a jurados, asesor y estudiante con fecha, hora y aula. |
| 7 (tercer jurado) | **Sustentado**; se genera el acta `ACT-AAAA-NNN` «Aprobado por unanimidad», condición Expedito. |
| 8 | **Tesis aprobada**; el panel de est08@ muestra los 8 pasos completos. |

**Pruebas automáticas:** `FlujoCompletoTest::test_recorrido_completo_de_las_dos_etapas`, `VerificacionAsesorTest::test_rubrica_del_proyecto_tiene_18_criterios_y_de_tesis_25`, `JuradoDictamenTest::test_asesor_en_tesis_envia_directo_a_revision_final_del_mismo_jurado`, `JuradoDictamenTest::test_revision_final_de_tesis_con_tres_conformes_queda_lista_para_sustentar`, `SustentacionActaTest::test_programacion_valida_notifica_y_cambia_estado`, `SustentacionActaTest::test_aprobado_por_mayoria_genera_acta_y_al_informar_cierra_el_proceso`.

---

## F-03 · Avance con observaciones → respuesta → levantada

| | |
|---|---|
| **Objetivo** | Comprobar el ciclo semanal: el asesor observa, el estudiante responde indicando dónde corrigió y el asesor da la observación por levantada. |
| **Cuentas** | asesor@, est04@ (y est03@ para la primera parte) |
| **Escenario** | PT-DEMO-03 (observar) y PT-DEMO-04 (responder y levantar). |
| **Datos** | Siembra los escenarios. |

**Pasos**

1. **asesor@** → Mis proyectos → PT-DEMO-03 → **Revisar avance**.
   - Pulsa **+ Agregar observación**, elige la sección y escribe la observación.
   - Mientras haya observaciones nuevas, «Sin observaciones» y «Proyecto culminado» quedan desactivados.
   - Elige «Con observaciones» y pulsa **Enviar revisión**.
2. **est03@** → Observaciones: la observación aparece en «Pendientes».
3. **est04@** → Observaciones → «Pendientes (2)».
   - En la primera, escribe la respuesta y en «Corregida en» elige la **v2**.
   - Pulsa **Enviar respuesta**.
4. **est04@** → pestaña «Respondidas»: ahora hay 2. Todavía puede **Actualizar respuesta** mientras no esté levantada.
5. **asesor@** → Mis proyectos → PT-DEMO-04 → «Observaciones del proyecto» → **Dar por levantada** en una observación respondida.

**Resultado esperado**

- Paso 1: la entrega v2 de PT-DEMO-03 queda «Observada». Los estudiantes reciben el aviso «El asesor revisó tu entrega v2».
- Paso 3: el asesor recibe el aviso «Observación respondida · PT-DEMO-04».
- Paso 5: la observación pasa a «Levantadas». El estudiante recibe «Se levantó una observación».
- Una observación **Pendiente** no se puede levantar: «Solo se levantan observaciones que el estudiante ya respondió.»
- Solo quien registró la observación puede levantarla: «Solo el revisor que registró la observación puede levantarla.»
- «Corregida en» solo acepta una versión posterior a la observada: «Elige una entrega de tu proyecto posterior a la observada.»

**Pruebas automáticas:** `AvancesRevisionTest::test_ciclo_completo_avance_observacion_respuesta_levantada`, `test_reglas_del_dictamen_y_doble_revision`, `test_solo_se_levanta_lo_respondido_y_por_su_autor`, `test_validaciones_de_entrega_y_respuesta`.

---

## F-04 · Rúbrica con «no cumple»

| | |
|---|---|
| **Objetivo** | Un criterio en «no cumple» exige observación y bloquea la conformidad. |
| **Cuentas** | asesor@, est05@ |
| **Escenario** | PT-DEMO-05 (versión final v4). |
| **Datos** | Siembra los escenarios. Vuelve a sembrar al terminar. |

**Pasos**

1. **asesor@** → Mis proyectos → PT-DEMO-05 → **Verificar con rúbrica**.
2. Marca el criterio 3 como **✗ No cumple**.
   - Se abre el campo de observación.
   - **Dar conformidad** queda desactivado y la barra inferior dice «Aún no puedes dar tu conformidad».
3. Intenta **Guardar y enviar observaciones** sin escribir la observación: el botón está desactivado. El servidor también lo rechaza con «El criterio 3 está en «no cumple»: registra su observación.»
4. Marca los demás criterios con **Marcar los pendientes como «cumple»**, escribe la observación y pulsa **Guardar y enviar observaciones**.
5. Abre **Turnitin y conformidad**.
6. **est05@** → Observaciones.

**Resultado esperado**

- Paso 4: «Verificación registrada» con el dictamen «Observado». La versión v4 queda «Observada» y el estudiante recibe «Tu asesor verificó la versión v4 con la rúbrica».
- Paso 5: no se puede registrar Turnitin: «Para registrar Turnitin, la versión vigente debe estar verificada con la rúbrica (conforme) y sin observaciones abiertas.»
- Paso 6: la observación del criterio 3 aparece en «Pendientes».
- Una segunda verificación de la misma versión se rechaza: «Ya registraste tu verificación de la versión v4. Espera la nueva versión del estudiante.»
- «No aplica» solo existe en el criterio 6 (hipótesis) y cuenta como cumplido.

**Pruebas automáticas:** `VerificacionAsesorTest::test_criterios_no_cumple_exigen_observacion_y_bloquean_conformidad`, `test_marcas_incompletas_o_invalidas`, `test_sin_version_final_no_hay_verificacion`.

---

## F-05 · Turnitin mayor a 21 %

| | |
|---|---|
| **Objetivo** | Con una similitud mayor al 21 % el proyecto no avanza y vuelve a los estudiantes. |
| **Cuentas** | asesor@, est05@ |
| **Escenario** | PT-DEMO-05 recién sembrado. |
| **Datos** | Siembra los escenarios. |

**Pasos**

1. **asesor@** → PT-DEMO-05 → **Verificar con rúbrica** → **Marcar los pendientes como «cumple»** → **Dar conformidad**.
2. **Continuar con Turnitin y conformidad** → adjunta el PDF y escribe `25` en «Similitud (%)».
   - Aparece el aviso «Si la similitud supera el 21 %, el documento vuelve a los estudiantes para corregir.»
   - El botón cambia a **Registrar reporte y devolver a los estudiantes**.
3. Pulsa el botón.
4. **est05@** → abre la campana y luego Avances.

**Resultado esperado**

- El reporte queda «No apto» y la versión v4 queda «Observada».
- El proyecto **sigue En desarrollo**: no pasa a «Verificado por el asesor» y el Director no recibe aviso.
- El estudiante recibe un aviso de similitud por encima del límite que lo lleva a Avances. Para continuar, sube una nueva versión final, que el asesor vuelve a verificar.
- Un segundo reporte para la misma versión se rechaza: «Ya registraste el reporte de similitud de esta versión.»
- Con exactamente 21 % el reporte es «Apto».

**Pruebas automáticas:** `VerificacionAsesorTest::test_similitud_mayor_al_limite_vuelve_al_estudiante`, `test_apto_sin_conformidad_y_observaciones_abiertas_bloquean`.

---

## F-06 · Asignación de jurado con validaciones

| | |
|---|---|
| **Objetivo** | El jurado tiene 3 docentes distintos, ninguno es el asesor, los cargos no se repiten y se puede reemplazar a un miembro con historial. |
| **Cuentas** | director@ |
| **Escenario** | PT-DEMO-06 (y PT-DEMO-05 para el caso «sin conformidad»). |
| **Datos** | Siembra los escenarios. |

**Pasos**

1. **director@** → Panel general → «Requieren tu acción» → **Asignar** en PT-DEMO-06.
2. Abre la lista del Presidente: el asesor **Mg. Carlos Medina Ruiz** (`asesor@`) **no aparece**.
3. Elige **Dra. Ana Paredes Luna** como Presidente: en las listas de Secretario y Vocal esa docente aparece desactivada.
4. Completa Secretario **Ms. Jorge Castillo Ríos** y Vocal **Dra. Rosa Huamán Díaz** → **Asignar jurado y notificar**.
5. En la misma pantalla, en «Reemplazar a un miembro», elige el cargo Vocal y como nuevo docente **Dra. Elena Vargas Soto** (`profesor@`). Escribe el motivo y pulsa **Reemplazar y notificar**.
6. Intenta reemplazar de nuevo el Vocal por **Dra. Rosa Huamán Díaz**, que ya integró ese jurado.
7. Abre la pantalla de jurado de PT-DEMO-05, que está En desarrollo. La lista no ofrece el enlace en ese estado, así que copia la URL del paso 1 (`/tesis-proyecto-tesis/director/proyectos/{uuid}/jurado`) y cambia el uuid por el de PT-DEMO-05. Lo ves como asesor@ en Mis proyectos → PT-DEMO-05.

**Resultado esperado**

- Paso 4: el estado pasa a «En revisión del jurado». Cada docente recibe «Fuiste asignado como {cargo} del jurado · PT-DEMO-06».
- Paso 5: el «Jurado vigente» muestra a Dra. Elena Vargas Soto como Vocal. El historial guarda al anterior como «Reemplazado», con su motivo. El nuevo docente recibe «Fuiste asignado…» y el anterior «Dejaste de integrar el jurado · PT-DEMO-06».
- Paso 6: «Ese docente ya integra (o integró) este jurado.»
- Paso 7: «El proyecto aún no tiene la conformidad del asesor (En desarrollo).» No hay formulario.
- Reglas que el servidor aplica aunque se manipule el formulario (probadas automáticamente):
  - «El asesor no puede ser jurado de su propio asesorado.»
  - «Los tres miembros del jurado deben ser docentes distintos.»
  - Un solo miembro activo por cargo (índice único en la base de datos).

**Pruebas automáticas:** `JuradoDictamenTest::test_director_asigna_jurado_valido_y_notifica`, `test_reglas_de_asignacion`, `test_reemplazo_de_jurado_guarda_historial`.

---

## F-07 · Revisión intermedia referencial

| | |
|---|---|
| **Objetivo** | En la etapa Tesis, desde la semana 8, cada jurado puede hacer una revisión intermedia. Es referencial: no cambia el estado ni bloquea al estudiante. |
| **Cuentas** | jurado1@, est08@ |
| **Escenario** | PT-DEMO-08. |
| **Datos** | Siembra los escenarios. |

**Pasos**

1. **jurado1@** → Proyectos asignados → PT-DEMO-08 → **Revisión intermedia**.
2. El título dice «Revisión intermedia (referencial)». Marca un criterio como ✗ con su observación y los demás como cumple.
3. Pulsa **Registrar revisión intermedia**.
4. Vuelve a Proyectos asignados.
5. **est08@** → Inicio y Observaciones.

**Resultado esperado**

- La revisión queda registrada como «Referencial». El proyecto **sigue en «Tesis en desarrollo»**.
- El botón «Revisión intermedia» desaparece para jurado1@, porque solo se hace una por jurado. jurado2@ y jurado3@ aún la tienen.
- El estudiante recibe «Un miembro del jurado verificó la versión v2 con la rúbrica», con el mensaje «Revisión intermedia del jurado (referencial, no bloquea tu avance)». La observación aparece en sus pendientes.
- Antes de la semana 8, o fuera de la etapa Tesis, la revisión intermedia no está disponible.

**Pruebas automáticas:** `JuradoDictamenTest::test_revision_intermedia_desde_semana_8_es_referencial_y_unica`.

---

## F-08 · Sustentación aprobada por unanimidad

| | |
|---|---|
| **Objetivo** | Con 3 aprobatorios el resultado es «Aprobado por unanimidad» y el acta se genera sola. |
| **Cuentas** | jurado2@, jurado3@, secretaria@ |
| **Escenario** | PT-DEMO-10 (el Presidente ya registró «Aprobatorio»). |
| **Datos** | Siembra los escenarios **el mismo día** de la prueba. |

**Pasos**

1. **jurado2@** → Sustentaciones → PT-DEMO-10 → **Registrar resultado** → «Aprobatorio» + hora de término → **Registrar mi resultado**.
2. Mira «Registro del jurado»: muestra quién registró, pero no qué votó.
3. **jurado3@** → hace lo mismo con «Aprobatorio».
4. **secretaria@** → Actas y dictámenes.

**Resultado esperado**

- Tras el paso 1, el resultado individual del Presidente sigue oculto.
- Tras el paso 3:
  - el proyecto pasa a **Sustentado**;
  - se genera el acta `ACT-AAAA-NNN` con «Aprobado por unanimidad» y condición «Expedito», sin nota;
  - la Secretaría y el Director reciben el aviso «Acta N.° … · PT-DEMO-10»;
  - estudiantes, jurado y asesor reciben «Resultado de la sustentación · PT-DEMO-10».
- Un segundo registro del mismo jurado se rechaza: «Ya registraste tu resultado.»
- Para cerrar el proceso, sigue el paso 8 de [F-02](#f-02--etapa-2-completa-de-la-tesis-a-tesis-aprobada) o usa PT-DEMO-11, que ya tiene el acta lista.

**Pruebas automáticas:** `SustentacionActaTest::test_unanimidad_con_mencion_honrosa`, `test_resultado_solo_desde_el_dia_y_por_jurado_activo`.

---

## F-09 · Sustentación aprobada por mayoría

| | |
|---|---|
| **Objetivo** | Con 2 aprobatorios el resultado es «Aprobado por mayoría». |
| **Cuentas** | jurado2@, jurado3@ |
| **Escenario** | PT-DEMO-10 recién sembrado. |

**Pasos**

1. **jurado2@** → **Registrar resultado** → «Aprobatorio» → **Registrar mi resultado**.
2. **jurado3@** → **Registrar resultado** → «Desaprobatorio» → **Registrar mi resultado**.

**Resultado esperado**

- El estado pasa a **Sustentado**. El acta dice «Aprobado por mayoría», condición «Expedito».
- Ahora los tres resultados individuales son visibles.
- La opción de mención honrosa no aplica, porque no es por unanimidad.

**Pruebas automáticas:** `SustentacionActaTest::test_aprobado_por_mayoria_genera_acta_y_al_informar_cierra_el_proceso`, `FlujoCompletoTest::test_recorrido_completo_de_las_dos_etapas`.

---

## F-10 · Sustentación desaprobada y nueva fecha

| | |
|---|---|
| **Objetivo** | Con 0 o 1 aprobatorios la sustentación se desaprueba y la Secretaría programa una nueva fecha, sin límite de intentos. |
| **Cuentas** | jurado2@, jurado3@, secretaria@ |
| **Escenario** | PT-DEMO-10 recién sembrado. |

**Pasos**

1. **jurado2@** y **jurado3@** registran «Desaprobatorio».
2. **secretaria@** → Sustentaciones → «Por programar»: aparece PT-DEMO-10 → **Programar**.
3. Fecha de hoy o posterior, hora `15:00`, aula `INFO-5` → **Programar y notificar**.

**Resultado esperado**

- Paso 1: el estado pasa a **Sustentación desaprobada**. Se genera el acta «Desaprobado», condición «Impedido». El aviso dice «… Se programará una nueva fecha.»
- Paso 3: el estado pasa a **Sustentación programada**, con «Intento: 2.ª sustentación». Jurados, asesor y estudiante reciben el aviso.
- Una fecha anterior a hoy se rechaza: «La fecha no puede ser anterior a hoy.»
- Un aula ocupada a esa hora se rechaza: «El ambiente ya tiene una sustentación en ese horario.»

**Pruebas automáticas:** `SustentacionActaTest::test_desaprobado_permite_reprogramar_sin_limite`, `test_validaciones_y_choques_de_agenda`.

---

## F-11 · Mención honrosa

| | |
|---|---|
| **Objetivo** | La mención honrosa se otorga solo por unanimidad **y** cuando los tres jurados la proponen. |
| **Cuentas** | secretaria@, jurado1@, jurado2@, jurado3@ |
| **Escenario** | PT-DEMO-09. |

**Pasos**

1. **secretaria@** → Sustentaciones → PT-DEMO-09 → **Programar** → fecha de hoy, `15:00`, `INFO-5` → **Programar y notificar**.
2. Cada jurado → Sustentaciones → **Registrar resultado** → «Aprobatorio», marca **Propongo mención honrosa** → **Registrar mi resultado**.
3. *(Contraprueba, después de volver a sembrar)* Repite los pasos, pero con un jurado que **no** marque la mención.

**Resultado esperado**

- Paso 2: «Aprobado por unanimidad» **con mención honrosa** en el acta y en el resultado.
- Paso 3: «Aprobado por unanimidad» **sin** mención.
- La casilla de mención solo aparece al elegir «Aprobatorio».

**Pruebas automáticas:** `SustentacionActaTest::test_unanimidad_con_mencion_honrosa`.

---

## F-12 · Notificaciones que llevan a la acción

| | |
|---|---|
| **Objetivo** | Cada aviso de la campana abre la pantalla exacta de la acción pendiente y queda marcado como leído. |
| **Cuentas** | Las de la tabla |
| **Escenario** | Todos los PT-DEMO. |
| **Datos** | Siembra los escenarios. |

**Pasos:** entra con la cuenta, abre la **campana** (arriba a la derecha), pulsa el aviso indicado («… · Ir a la acción →») y comprueba la pantalla y el contador.

| Cuenta | Aviso | Pantalla destino |
|---|---|---|
| director@ | Nuevo proyecto PT-DEMO-01 | Solicitar asesor al Decanato (PT-DEMO-01) |
| secretaria@ | Solicitud de asesor enviada · PT-DEMO-02 | Registrar resolución, con la solicitud de PT-DEMO-02 abierta |
| asesor@ | Nueva entrega · PT-DEMO-03 v2 | Revisar avance v2 de PT-DEMO-03 |
| est04@ | El asesor revisó tu entrega v1 | Levantar observaciones (PT-DEMO-04) |
| asesor@ | Observación respondida · PT-DEMO-04 | Detalle del proyecto PT-DEMO-04 |
| asesor@ | Nueva entrega · PT-DEMO-05 v4 | Verificar con la rúbrica (PT-DEMO-05) |
| director@ | Conformidad del asesor · PT-DEMO-06 | Asignar jurado (PT-DEMO-06) |
| jurado2@ | Fuiste asignado como jurado · PT-DEMO-07 | Verificar con la rúbrica como jurado (PT-DEMO-07) |
| jurado1@ | Revisión intermedia · PT-DEMO-08 | Revisión intermedia (PT-DEMO-08) |
| asesor@ | Nueva entrega · PT-DEMO-08 v2 | Revisar avance v2 de PT-DEMO-08 |
| secretaria@ | Programar sustentación · PT-DEMO-09 | Programar sustentación (PT-DEMO-09) |
| jurado3@ | Sustentación programada · PT-DEMO-10 | Resultado de la sustentación (PT-DEMO-10) |
| secretaria@ | Acta N.° ACT-DEMO-11 · PT-DEMO-11 | Acta de sustentación ACT-DEMO-11 |

**Resultado esperado**

- La URL de destino solo contiene uuid (nunca `/1`, `/2`, …).
- El contador de la campana baja en 1 y el aviso deja de decir «sin leer».
- **Marcar todas como leídas** deja el contador en 0.
- Un aviso ajeno o alterado responde **404** y no se marca como leído.
- Sin sesión, la acción lleva al login.

**Pruebas automáticas:** `EscenariosNotificacionesTest::test_cada_aviso_lleva_a_la_pantalla_exacta_y_queda_leido`, `test_aviso_con_token_ajeno_o_alterado_no_se_abre`, `test_campana_muestra_avisos_como_formularios_post`.

---

## F-13 · Alumno intenta abrir un proyecto ajeno

| | |
|---|---|
| **Objetivo** | Un estudiante solo ve sus propios proyectos y archivos. |
| **Cuentas** | est03@ (dueño) y est01@ (ajeno) |
| **Escenario** | PT-DEMO-03. |

**Pasos**

1. **est03@** → Avances. Copia la URL: `/tesis-proyecto-tesis/alumno/proyecto/{uuid}/avances`. Copia también el enlace de un PDF de «Historial de versiones» (`/tesis-proyecto-tesis/archivos/entrega/{uuid}`).
2. Cierra sesión y entra como **est01@**. Pega las dos URL.

**Resultado esperado:** las dos responden **403**. El menú de est01@ («Avances», «Observaciones») siempre lo lleva a su propio proyecto.

**Pruebas automáticas:** `RegistroProyectoTest::test_alumno_solo_ve_su_propio_proyecto`, `AvancesRevisionTest::test_acceso_cruzado_alumno_y_asesor`, `BaseModuloTest::test_descarga_de_archivo_exige_relacion_con_el_proyecto`, `EscenariosNotificacionesTest::test_escenarios_idempotentes_con_estados_y_pdf_reales`, `EscenariosNotificacionesTest::test_reiniciar_borra_proyectos_de_ensayo_de_las_cuentas_demo_y_conserva_los_demas`.

---

## F-14 · Jurado o profesor de otro proyecto

| | |
|---|---|
| **Objetivo** | Un profesor solo entra donde es asesor o jurado **activo**. |
| **Cuentas** | jurado2@, profesor@, asesor@ |
| **Escenario** | PT-DEMO-07 (tiene jurado) y PT-DEMO-05 (sin jurado). |

**Pasos**

1. **jurado2@** → Proyectos asignados → PT-DEMO-07 → **Revisar con la rúbrica**. Copia la URL `/tesis-proyecto-tesis/jurado/proyectos/{uuid}/verificar`.
2. Entra como **profesor@** (no integra ningún jurado) y pega la URL.
3. Entra como **asesor@** (asesor, no jurado, de PT-DEMO-07) y pega la URL.
4. Como **jurado2@**, cambia el uuid por el de PT-DEMO-05. Lo obtienes como asesor@ en Mis proyectos → PT-DEMO-05.

**Resultado esperado:** **403** en los pasos 2, 3 y 4. Un jurado reemplazado (F-06, paso 5) pierde el acceso en el acto.

**Pruebas automáticas:** `JuradoDictamenTest::test_autorizacion_del_jurado_y_del_director`, `SustentacionActaTest::test_resultado_solo_desde_el_dia_y_por_jurado_activo`, `VerificacionAsesorTest::test_autorizacion_de_rubrica_y_turnitin`, `BaseModuloTest::test_policy_limita_acceso_por_rol_y_relacion`.

---

## F-15 · Id numérico en la URL y rol equivocado

| | |
|---|---|
| **Objetivo** | Ninguna URL acepta ids numéricos y cada sección exige su rol. |
| **Cuentas** | asesor@, est01@, sin sesión |

**Pasos**

1. Como **asesor@**, abre `/tesis-proyecto-tesis/asesor/proyectos/1` y `/tesis-proyecto-tesis/archivos/entrega/1`.
2. Como **est01@**, abre `/tesis-proyecto-tesis/director` y `/tesis-proyecto-tesis/secretaria/resoluciones`.
3. Sin sesión, abre `/tesis-proyecto-tesis/alumno`.
4. Sin sesión, abre `/tesis-proyecto-tesis` (la portada).

**Resultado esperado**

- Paso 1: **404**. La ruta solo reconoce uuid.
- Paso 2: **403**.
- Paso 3: redirección a `/login`.
- Paso 4: 200 con el nombre del módulo y el enlace de acceso, sin datos.

**Pruebas automáticas:** `BaseModuloTest::test_route_key_es_uuid_y_rechaza_ids_numericos`, `BaseModuloTest::test_portada_sin_sesion_no_muestra_datos_y_lo_demas_exige_login`, `SeguridadRutasTest::test_parametros_de_ruta_solo_aceptan_uuid`, `test_rutas_de_cada_carril_exigen_su_rol`, `test_toda_ruta_salvo_la_portada_exige_auth_y_verified`.

---

## Pruebas automáticas

```bash
vendor/bin/phpunit --filter=TesisProyectoTesis           # 75 pruebas del módulo
vendor/bin/phpunit tests/Unit/ModuleStructureTest.php    # aislamiento entre módulos
```

- Necesitan PostgreSQL con todos los módulos migrados (`php artisan module:migrate all`). Con SQLite se marcan como omitidas.
- Cada prueba corre dentro de una transacción y siembra `TesisDatabaseSeeder`.
- Más detalle en [MANUAL_ARQUITECTURA.md › Pruebas](MANUAL_ARQUITECTURA.md#pruebas).

| Archivo | Qué cubre |
|---|---|
| `BaseModuloTest` | Migraciones, rúbrica, seeders, identidad, uuid, Policy, portada, descargas, historial, API |
| `RegistroProyectoTest` | Registro F1, coautor, proyecto activo único, panel del estudiante |
| `DesignacionAsesorTest` | Solicitud al Decanato, resolución, autorización del Director y la Secretaría |
| `AvancesRevisionTest` | Avances, revisión, respuesta y levantamiento, accesos cruzados |
| `VerificacionAsesorTest` | Rúbrica del asesor, Turnitin, límites |
| `JuradoDictamenTest` | Asignación, reemplazo, verificación, dictamen, revisión intermedia |
| `SustentacionActaTest` | Programación, choques de agenda, resultados, acta, mención, reprogramación |
| `FlujoCompletoTest` | Recorrido completo de las dos etapas por HTTP |
| `SeguridadRutasTest` | Revisión automática de `auth`, roles, uuid, POST, `{!! !!}`, `@csrf`, `$fillable` |
| `EscenariosNotificacionesTest` | Seeder de escenarios, PDF reales, destino de cada aviso |
| `Unit/ParametrosEstadosTest` | Parámetros del proceso y etiquetas de estado |
