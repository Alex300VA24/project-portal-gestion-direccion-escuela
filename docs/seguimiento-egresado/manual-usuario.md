# Manual de usuario · contacto, encuesta e historial del egresado · S3-17 a S3-22

**Responsable:** Álvaro. **Referencias:** `docs/seguimiento-egresado/flujo.md` (cómo funciona por dentro) y `docs/seguimiento-egresado/correo.md` (configuración del correo).

Tiene dos partes: la de la **Secretaría**, que genera los enlaces, y la del **egresado**, que los usa. La Secretaría y la Dirección inician sesión en el portal con su cuenta; el egresado no necesita cuenta ni contraseña: entra con su enlace personal.

---

## Parte 1 · Secretaría

### Entrar

Inicia sesión en el portal con tu cuenta de Secretaría y abre el módulo **Seguimiento al Egresado**. En la barra superior ves tu nombre y tu rol, y las opciones **Cambiar de módulo** y **Cerrar sesión**. La Dirección entra igual y ve las mismas pantallas, con permisos propios (por ejemplo, solo la Dirección registra la evaluación institucional).

### Padrón y promociones: un flujo de cuatro pasos

Una **promoción** es el grupo de alumnos que ingresó el mismo año. En **Padrón y promociones** ves la lista de promociones y, de cada una, el paso en que va (✓ Promoción · Alumnos · Enlaces · Contacto). El botón de la derecha (**Cargar alumnos →**, **Enviar enlaces →**, **Ver seguimiento →**) te lleva a su página, directo al paso que toca. Así puedes dejar el trabajo a medias y retomarlo después.

La página de cada promoción tiene los cuatro pasos uno debajo de otro, sin cambiar de pantalla. Cada paso se habilita al terminar el anterior:

1. **Promoción y total de ingresantes.** Con **+ Nueva promoción** registras su año de ingreso, su total de ingresantes (con él se calcula qué porcentaje llega a egresar) y su plan de estudios; si falta el plan, **+ Otro plan** lo registra ahí mismo. **Editar** corrige estos datos.
2. **Cargar a los alumnos.** Sube un archivo **CSV** o Excel con los alumnos que ya cumplen para egresar. Como estás dentro de la promoción, **todos quedan unidos a ella**. Hay un formato modelo descargable y un ejemplo en `docs/seguimiento-egresado/datos/alumnos-aptos-demo.csv`.
   - Columnas obligatorias: `matricula`, `tipo_doc`, `numero_documento`, `nombres`, `apellido_paterno` y **`correo_institucional`** (válido). Opcionales: `apellido_materno`, `fecha_ingreso`, `fecha_egreso` (no puede ser futura) y, si ya se conocen, `correo_personal` y `celular`. Si el archivo trae una columna `cohorte` (año), debe coincidir con la promoción.
   - La carga es **todo o nada**: si una fila tiene un problema (matrícula repetida, correo faltante o inválido, fecha futura, otra promoción), no se guarda ninguna. Un cuadro dice qué filas corregir; también queda en **Consultar alumnos y grados → Historial de cargas**. Si todo sale bien, el cuadro dice cuántos alumnos se cargaron.
   - Los alumnos quedan como **Apto para egresar**, sin fecha de egreso, sin créditos y sin contacto. **Cargar más alumnos** vuelve a abrir el paso 3 para los nuevos.
3. **Enviar el enlace de contacto.** Elige la **fecha límite** (se sugiere 30 días desde hoy; máximo un año) y pulsa **Enviar enlaces**; confirma en el cuadro. Cada alumno sin contacto recibe en su **correo institucional** un enlace personal que funciona hasta el final de ese día. **Completar el contacto es obligatorio para poder egresar**: el correo y el formulario se lo dicen. Los que ya dejaron su contacto o ya tienen un enlace pendiente se omiten y el aviso dice cuántos.
4. **Quién completó su contacto.** Una tabla (matrícula, alumno, fecha de envío, estado del enlace, fecha de respuesta y acción) muestra a cada alumno de la promoción:

   | Estado | Significa |
   |---|---|
   | COMPLETADA (verde) | Ya dejó su contacto. |
   | PENDIENTE | Tiene un enlace vigente («hasta el dd/mm»). Debajo dice si el correo salió, falló o hay que entregarlo a mano. |
   | VENCIDA (rojo) | Pasó la fecha límite sin completar. |
   | SIN ENLACE | Aún no se le generó uno. |

   Arriba de la tabla un resumen cuenta cuántos completaron, cuántos siguen en plazo, cuántos vencieron y cuántos no tienen enlace, y un aviso dice cuántos alumnos **aún no deberían egresar** por no haber completado su contacto. Quien no completó tiene **Enlace nuevo** (conserva la misma fecha límite) y la nota «Aún no debería egresar». Un filtro permite ver solo quienes pasaron la fecha límite sin completar.

   Los enlaces se muestran **una sola vez** al generarlos (botón **Copiar**); en la base solo queda su huella.

**Consultar alumnos y grados** (botón de la lista) conserva la lista completa de alumnos con su ficha, los grados y títulos y el historial de cargas.

### Confirmar el egreso

Cuando el alumno egresó de verdad:

1. En **Consultar alumnos y grados** abre su ficha y pulsa **Confirmar egreso**.
2. Escribe la **fecha real de egreso** (no puede ser futura ni anterior a su ingreso, si se conoce) y confirma.
3. El alumno pasa a **Egresado**; el periodo (por ejemplo `2025-I`) se calcula con esa fecha. Su historial conserva la etapa anterior y sus contactos no se pierden.

Solo la Secretaría confirma egresos; la Dirección consulta. Solo se puede confirmar a quien está como «Apto para egresar». Si el alumno aún no completó su contacto inicial (obligatorio para egresar), el sistema lo **avisa** pero deja continuar.

### Estado del correo de los enlaces

| Lo que dice | Qué hacer |
|---|---|
| Correo enviado | Nada: el alumno ya lo recibió. |
| Enlace listo para entregar | El correo no salió hacia esa dirección: entrega el enlace por otro medio (WhatsApp, por ejemplo). |
| Falló el envío | Entrega el enlace por otro medio y avisa al responsable del correo. |

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
