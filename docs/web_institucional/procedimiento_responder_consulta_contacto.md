# Registrar una respuesta de contacto

Nueva migración: `2026_11_03_000014_create_sp_responder_consulta_contacto.php`,
en `app/Modules/WebInstitucional/Database/Migrations/`. No modifica las 13
migraciones publicadas ni crea/elimina tablas. El proveedor modular ya carga esa carpeta.

## Contrato y revisión del modelo

La migración `2026_11_02_000012_create_consultas_contacto_table.php` define la
tabla real del repositorio: PK `id_consulta` BIGINT autoincremental; `estado`
VARCHAR(50) NOT NULL, sin CHECK, enum ni valor predeterminado; `respuesta` TEXT,
`respondido_por` BIGINT y `respondida_en` TIMESTAMPTZ anulables. El responsable
tiene el índice `wi_consulta_contacto_responsable_index`, sin FK externa.
Las otras migraciones del módulo no añaden restricciones a esta tabla.
No se ha conectado a una base desplegada: esta revisión corresponde al DDL versionado.

Como no existe catálogo de estados, este procedimiento establece un contrato
explícito y acotado: solo acepta `recibida` y escribe `respondida` (minúsculas).
No interpreta otros estados ni altera CHECK, NOT NULL, índices o restricciones.
No normaliza estados ni sobrescribe respuestas, incluso parciales: cualquiera
de los tres metadatos de respuesta no nulo impide la operación.

Parámetros obligatorios, en orden:

1. `p_id_consulta BIGINT`: identificador positivo de una consulta existente.
2. `p_respondido_por BIGINT`: identificador positivo, referencia lógica a
   `seguridad.usuario`. La aplicación debe comprobar identidad, permisos y existencia;
   el procedimiento no consulta ese esquema ni crea FK.
3. `p_respuesta TEXT`: no nulo ni vacío o compuesto exclusivamente de espacios,
   tabulaciones/saltos de línea según la clase POSIX `space` de PostgreSQL.
   Conserva el texto recibido; no añade un límite artificial a la columna TEXT.

Actualiza únicamente estado, respuesta, responsable y fecha del servidor.
No cambia recepción, derivación ni datos del remitente; no envía correos.
`FOR UPDATE` serializa llamadas concurrentes sobre la misma consulta: una segunda
llamada no sobrescribe la primera (a mayor aislamiento puede recibirse `40001`).
No realiza COMMIT; participa en la transacción del llamador. Usa `SECURITY INVOKER`
y `search_path` restringido, con objetos del módulo calificados. El rol llamador
necesita EXECUTE sobre el procedimiento y los permisos de lectura/actualización
correspondientes sobre la tabla; no es un mecanismo de autorización por usuario.

Errores: `22023` argumentos inválidos, `P0002` consulta inexistente, `55000`
estado no admitido/respuesta previa o parcial. Tipos incompatibles y desbordamiento
BIGINT son rechazados por PostgreSQL antes de entrar al procedimiento.

```sql
CALL web_institucional.sp_responder_consulta_contacto(
    123::bigint, 7::bigint, 'Información solicitada.'::text
);
```

## Pruebas futuras en una base aislada (no ejecutadas)

No ejecutar contra producción ni usar `docker compose up app`: ese servicio migra
automáticamente. Usar una base desechable PostgreSQL 11+ (proyecto: PostgreSQL 17),
sin datos reales, con el esquema y tablas del módulo previamente instalados.
Verificar explícitamente el destino antes de cualquier instalación:

```sql
SELECT current_database(), current_user, inet_server_addr(), inet_server_port();
```

La instalación futura puede hacerse con el SQL `CREATE PROCEDURE` del `up()`;
la reversión con el `DROP PROCEDURE` del `down()`, sin `CASCADE`. No usar
`CREATE OR REPLACE`: una colisión debe fallar, no reemplazar un objeto ajeno.
Alternativamente, cuando se autorice ejecutar migraciones, usar Laravel con la
conexión aislada y la ruta de esta única migración. No ejecutar las 13 anteriores
sobre una base que ya tenga esas tablas.

Con el procedimiento instalado, ejecutar:

```powershell
psql -X --dbname="postgresql://usuario:clave@localhost:5433/web_institucional_pruebas" --set=ON_ERROR_STOP=1 --file=docs/web_institucional/pruebas_responder_consulta_contacto.sql
```

El script comprueba éxito, preservación de otros campos, IDs nulos/cero/negativos,
ID inexistente, texto nulo/vacío/blanco, estados ajenos, respuesta parcial y
duplicación. Usa BEGIN/ROLLBACK; las secuencias pueden avanzar pese al rollback.
No crea tablas ni necesita `seguridad.usuario`.

Concurrencia manual: preparar una consulta `recibida` sin metadatos en esa misma
base aislada; sesión A: BEGIN, CALL y mantener la transacción abierta. Sesión B:
BEGIN y CALL con otro responsable/texto para el mismo ID; debe esperar. Al hacer
COMMIT en A, B debe fallar con `55000` en READ COMMITTED; hacer ROLLBACK en B y
comprobar que permanecen los datos de A. Si A hace ROLLBACK, B puede responder.

Reversión: ejecutar el SQL de `down()` en la base aislada y comprobar que
`to_regprocedure('web_institucional.sp_responder_consulta_contacto(bigint,bigint,text)')`
es NULL, que la tabla/datos siguen presentes y que CALL falla con `42883`.
Reinstalar el SQL de `up()` y repetir las pruebas. Estas pruebas de ejecución,
concurrencia y reversión están documentadas, no se afirma que hayan pasado.
