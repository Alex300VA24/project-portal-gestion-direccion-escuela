# Roles, permisos, middleware y adscripción — Gestión de Horarios

**Estado:** descripción técnica vigente para revisión del equipo de Seguridad. Ningún proveedor de pruebas se considera un proveedor institucional.

## 1. Guard y rutas

| Superficie | Control de entrada | Restricción adicional |
| --- | --- | --- |
| `/gestion-horarios` | Acceso público a la portada. | Los enlaces administrativos dependen de sesión y autorización. |
| `/gestion-horarios/publicados` | Consulta pública. | Solo metadatos publicados; contrato público por validar. |
| `/gestion-horarios/administracion/*` | `auth:web` y `role:director_escuela\|secretaria,web`. | Policy, adscripción y contrato específico según operación. |
| `/gestion-horarios/mi-disponibilidad` | `auth:web` y `role:profesor,web`. | El componente Livewire también verifica el rol; la consulta de datos propios exige vínculo institucional con el docente. |
| Escritura de horarios | Solicitud `HorarioRequest::authorize() = false` y controlador con 403. | Creación y publicación institucional deshabilitadas. |

Las rutas públicas de API no deben confundirse con la autorización para consultar entidades académicas privadas.

## 2. Diferencia de `RegistroPolicy`: decisión del administrador

En la versión de `develop`, la policy hereda de `ModulePolicy`, declara `$permission = 'gestion-horarios.view'` y `viewAny()` comprueba `$user->can($this->permission)`.

La **copia local manual** de `RegistroPolicy.php` reemplaza `viewAny()` por `$user->hasAnyRole(['director_escuela', 'secretaria'])`. Esto permite superar esa comprobación con un rol aun cuando no exista el permiso Spatie `gestion-horarios.view`. También define `gestionar()` delegando en `viewAny()`.

**La policy local no verifica la escuela autorizada**. La adscripción académica es una comprobación adicional a cargo de los servicios y contratos del módulo. Un resultado positivo de `viewAny()` nunca debe interpretarse como autorización para consultar toda la base.

No se modifica aquí esa policy. Seguridad debe decidir si la comprobación final requiere rol, permiso o ambos, y cómo se combina con la adscripción, la vigencia y la revocación. Véase [Decisiones pendientes](REVISION_ADMINISTRATIVA_PENDIENTES_PR.md).

## 3. Contratos de ámbito

- `ProveedorEscuelasAutorizadas`: escuelas vigentes vinculadas al usuario y rol real.
- `ProveedorIdentidadDocente`: vínculo verificable entre cuenta web y docente de Core.
- `ProveedorPermisoGestionAcademica`: autorización institucional para gestionar oferta o asociaciones, **diferente de un permiso de lectura**.
- Contratos de recursos: alcance de docentes, ambientes y otros datos compartidos entre escuelas.

Los servicios de consulta y escritura no deben aceptar un `id_escuela` enviado por el navegador como prueba de adscripción. No se infieren escuelas desde correo, nombre, `core.estudiante` o IDs numéricos coincidentes. Si el proveedor no está registrado, falla, devuelve información incompleta o se revoca el vínculo, el acceso correspondiente debe cerrarse.

Las operaciones de oferta y asociación vuelven a comprobar permiso y escuela antes de escribir; estas comprobaciones no acreditan por sí solas una transacción atómica con sistemas externos hasta el commit.

## 4. Casos de seguridad que deben mantenerse

1. Visitante o usuario sin rol: no accede a administración.
2. Rol administrativo sin permiso requerido: comportamiento pendiente de definición institucional; no asumir autorización.
3. Rol/permiso sin escuela vigente: no consulta ni escribe datos académicos restringidos.
4. Escuela válida sin contrato de recursos compartidos: no accede a docentes y ambientes globales.
5. Identidad docente ausente o ambigua: no muestra asignaciones personales.
6. Contrato de permiso revocado: escritura denegada.
7. Descarga administrativa sin adscripción: denegada.
8. Creación de horario institucional: HTTP 403 hasta implementar el flujo oficial.

Los proveedores ficticios y los usuarios de prueba deben estar confinados a tests y bases desechables, nunca a los registros productivos del contenedor Laravel.

## 5. Decisiones solicitadas

- Matriz oficial de roles y permisos de lectura/escritura.
- Proveedor, identidad, guard, vigencia y protocolo de revocación de escuelas y docentes.
- Tratamiento de adscripciones múltiples e identidades inconsistentes.
- Alcance global de docentes/ambientes y autorización de las descargas.
- Evidencia transaccional para los flujos de escritura y de generación cuando se habiliten.

La falta de estas decisiones explica las funciones deshabilitadas; no es un error que deba solucionarse desactivando controles.

## Consulta institucional: alcance actual

Las páginas de versiones y actividades publicadas exigen auth:web. Las actividades se filtran por publicación y periodo en el servidor; no consultan propuestas privadas. El rol no acredita adscripción; generación, consultas personales y acceso a recursos privados requieren contratos institucionales verificables.

## Acceso temporal por rol (sin adscripción)

Director y secretaría pueden verificar que su sesión, rol y permiso de consulta son reconocidos sin que ello les dé acceso a datos privados de una escuela. El profesor con rol `profesor` puede entrar en su espacio y ver el estado de la vinculación, pero no consultar asignaciones ni disponibilidad si falta identidad acreditada. Los alumnos no obtienen acceso docente. Véase [Acceso por rol sin adscripción](ACCESO_POR_ROL_SIN_ADSCRIPCION.md). Los permisos `gestion-horarios.view` concedidos manualmente en un entorno local no sustituyen proveedores oficiales de identidad o adscripción.
