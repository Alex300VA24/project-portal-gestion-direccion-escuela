# Roles y permisos de Investigación Formativa

## Identidad de acceso

El inicio de sesión usa `App\Models\User`, tabla `public.users` y guardia `web`. Los roles y permisos de Spatie también usan `web`. Los roles académicos de `seguridad` no conceden automáticamente los permisos de Laravel.

`IdentidadInvestigacionService::resolver` exige una vinculación activa en `investigacion_formativa.identidad_usuario`, una cuenta activa en `seguridad.usuario` y una persona asociada. La identidad de docente se obtiene de `core.docente` cuando existe y está activa. No se busca por coincidencia de ID ni por dominio del correo.

## Matriz real del seeder

Todos los permisos de la tabla tienen el prefijo `investigacion-formativa.`. La columna estudiante se refiere al rol `alumno`; las operaciones de título y entrega además exigen la coordinación del grupo cuando corresponde.

| Sufijo del permiso | `director_escuela` | `profesor` | `alumno` |
|---|---|---|---|
| `view` | Sí | Sí | Sí |
| `courses.manage` | Sí | No | No |
| `surveys.manage` | Sí | No | No |
| `reports.view` | Sí | No | No |
| `configuration.manage` | No | Sí | No |
| `groups.review` | No | Sí | No |
| `evaluations.manage` | No | Sí | No |
| `groups.create` | No | No | Sí |
| `projects.update` | No | No | Sí |
| `submissions.create` | No | No | Sí |
| `surveys.respond` | No | No | Sí |
| `files.download` | Sí | Sí | Sí |

`submissions.create` y `files.download` existen como permisos y políticas. No implican que haya una ruta funcional de archivos: esa integración sigue pendiente de G4. El permiso por sí solo nunca identifica una escuela, sección o grupo autorizado.

El seeder del módulo agrega permisos sin retirar concesiones existentes. No cambia contraseñas ni asigna roles a cuentas institucionales.

```bash
php artisan db:seed --class='App\Modules\InvestigacionFormativa\Database\Seeders\InvestigacionPermisosSeeder'
```

No use el seeder raíz para esta tarea: restablece cuentas de demostración del portal. Para una demostración aislada, use el procedimiento de [manual-flujos.md](manual-flujos.md).

## Middleware y rutas

`bootstrap/app.php` registra los alias `role`, `permission` y `role_or_permission`. Las rutas web del módulo usan `auth` y `permission:investigacion-formativa.view`. Luego agregan el permiso de la operación. La API del módulo también exige autenticación web y el permiso de entrada; no ofrece una API pública de respuestas de encuesta.

Todas las rutas siguientes usan el prefijo `/investigacion-formativa`. Los valores entre llaves son claves del recurso, no rutas de archivos.

| Ruta o familia | Permiso adicional | Comprobación del recurso |
|---|---|---|
| `GET /` | Ninguno | Identidad válida y selección de los espacios de sus roles. |
| `GET /director` | `reports.view` | `ReporteService::resumenDirector`: escuelas y periodos del director. |
| `GET /director/cursos` | `courses.manage` | Lista limitada al alcance del director. |
| `PATCH /director/secciones/{seccion}/habilitacion` | `courses.manage` | `SeccionPolicy::habilitar` y comprobaciones del servicio. |
| `GET /director/secciones/{seccion}/resultados` | `reports.view` | `Gate::authorize('verResultados', $seccion)`. |
| Encuesta del director: editar, guardar, publicar, cerrar | `surveys.manage` | `SeccionPolicy::gestionarEncuesta` o `EncuestaPolicy::gestionar`, según el recurso. |
| Cursos y configuración del docente | `configuration.manage` | Docente responsable y `SeccionPolicy::configurar` para guardar. |
| Grupos y revisión del título por docente | `groups.review` | `GrupoPolicy::view` y `GrupoPolicy::revisarTitulo`. |
| `PUT /docente/secciones/{seccion}/grupos/{grupo}/entregas/{entrega}/evaluacion` | `evaluations.manage` | `GrupoPolicy::evaluar`; el servicio comprueba sección, grupo, entrega y rúbrica. |
| Crear grupo del estudiante | `groups.create` | `SeccionPolicy::crearGrupo`, padrón activo y comprobaciones atómicas del servicio. |
| `GET /alumno/secciones/{seccion}/mi-grupo` | Ninguno adicional | Grupo exacto del estudiante en la sección. |
| Cambiar título del estudiante | `projects.update` | `ProyectoPolicy::update`, coordinador y padrón activo. |
| Encuesta individual: mostrar y responder | `surveys.respond` | Participación activa y `EncuestaPolicy::responder` al responder. |

Consulte `Routes/web.php` y `Requests/` para las firmas completas. El servidor comprueba de nuevo los vínculos de los recursos; colocar un ID en una URL no concede acceso.

## Alcance y estados

- Dirección: debe tener rol de director y una fila activa de `director_escuela` para la escuela de la sección.
- Docencia: debe tener rol de profesor y ser el docente responsable en `core.seccion`.
- Estudiante: se comprueba identidad académica, escuela, padrón y grupo según la operación.
- Coordinación: además debe ser el coordinador registrado del grupo exacto. Un rol de alumno no concede coordinación.
- Encuesta: cada respuesta nueva exige padrón activo, sección habilitada y encuesta publicada. La identidad se conserva para impedir duplicados.
- Historial: `SeccionPolicy::verResultados` usa `puedeVerResultados`, que admite secciones cerradas dentro de la escuela asignada. Las operaciones de escritura conservan sus comprobaciones de sección activa.

`ProyectoPolicy::view` y `EntregaPolicy::download` resuelven el grupo del proyecto. No autorizan cualquier grupo por compartir sección. La política de descarga permanece como contrato previo; no hay un endpoint de descarga disponible.

Los servicios vuelven a verificar los estados dentro de la transacción. Las restricciones de base respaldan unicidad y pertenencia. La versión esperada evita que un formulario antiguo sobrescriba cambios recientes.

## Importaciones y operación

Las importaciones son operaciones de un operador confiable por consola. No son formularios de autoasignación de permisos.

| Importación | Cabecera CSV |
|---|---|
| Identidades | `user_id,id_usuario_seguridad,activo` |
| Directores | `user_id,id_escuela,activo` |
| Participantes | `id_seccion,id_estudiante,estado,fuente,referencia_origen` |
| Competencias | `id_seccion,codigo,descripcion,fuente,seleccionada` |

Valide con `--dry-run`. Revise los errores e IDs contra la fuente autorizada. Aplique con `--apply` solo cuando el archivo sea correcto. La exportación del demo muestra el formato y sus propios IDs; no prueba que un padrón institucional haya sido autorizado.

## Diagnóstico de acceso denegado

1. Compruebe qué cuenta de Laravel inició sesión y qué espacio eligió.
2. Verifique la existencia y el estado de su vinculación académica.
3. Revise la cuenta académica y su persona asociada.
4. Confirme el rol y el permiso con guardia `web`.
5. Revise escuela asignada, docente responsable o padrón, según el actor.
6. Verifique que la sección, el grupo, el proyecto y la entrega correspondan entre sí.
7. Para escrituras, revise habilitación, configuración, estado del recurso y versión del formulario.
8. Después de cambiar permisos por una operación autorizada, vuelva a iniciar sesión y revise la caché de permisos del entorno.

No conceda un rol superior como solución a una vinculación incorrecta. No cambie el middleware para eludir un 403. La revisión de seguridad dedicada permanece diferida; este documento describe el control existente y no constituye una certificación.
