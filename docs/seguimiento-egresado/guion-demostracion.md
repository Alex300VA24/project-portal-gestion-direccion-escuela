# Guion de demostración · Seguimiento al Egresado

Recorrido completo, con datos ficticios que se guardan de verdad en PostgreSQL: **alumno apto → contacto inicial → egreso → campaña → encuesta → empleador → indicadores**. Una prueba automática lo ejecuta de punta a punta sobre una base vacía (`tests/Feature/SeguimientoEgresado/RecorridoCompletoTest.php`).

## 1. Preparar el sistema

### Con Docker (recomendado en el equipo del grupo)

```bash
git clone -b feature/modulo-seguimiento-egresado https://github.com/VakoCS/project-portal-gestion-direccion-escuela.git
cd project-portal-gestion-direccion-escuela
cp .env.example .env            # en Windows PowerShell: Copy-Item .env.example .env
```

En `.env` poner, para no escribir a nadie real: `SG_CORREO_REDIRIGIR_A=<tu correo>`. Luego:

```bash
docker compose build
docker compose run --rm --no-deps app php artisan key:generate --show   # pegar el resultado en APP_KEY del .env
docker compose up -d                                                    # aplica las migraciones solo
docker compose exec app php artisan db:seed --force                     # cuentas del portal (secretaria y director)
```

Abrir http://localhost:8000/login.

> **No ejecutar** el seeder de datos de demostración del módulo (`SeguimientoEgresadoSeeder`) para este recorrido: carga egresados y respuestas ficticias que se mezclarían con los resultados. Tampoco `migrate:fresh`.
>
> **Para ver las pantallas con datos ya cargados** (indicadores, gráficos, campañas, enlaces de ejemplo) sin hacer el recorrido, en una base aparte y solo en un entorno local (no en `production`): `php artisan db:seed --class="App\Modules\SeguimientoEgresado\Database\Seeders\SeguimientoEgresadoSeeder"`. Los enlaces de ejemplo están en el README del módulo. Si el recorrido ya se hizo, ese seeder se mezcla con sus datos: usar una base nueva.

### Sin Docker

Requisitos: PHP 8.3 o superior con `pdo_pgsql`, `zip` y `mbstring`, Composer, Node 22 y PostgreSQL 17. Seguir el README principal y luego:

```bash
php artisan module:migrate all --force
php artisan db:seed --force
php artisan serve
```

### Cuentas

| Rol | Correo | Contraseña |
|---|---|---|
| Secretaría | `secretaria@portal.local` | en `database/seeders/DatabaseSeeder.php` |
| Dirección | `director@portal.local` | la misma |

### Correo

- `MAIL_MAILER=log` (por defecto): no sale ningún correo. El sistema no dice «enviado»: marca «Enlace listo para entregar» y muestra el enlace en pantalla para copiarlo. Para que la demostración muestre «Enviado por correo a…», usar Mailpit (más abajo) o un SMTP real.
- Con **Mailpit** (local y gratuito; probado con Docker): es un buzón que captura los correos sin entregarlos a nadie. Se levanta con
  ```bash
  docker run -d --name mailpit -p 8025:8025 -p 1025:1025 axllent/mailpit
  ```
  y en el `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=host.docker.internal` (sin Docker, `127.0.0.1`), `MAIL_PORT=1025`. Después de cambiar el `.env`, recrear el contenedor: `docker compose up -d --force-recreate app`. Los correos se leen en http://localhost:8025.
- Siempre con `SG_CORREO_REDIRIGIR_A` puesto mientras se usen datos de prueba y un correo real.
- Si el 8000 o el 5433 ya están ocupados en tu equipo, en el `.env`: `APP_HTTP_PORT=8080` y `POSTGRES_HOST_PORT=5434` (el portal queda en http://localhost:8080).

### Limitación de Docker

La imagen del portal no incluye la extensión PHP `zip`: la **importación de `.xlsx`** no funciona ahí (la fila se rechaza con «No se pudo leer el archivo»; no rompe nada) y el Excel de indicadores se entrega como CSV. Usar **CSV** para importar. Arreglarlo es agregar `zip` al `Dockerfile`, que es del proyecto principal.

## 1b. Presentación con los datos de muestra (recomendada)

En una **base nueva** y en un entorno local: `php artisan module:migrate all`, `php artisan db:seed` y `php artisan db:seed --class="App\Modules\SeguimientoEgresado\Database\Seeders\SeguimientoEgresadoSeeder"`. Con eso la base trae promociones con una tasa de egreso creíble (I1 entre 58 % y 78 %), campañas, encuestas, evaluación institucional y dos cosas **sin enviar** que se hacen en vivo. Los egresados de relleno usan correos `.test`: ninguno puede recibir un mensaje.

| Paso | Qué se hace | Resultado esperado |
|---|---|---|
| A | Iniciar sesión como Secretaría → **Inicio** (campañas por cerrar), **Indicadores**, **Evaluación institucional** (con Dirección: validar un informe y ver cómo cambia I3). | Cifras y gráficas con datos; las promociones en curso se rotulan «En curso». |
| B | **Contacto inicial.** Padrón → **Promoción 2019** → paso **2. Cargar a los alumnos** → elegir `docs/seguimiento-egresado/datos/alumnos-aptos-demo.csv` → paso **3. Enviar enlaces**. | Se cargan 6 alumnos. Los enlaces llegan **solo** a `acortegana@unitru.edu.pe` y `agamboaa@unitru.edu.pe` (la lista de correos permitidos); los otros cuatro (`.test`) quedan «No enviado» con su enlace en pantalla. |
| C | **Campaña.** Campañas e invitaciones → **Seguimiento a egresados 2026-II** (ya creada, sin enviar) → **Ver detalle** → **Enviar invitaciones**. | El correo con el enlace llega a `alvaroenrique080199@gmail.com` y `arimanuelg544@gmail.com`. Quien no dejó su correo no se convoca y el aviso lo dice. |
| D | Abrir el enlace recibido en el correo, responder la encuesta y volver a la campaña. | La invitación pasa a **COMPLETADA** y la barra de avance sube. |

Los correos de los pasos B y C se envían de verdad: hace falta `MAIL_MAILER=smtp` con la contraseña de aplicación en el `.env` (ver [correo.md](correo.md)). Con `MAIL_MAILER=log` no sale nada y el enlace se entrega a mano.

## 2. Recorrido (Secretaría)

Iniciar sesión como Secretaría y abrir **Seguimiento al Egresado** (`/seguimiento-egresado`).

| Paso | Qué se hace | Resultado esperado |
|---|---|---|
| 0 | **Padrón y promociones** → «+ Nueva promoción»: año `2019`, total de ingresantes `6`; si falta el plan, «+ Otro plan»: `Plan 2018`, vigente desde `2018-03-01`, estado Vigente. | El cuadro «Listo» y la página de la promoción en el paso 2. |
| 1 | Paso **2. Cargar a los alumnos** de la misma página → elegir `docs/seguimiento-egresado/datos/alumnos-aptos-demo.csv`. | Cuadro «Se cargaron 6 alumnos en la Promoción 2019» y el paso 3 «Te toca». Los 6 aparecen como **Apto para egresar**, sin fecha de egreso, sin créditos y sin contacto. |
| 1b | Cargar el mismo archivo otra vez. | Cuadro «No se cargó ningún alumno» con las filas y el motivo (matrícula repetida); no se crea nada. |
| 2 | Paso **3. Enviar el enlace de contacto** → elegir la **fecha límite** → «Enviar enlaces» → «Continuar». | 6 enlaces personales, una sola vez, con botón «Copiar». Con correo conectado dice «Enviado por correo a… (redirigido)»; si no, «No enviado» y se entregan a mano. |
| 3 | Abrir en otra ventana (modo incógnito) uno de los enlaces: es la vista del alumno. «Completar mi contacto»: correo personal y celular. | El alumno guarda su contacto. En el paso **4. Quién completó su contacto** ese alumno pasa a **COMPLETADA** (verde) y el resumen lo cuenta; quien no completó muestra «Aún no debería egresar». |
| 4 | **Padrón** → «Consultar alumnos y grados» → ficha de cada alumno → «Confirmar egreso» → fecha real (por ejemplo `2025-07-18`). | Pasa a **Egresado**, con fecha y periodo `2025-I`. Su historial conserva la etapa anterior y sus contactos. |
| 5 | **Campañas e invitaciones** → «+ Nueva campaña»: para egresados, periodo `2026-II`, se abre hoy y se cierra dentro de 30 días, promoción 2019 → **Ver detalle** → «Enviar invitaciones». | La campaña nace **Abierta** por sus fechas. Un enlace por **egresado confirmado que dejó su correo personal**; el correo va a ese correo personal. Quien no dejó su contacto no se convoca y el aviso lo dice. |
| 6 | Abrir un enlace de la campaña (ventana de alumno). Responder la encuesta: trabaja, **autoriza contactar a su empleador** y da el contacto del jefe. | «Gracias»; la respuesta queda en **Mi historial**. La invitación pasa a **COMPLETADA**; las demás siguen pendientes. |
| 7 | Secretaría → **Encuesta empresarial** → elegir (o crear con «+ Nueva campaña de empleadores») la campaña de empleadores. La tabla trae a los egresados que autorizaron contactar a su empresa, con su empresa y contacto → «Generar enlace» o «Generar los enlaces que faltan». Para el mercado general: «¿Cuántos enlaces?». | Una ventana con los enlaces (una sola vez), con «Copiar todos» y «Descargar lista (CSV)». Solo se evalúa a quien **autorizó** contactar a su empresa. |
| 8 | Abrir cada enlace de empresa sin iniciar sesión y responder. | La empresa responde sin cuenta; el enlace queda usado. |
| 9 | **Indicadores I1 a I6**, **Encuesta empresarial** y los botones de descarga (CSV). | Las cifras salen solo de lo respondido en el recorrido. Sin datos `[DEMO]`. |
| 10 | Secretaría → **Evaluación institucional** → **+ Nuevo informe** (F8): promoción, periodo, nivel de cada uno de los tres objetivos → «Guardar borrador». Luego **+ Registrar cambio**: tipo, «dice», «debe decir», motivo, estado «Propuesta». | El informe queda en **Borrador** (no cuenta aún en I3) y el cambio en **Propuesta**. La Secretaría no puede validar ni implementar. |
| 11 | Dirección (otra sesión) → **Evaluación institucional** → «Revisar y validar» el informe → «Guardar y validar». En el cambio, estado «Implementada» con documento y fecha. | El informe pasa a **Validado** y **I3** se actualiza («x de y objetivos logrados»); el cambio implementado suma 1 a **I6**. En **Indicadores** coinciden las mismas cifras. |
| 12 | **Descargar F8** (o F10, F11) desde la misma página. | Un libro de Excel con el formato oficial (una hoja por informe): promoción, periodo, nivel marcado con X, conclusiones, toma de decisiones y V° B° del Director. |

## 3. Qué demuestra y qué comprobar si algo falla

- **Sin datos inventados:** el alumno apto no tiene fecha de egreso, créditos ni contacto hasta que alguien los registre.
- **Enlaces seguros:** se ven una sola vez; en la BD solo queda su hash; «Enlace nuevo» anula el anterior.
- **Estado del correo real:** nunca dice «enviado» si no salió. Si falla, el enlace sigue disponible en pantalla.
- **Dos tipos de invitación:** `CONTACTO_INICIAL` (sin campaña) y `SEGUIMIENTO` (con campaña).
- **Permisos:** Dirección consulta pero no genera enlaces ni confirma egresos (recibe 403). Sin iniciar sesión, las pantallas internas llevan al acceso del portal.

| Síntoma | Causa y solución |
|---|---|
| «La promoción … no está registrada» | Falta el paso 0 (registrar el plan de estudios y la promoción). |
| Aviso «todavía no completó su contacto inicial» al confirmar el egreso | El egreso se confirma igual, pero conviene que el alumno complete su contacto (paso 4 de su promoción). |
| «No hay egresados confirmados» al emitir | Falta el paso 4 (confirmar egresos). |
| «El egresado no ha autorizado el contacto» | El egresado no respondió la encuesta o respondió que no autoriza. |
| Importación con `.xlsx` en Docker: «No se pudo leer el archivo» | Falta `zip` en la imagen; usar CSV. |
| Los correos siguen sin salir tras cambiar el `.env` | Recrear el contenedor: `docker compose up -d --force-recreate app`. |
| Pantalla de login sin estilos | Falta construir los assets del portal (`npm run build`; en Docker ya vienen). |

## 4. Pendientes conocidos

- El envío es síncrono; una campaña de más de unas decenas de egresados puede tardar o cortarse (ver `correo.md`).
- Los enlaces de empresa se copian a mano: no se envían por correo.
- No se usa `core.persona` ni `core.estudiante`: están vacías en el portal y crear estudiantes exigiría datos académicos que el padrón no tiene.
- El módulo mantiene su propio personal interno, planes y archivos (`usuario_interno`, `plan_curricular`, `archivo_evidencia`).
