# Correo de las invitaciones · S3-17 a S3-22

**Responsable:** Álvaro. **Para:** el grupo que despliega el módulo. **Referencias:** `docs/seguimiento-egresado/permisos-middleware.md`, `docs/seguimiento-egresado/flujo.md` y `docs/seguimiento-egresado/arquitectura.md`.

El módulo envía por correo el **enlace personal** de cada invitación. Este documento dice qué se envía, cómo se configura y qué falta decidir para producción. No hay ningún servicio de pago incorporado: se usa el correo estándar de Laravel (`MAIL_*`), así que cambiar de proveedor es cambiar el `.env`.

## Qué se envía y cuándo

| Acción de la Secretaría | Pantalla | Propósito del enlace |
|---|---|---|
| Emitir invitaciones de una campaña | Campañas e invitaciones | `SEGUIMIENTO` (encuesta) |
| Reenviar recordatorio | Campañas e invitaciones | `SEGUIMIENTO`, con un enlace nuevo |
| Generar enlace de contacto inicial (un alumno o toda la promoción) | Padrón → página de la promoción (pasos 3 y 4) | `CONTACTO_INICIAL` (datos de contacto) |

El mensaje (`App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail`) lleva el nombre del egresado, un botón y el enlace en texto, y la fecha en que vence. Se envía en HTML y en texto plano. Las vistas están en `resources/views/modules/seguimiento-egresado/emails/`.

## Reglas de envío

Toda la lógica está en `App\Modules\SeguimientoEgresado\Services\Invitaciones\EnvioDeEnlaces`. Es el único lugar que hay que tocar si se cambia la forma de enviar.

- **Destino.** El contacto inicial va al correo **institucional**. Las campañas de seguimiento van **solo al correo personal** que el egresado dejó (nunca al institucional); si no lo dejó, no se envía y no se lo convoca.
- **Qué se registra.** `enviado_en` se marca solo cuando el servidor de correo aceptó el mensaje, y se guarda el canal (`CORREO_PERSONAL` o `CORREO_INSTITUCIONAL`) en `id_canal_envio`. Si el envío falla, se marca `entrega_fallida`. Con `MAIL_MAILER=log` o `array` el mensaje no sale a ningún servidor, por lo que no se marca como enviado.
- **El enlace nunca se pierde en el momento.** Pase lo que pase con el correo, el enlace se muestra una vez en pantalla con su estado, para que la Secretaría lo entregue por otro medio. En la BD solo queda el SHA-256, así que después no se puede volver a mostrar: se genera uno nuevo («Enlace nuevo» o «Reenviar recordatorio»).

Estados que ve la Secretaría (`App\Modules\SeguimientoEgresado\Services\Invitaciones\EstadoEnvio`):

| Estado | Significa |
|---|---|
| Enviado por correo a … | El servidor lo aceptó. Si hay redirección, dice «(redirigido)». |
| Enlace listo para entregar (sin envío por correo) | `MAIL_MAILER` es `log` o `array`, o `SG_CORREO_ENVIAR=false`. El enlace no se envió: se entrega a mano. |
| Sin correo registrado | El egresado no tiene correo. |
| Falló el envío | El servidor rechazó el mensaje o no se pudo conectar. Queda en `storage/logs` (sin el enlace). |

## Variables de entorno

| Variable | Valor por defecto | Para qué sirve |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` escribe el correo en `storage/logs` (solo desarrollo). `smtp` envía de verdad. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | ver `.env.example` | Servidor SMTP. |
| `MAIL_SCHEME` | vacío | Vacío o `smtp` negocia TLS (puerto 587). `smtps` para el puerto 465. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `hello@example.com` | Remitente. En producción debe ser una dirección del dominio oficial. |
| `SG_CORREO_ENVIAR` | `true` | `false` apaga el envío; los enlaces siguen saliendo en pantalla. |
| `SG_CORREO_REDIRIGIR_A` | vacío | Si tiene un correo, **todo** el correo va a esa dirección. Obligatorio con datos de prueba. En producción se deja vacío. |
| `SG_CORREO_PERMITIDOS` | vacío | Lista de direcciones separadas por comas. Si tiene valores, **solo** se envía a esas direcciones; a cualquier otra no sale nada y su enlace queda para entregarlo a mano («Enlace listo para entregar»). Vacío = sin límite. |
| `SG_DIAS_CONTACTO_INICIAL` | `30` | Días de vigencia del enlace de contacto inicial. El de campaña vence con la campaña. |

Después de cambiar el `.env`, con la configuración en caché: `php artisan config:clear`.

## Configuraciones

### Desarrollo

Se deja `MAIL_MAILER=log`. El módulo avisa en pantalla que el correo no está conectado. El mensaje, con el enlace, queda en `storage/logs/laravel.log`.

### Pruebas locales de punta a punta

Cualquier servidor SMTP local que acepte mensajes sirve (por ejemplo Mailpit, gratuito). Con `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1` y `MAIL_PORT=2525` (los valores del `.env.example`), los correos aparecen en su bandeja y se comprueba el recorrido completo sin escribir a nadie.

### Demostración con Gmail (gratis, limitado)

1. En la cuenta de Google: activar la verificación en dos pasos y crear una **contraseña de aplicación** (16 letras).
2. En el `.env` local (nunca en el repositorio):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=<cuenta de Gmail>
MAIL_PASSWORD=<contraseña de aplicación, sin espacios>
MAIL_FROM_ADDRESS=<la misma cuenta>
MAIL_FROM_NAME="Seguimiento al Egresado"
SG_CORREO_REDIRIGIR_A=<tu correo, para recibir las pruebas>
```

3. Comprobar: Padrón → una promoción → paso 4 → «Generar enlace» en un alumno. Debe decir «Enviado por correo a … (redirigido)» y llegar el mensaje.

Gmail reescribe el remitente con la cuenta autenticada y limita los envíos diarios (del orden de 500 destinatarios en cuentas personales; el límite lo fija Google). Sirve para demostrar, no para producción.

### Producción

No existe un servicio gratuito, sin límite y confiable para enviar correo: hay que elegir. Opciones, de más a menos recomendable:

1. **SMTP institucional** de la universidad (si la Escuela tiene Google Workspace o Microsoft 365, o un servidor de correo propio). No cuesta extra y el remitente es del dominio oficial.
2. **Proveedor transaccional con plan gratuito** (Brevo, Resend, Mailgun, Amazon SES u otro). Los planes gratuitos suelen cubrir el volumen real del módulo (decenas o cientos de correos por campaña). Verificar los límites vigentes antes de decidir, porque cambian.
3. **Servidor SMTP propio**. Es gratis, pero la entregabilidad es mala si la IP no tiene reputación: casi todo termina en spam. No se recomienda.

Con cualquiera de las tres, en el dominio oficial hay que configurar **SPF, DKIM y DMARC** y usar un remitente fijo del dominio (por ejemplo `seguimiento@<dominio>`). Sin eso, el correo llega a spam.

## Límites conocidos (trabajo para el despliegue)

- **El envío es síncrono.** Se envía dentro de la petición de la Secretaría. Cada correo tarda del orden de un segundo; con PHP-FPM, `max_execution_time` (30 s por defecto) corta la emisión de campañas de más de unas decenas de egresados. Las invitaciones ya creadas quedan guardadas, pero sus enlaces no llegan a mostrarse: se recuperan con «Reenviar recordatorio» (genera un enlace nuevo y lo envía). Solución recomendada: pasar el envío a una cola (`QUEUE_CONNECTION=database` ya está en `.env.example`) y marcar `enviado_en`/`entrega_fallida` desde el trabajo en cola. El único punto a cambiar es `EnvioDeEnlaces::enviar()`.
- **«Enviado» significa «el servidor lo aceptó».** No detecta rebotes posteriores (casilla llena, dirección inexistente). Si se necesita, hay que leer los avisos de rebote del proveedor y marcar `entrega_fallida`.
- **`MAIL_MAILER=log` guarda el enlace completo en el registro.** No usarlo en producción.
- **Seguridad del enlace.** Quien tenga el enlace entra al expediente de ese egresado hasta que venza. Está dicho en el correo («No lo compartas»). El enlace usa HTTPS solo si `APP_URL` y el servidor lo usan: en producción, `APP_URL=https://…`.

## Archivos

| Archivo | Para qué |
|---|---|
| `config/seguimiento-egresado.php` (sección `correo`) | Lee las variables `SG_*`. |
| `app/Modules/SeguimientoEgresado/Services/Invitaciones/EnvioDeEnlaces.php` | Envía y registra el resultado. |
| `app/Modules/SeguimientoEgresado/Services/Invitaciones/EstadoEnvio.php`, `ResultadoEnvio.php` | Estados y textos de pantalla. |
| `app/Modules/SeguimientoEgresado/Mail/InvitacionEgresadoMail.php` y `resources/views/modules/seguimiento-egresado/emails/` | El mensaje. |
| `tests/Feature/SeguimientoEgresado/Invitaciones/EnvioDeEnlacesTest.php`, `CorreoCampaniaTest.php` | Envío real simulado, redirección, fallo y modo de prueba. |
