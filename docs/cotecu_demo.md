# COTECU — Guía rápida de la demo

Cómo levantar el entorno, restaurar los valores por defecto y qué puede
hacer cada perfil. Todos los comandos se ejecutan desde la raíz del
proyecto, en el host.

## 1. Levantar el entorno

```bash
docker compose up -d
```

- Portal: `http://localhost:8000`. Usuarios `*@portal.local`, contraseña `Portal2026!`.
- **El servicio `app` no usa volúmenes**: los archivos se copian dentro de la imagen.
  Después de cualquier cambio de código hay que reconstruir:

```bash
docker compose build app
docker compose up -d app
```

- Las migraciones se ejecutan solas en cada arranque del contenedor
  (`php artisan module:migrate all --force`). Si el arranque falla:
  `docker compose logs app`.

## 2. Restaurar los valores por defecto

### a) Restaurar los datos demo (lo normal)

Los tres seeders son idempotentes: se pueden correr cuantas veces haga
falta, siempre **en este orden**:

```bash
docker compose exec -T app php artisan db:seed --force                               # roles y cuentas base
docker compose exec -T app php artisan db:seed --class=CotecuPermissionsSeeder --force   # permisos por rol
docker compose exec -T app php artisan db:seed --class=CotecuDemoSeeder --force       # datos demo de COTECU
```

Qué restaura cada uno:

- `DatabaseSeeder`: roles institucionales y las 4 cuentas base (contraseña `Portal2026!`).
- `CotecuPermissionsSeeder`: permisos de COTECU por rol. Va **después** del anterior
  (si faltan los roles, avisa y no hace nada).
- `CotecuDemoSeeder`: 3 semestres (2026-I Cerrado, 2026-II Activo, 2027-I Próximo),
  las cuentas demo, el director de COTECU, 2 planes curriculares, catálogo de 17 cursos,
  11 secciones, 9 sílabos con sus fechas del indicador, las listas de cotejo y
  1 documento oficial.

> Nota: los semestres solo se **crean** si faltan (no sobrescriben estados ya
> modificados) y las revisiones cerradas no se borran. Si cambiaste estados a
> mano, usa el reset total del apartado b).

### b) Reset total (borrar todo y levantar desde cero)

```bash
docker compose down -v     # borra el volumen de la base de datos
docker compose up -d       # al arrancar, el contenedor reconstruye el esquema solo
# …y luego los 3 seeders del apartado a)
```

### c) No usar `php artisan migrate:fresh`

`migrate:fresh` solo limpia el esquema público; los esquemas propios
(`core`, `seguridad`, `cotecu`, …) quedan con las tablas viejas y el
próximo arranque del contenedor falla con `Duplicate table`. El reset
total correcto es el apartado b).

## 3. Qué puede hacer cada perfil

Cuentas (todas con contraseña `Portal2026!`):

| Cuenta | Perfil |
|---|---|
| `director@portal.local` | Director de escuela |
| `secretaria@portal.local` | Secretaría |
| `profesor@portal.local` | Profesor |
| `alumno@portal.local` | Alumno (sin acceso al módulo) |
| `director-cotecu@portal.local` | Director de COTECU (solo planes) |
| `docente-ajeno@portal.local` | Profesor (mismos permisos que el Profesor Demo) |

Matriz por pantalla (verificada: **200** = puede entrar, **403** = no puede):

| Pantalla | director | secretaría | profesor | director-cotecu | alumno |
|---|---|---|---|---|---|
| Inicio `/cotecu` | ✅ con indicador | ✅ con indicador | ✅ sin indicador | ✅ sin indicador | ❌ |
| Sílabos `/cotecu/silabos` | ✅ | ✅ | ❌ | ❌ | ❌ |
| Revisar sílabos `/cotecu/silabos/revisiones` | ✅ | ❌ | ❌ | ❌ | ❌ |
| Mis sílabos `/cotecu/mis-silabos` (subir) | ❌ | ❌ | ✅ | ❌ | ❌ |
| Planes `/cotecu/planes` | ✅ | ✅ | ✅ | ✅ | ❌ |
| Revisar planes `/cotecu/planes/revisiones` | ✅ | ❌ | ❌ | ❌ | ❌ |
| Mis planes `/cotecu/mis-planes` (subir) | ❌ | ❌ | ❌ | ✅ | ❌ |
| Documentos oficiales `/cotecu/documentos-oficiales` | ✅ ve el documento | ✅ lista vacía | ✅ lista vacía | ✅ lista vacía | ❌ |

En resumen:

- **Director de escuela**: ve todas las listas y revisa/cierra sílabos y planes;
  no sube nada. Junto con la secretaría es el único que ve el indicador de la portada.
- **Secretaría**: solo lectura (Sílabos, Planes, Documentos oficiales e indicador).
- **Profesor**: sube sus sílabos del semestre activo y consulta la lista de planes;
  no revisa ni ve la lista general de sílabos.
- **Director de COTECU**: única cuenta que sube planes curriculares.
- **Alumno**: no entra al módulo (403 en todo).

## 4. Limitaciones conocidas

1. **Archivos**: debido a que falta declarar la fuente donde se guardarán los
   archivos, los lectores y seleccionadores de archivos están inhabilitados.
   La subida está simulada (los campos PDF salen deshabilitados con su aviso) y
   los botones *Ver*/*Descargar* muestran el aviso de archivo no disponible.
2. **Lista de cotejo de planes**: debido a que aún falta saber la comodidad de
   operación del director, no se ha puesto todos los items de la lista de cotejo
   de los planes.
3. **Lista de cotejo de sílabos**: debido a que no se ha proporcionado la lista de
   cotejo de los sílabos, los items no son los oficiales.

## 5. El indicador de la portada

Solo lo ven el director y la secretaría: compara la fecha de aprobación de cada
sílabo Validado del semestre activo con la fecha de inicio de ese semestre. En la
demo (2026-II, inicia el 24/08/2026) muestra **2 de 3 (66.7 %)**.
