# Manual de usuario · contacto, encuesta e historial del egresado · S3-17 a S3-22

**Responsable:** Álvaro. **Referencias:** `docs/seguimiento-egresado/flujo.md` (cómo funciona por dentro) y `docs/seguimiento-egresado/correo.md` (configuración del correo).

Tiene dos partes: la de la **Secretaría**, que genera los enlaces, y la del **egresado**, que los usa. La Secretaría y la Dirección inician sesión en el portal con su cuenta; el egresado no necesita cuenta ni contraseña: entra con su enlace personal.

---

## Parte 1 · Secretaría

### Entrar

Inicia sesión en el portal con tu cuenta de Secretaría y abre el módulo **Seguimiento al Egresado**. En la barra superior ves tu nombre y tu rol, y las opciones **Cambiar de módulo** y **Cerrar sesión**. La Dirección entra igual y ve las mismas pantallas, con permisos propios (por ejemplo, solo la Dirección registra la evaluación institucional).

### Cargar los alumnos aptos para egresar

El padrón se arma con los alumnos que ya cumplen para egresar. **Todavía no son egresados**: se registran como **Apto para egresar**, sin fecha de egreso, sin créditos y sin datos de contacto. Nada de eso se inventa: lo registra quien lo conoce.

1. En **Padrón y cohortes** registra, si no existen, el **plan curricular** y la **cohorte** (año de ingreso). El archivo se rechaza si su cohorte no existe.
2. En la pestaña de importaciones, pulsa **Cargar archivo de padrón** y elige un archivo **CSV** o Excel. Hay una plantilla descargable y un ejemplo en `docs/seguimiento-egresado/datos/alumnos-aptos-demo.csv`.
3. Columnas obligatorias: `matricula`, `tipo_doc`, `numero_documento`, `nombres`, `apellido_paterno`, `cohorte` y **`correo_institucional`** (válido). Opcionales: `apellido_materno`, `fecha_ingreso`, `fecha_egreso` (no puede ser futura) y, si ya se conocen, `correo_personal` y `celular`.
4. La carga es **todo o nada**: si una fila tiene error (matrícula repetida, correo faltante o inválido, fecha futura, cohorte inexistente), no se guarda ninguna del archivo. En la pestaña de importaciones, «Ver Log» indica la fila y el motivo; se corrige el archivo y se vuelve a cargar.

### Confirmar el egreso

Cuando el alumno egresó de verdad:

1. En **Padrón** abre su ficha y pulsa **Confirmar egreso**.
2. Escribe la **fecha real de egreso** (no puede ser futura ni anterior a su ingreso, si se conoce) y confirma.
3. El alumno pasa a **Egresado**; el periodo (por ejemplo `2025-I`) se calcula con esa fecha. Su historial conserva la etapa anterior y sus contactos no se pierden.

Solo la Secretaría confirma egresos; la Dirección consulta. Solo se puede confirmar a quien está como «Apto para egresar».

### Generar enlaces de contacto inicial

El contacto inicial recoge los datos de contacto de los alumnos aptos y de los egresados. El enlace llega a su **correo institucional**; el alumno deja ahí su correo personal y su celular, y eso queda en su mismo expediente. La tabla «Egresados» muestra quién ya completó su contacto.

1. Entra a **Padrón y cohortes** y pulsa **Contacto inicial de egresados**.
2. Elige cómo invitar:
   - **A toda una cohorte.** En «Invitar a una cohorte» elige la cohorte y pulsa **Invitar a la cohorte**. Se genera un enlace para cada egresado que todavía no dejó su contacto. Los que ya lo dejaron, o que ya tienen un enlace pendiente, se omiten y la página dice cuántos.
   - **A un egresado.** En la tabla «Egresados» busca por nombre, matrícula o documento, y pulsa **Generar enlace** en su fila. Los filtros permiten ver solo quienes no tienen contacto, solo quienes ya lo tienen, o todos.
3. Aparece el panel **Enlaces personales de contacto inicial**:
   - Pulsa **Copiar** para copiar el enlace de cada egresado.
   - La columna **Correo** dice si el enlace se envió.
4. **Copia los enlaces antes de salir de la página.** Por seguridad se muestran **una sola vez** y no se pueden recuperar.

#### Estado del correo

| Lo que dice | Qué hacer |
|---|---|
| Enviado por correo a … | Nada: el egresado ya lo recibió. Si dice «(redirigido)», el correo fue a una dirección de pruebas, no al egresado. |
| Enlace listo para entregar | El correo no está conectado: entrega el enlace por otro medio (WhatsApp, por ejemplo). |
| Sin correo registrado | El egresado no tiene correo: entrega el enlace por otro medio. |
| Falló el envío | Entrega el enlace por otro medio y avisa al responsable del correo. |

Un aviso amarillo al inicio de la página indica cuándo el correo no está conectado o está redirigido.

#### Estado del enlace de cada egresado

En la tabla, la columna **Enlace de contacto inicial** muestra:

| Estado | Significa |
|---|---|
| Sin enlace | Aún no se le generó uno. |
| Pendiente | Tiene un enlace vigente. Debajo dice si el correo salió, falló o no se envió. |
| Completado | El egresado ya dejó su contacto. |
| Vencido | El enlace caducó. |

#### Enlace perdido: «Enlace nuevo»

Si el egresado perdió su enlace, o se compartió por error, pulsa **Enlace nuevo** en su fila. Se genera otro y **el anterior deja de funcionar**.

### Invitar a una campaña de seguimiento

1. Entra a **Campañas e invitaciones** y elige la campaña.
2. Pulsa **Emitir invitaciones por cohorte**. Se crea una invitación por cada **egresado confirmado** de las cohortes de la campaña (quien ya tiene invitación se omite). Los alumnos que siguen como «Apto para egresar» no se convocan: primero hay que confirmar su egreso. El correo va a su correo **personal** si ya lo dejó; si no, al institucional.
3. El panel **Enlaces personales de las invitaciones** muestra el enlace de cada uno y el estado del correo. Cópialos antes de salir de la página.
4. En la tabla de invitaciones, **Fecha envío** queda vacía hasta que el correo salga de verdad. «Envío fallido» indica que el servidor no lo aceptó.
5. Para quien no respondió, **Reenviar recordatorio** genera un enlace nuevo, lo envía por correo y lo muestra una vez. El enlace anterior deja de funcionar.

---

## Parte 2 · Egresado

### Entrar

Abre el enlace que te dio la Escuela, por correo o por otro medio. No pide contraseña ni datos: te lleva directo a **Mi expediente**.

- El enlace es **personal**: no lo compartas. Quien lo tenga puede ver y modificar tu expediente hasta que venza.
- Si dice que el enlace no es válido, es que venció o fue reemplazado. Pide uno nuevo a la Secretaría.

### Mi expediente

Es tu página de inicio. Muestra tus datos académicos y lo que tienes pendiente:

- **Completa tus datos de contacto**, si la Escuela aún no los tiene.
- **Tienes una encuesta por responder**, con la campaña y la fecha límite. Si tienes varias, aparece cada una con su botón.

El menú de la izquierda tiene tres opciones: **Inicio**, **Mi contacto** y **Mi historial**.

### Dejar o actualizar tu contacto

1. Pulsa **Completar mi contacto** (o **Mi contacto** en el menú).
2. Llena el correo personal y el celular (obligatorios); el teléfono fijo y la dirección son opcionales.
3. Pulsa **Guardar mis datos**. Si algún dato no tiene el formato correcto, la página te lo indica.

Cuando cambias tus datos, la Escuela **conserva los anteriores** en tu historial: nada se pierde.

### Responder la encuesta de seguimiento

1. Pulsa **Responder la encuesta** en Mi expediente.
2. Completa las seis secciones: contacto, perfil profesional, situación laboral, desarrollo profesional continuo, satisfacción y habilidades, y contacto del empleador. Los campos con * son obligatorios.
   - En la primera sección verás tus datos de contacto: confírmalos o corrígelos.
   - Si no trabajas, no se te pedirán datos de empleo.
3. Revisa y pulsa **Enviar mi respuesta**. Los campos con formato (celular, correo, contacto del jefe) te avisan mientras escribes; si falta algo, la página te lo marca.

**Una vez enviada, la encuesta no se puede modificar ni responder de nuevo.** Tu respuesta queda en tu historial.

### Mi historial

- **Campañas de seguimiento:** las campañas a las que te invitaron y si respondiste. **Ver respuesta** abre lo que contestaste.
- **Cambios de contacto:** tus datos anteriores.

Es de solo lectura.

---

## Problemas frecuentes

| Problema | Qué hacer |
|---|---|
| «El enlace no es válido» | Pide un enlace nuevo a la Secretaría: pudo vencer o haber sido reemplazado. |
| No me llegó el correo | Revisa la carpeta de spam. Si no está, la Secretaría puede generarte un enlace nuevo y entregártelo por otro medio. |
| La sesión se cerró sola | Tu enlace venció. Pide uno nuevo. |
| Dice que ya respondí | La encuesta admite una sola respuesta. Puedes verla en Mi historial. |
| La campaña ya no está abierta | Pasó la fecha de la campaña. No se puede responder; consulta a la Escuela. |
| Me equivoqué en mi contacto | Entra a Mi contacto y corrígelo: se guarda como una versión nueva. |
