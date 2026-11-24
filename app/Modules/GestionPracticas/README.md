# Gestión de Prácticas

Módulo `gestion-practicas` · esquema PostgreSQL `gestion_practicas`. Cubre el proceso completo de prácticas
preprofesionales: convocatoria → postulación → registro y validación de la práctica → seguimiento → evaluación → cierre.

## Actores, roles y permisos

Los roles son de Spatie (guard `web`) y se crean con `GestionPracticasAccesoSeeder`.

| Actor | Rol | Qué hace |
| --- | --- | --- |
| Administrador | `practicas_administrador` | Registra empresas y sus cuentas; aprueba u observa convocatorias; valida la práctica y asigna al supervisor |
| Empresa | `practicas_empresa` | Completa su perfil; crea convocatorias, las envía a revisión y corrige las observaciones |
| Estudiante | `practicas_estudiante` | Ve convocatorias publicadas, postula, informa si fue seleccionado, registra su práctica, sube la carta y los informes |
| Docente supervisor | `practicas_supervisor` | Ve solo sus prácticas asignadas; registra seguimiento; revisa el informe; registra la nota y cierra |

Permisos: `gestion-practicas.view`, `.empresas.gestionar`, `.convocatorias.{gestionar,revisar,ver}`,
`.postulaciones.gestionar`, `.practicas.{registrar,validar,supervisar}`. Constantes en `Support/Acceso.php`.

## Vínculo entre el login y el dominio

El inicio de sesión (Breeze) usa `public.users`; el dominio usa `seguridad.usuario`, `core.estudiante`,
`core.docente` y `empresa_usuario`. **El vínculo es el correo**: `public.users.email = seguridad.usuario.correo_institucional`.
`Support/Actor.php` resuelve a partir de ahí el estudiante, el docente o la empresa del usuario autenticado.
Una cuenta sin `seguridad.usuario` ve el panel con un aviso y no puede operar (403).

## Estados

- **Convocatoria:** `borrador → en_revision → (observada ↔ en_revision) → publicada → cerrada` (o `anulada`).
- **Postulación:** `postulado → seleccionado | no_seleccionado | desistido`.
- **Práctica:** `registrada → en_revision → (observada ↔ en_revision) → en_curso → finalizada`.
- **Documento:** `pendiente → aprobado | observado | reemplazado`.

Reglas clave: un estudiante lleva **una práctica a la vez** (mientras tenga una registrada, en revisión, observada o en curso
no puede postular, marcarse como seleccionado ni registrar otra; los botones aparecen desactivados con el motivo);
el RUC de la empresa debe tener 11 dígitos y comenzar con 20; la empresa cambia su contraseña desde «Perfil de la empresa»;
el administrador valida y asigna supervisor en un solo paso (la práctica pasa a `en_curso`);
los informes solo se suben con la práctica `en_curso`; para cerrar hace falta la evaluación registrada y el
**informe final aprobado**; la nota final la calcula `gestion_practicas.calcular_nota_final()` (promedio ponderado
por los pesos de `criterio_evaluacion`) y se aprueba con nota ≥ `Acceso::NOTA_MINIMA` (11).

## Rutas

La portada `/gestion-practicas` es pública para quien no ha iniciado sesión; quien ya tiene acceso al módulo entra directo al panel. El trabajo está bajo `/gestion-practicas/panel/...` (requiere sesión,
correo verificado y permiso). Ver `Routes/web.php`.

## Datos y pruebas

```bash
# Roles, permisos y criterios de evaluación (necesario en cualquier entorno nuevo)
php artisan db:seed --class="App\Modules\GestionPracticas\Database\Seeders\GestionPracticasAccesoSeeder"

# Solo desarrollo: base académica mínima + una cuenta por actor (contraseña Portal2026!)
php artisan db:seed --class="App\Modules\GestionPracticas\Database\Seeders\GestionPracticasDemoSeeder"
#   practicas.admin@ · practicas.empresa@ · practicas.estudiante@ · practicas.supervisor@portal.local

# Pruebas del módulo (requieren PostgreSQL migrado; con SQLite se omiten)
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=<base_de_pruebas> vendor/bin/phpunit tests/Feature/GestionPracticas
```

Los archivos subidos se guardan en `storage/app/private/gestion-practicas/` y se registran en `comun.archivo`.
