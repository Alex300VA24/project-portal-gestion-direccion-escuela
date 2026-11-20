# Acceso por rol sin adscripción institucional — verificación temporal

## Objetivo

Permitir que el director, la secretaria y el profesor **entren a su espacio del módulo y comprueben que su sesión y rol son reconocidos**, aunque todavía no exista el vínculo institucional usuario–escuela o usuario–docente.

Esto habilita una **vista de navegación y estado**, no el acceso a datos académicos protegidos.

## Comportamiento esperado

| Cuenta | Ruta | Comportamiento sin adscripción/identidad |
| --- | --- | --- |
| `director_escuela` | `/gestion-horarios` o `/gestion-horarios/administracion` | Muestra «Ingreso administrativo confirmado». Si tiene permiso de consulta, informa que falta adscripción a escuela. |
| `secretaria` | Las mismas rutas | Muestra su rol confirmado y la misma limitación de escuela. |
| `profesor` | `/gestion-horarios/mi-disponibilidad` | Muestra «Acceso docente confirmado», pero no consulta asignaciones ni disponibilidad si falta identidad docente. |
| `alumno` | `/gestion-horarios` | Conserva su espacio académico y la consulta de horarios publicados. |

## Límites de seguridad

- Se conserva `AmbitoConsultaAdministrativa::exigirEscuela()` en las rutas o componentes que requieren datos privados por escuela.
- Se conserva `ProveedorIdentidadDocente` para consultar asignaciones y disponibilidad personales. La pertenencia al rol `profesor` no acredita un `id_docente`.
- No se cambia `RegistroPolicy.php`, Core, Seguridad, Prematrícula ni `bootstrap/providers.php`.
- No se agregan enlaces que lleven a pantallas privadas sabidamente denegadas; la consulta publicada continúa disponible según sus propias reglas.
- No se habilitan escrituras, exportaciones administrativas ni generación, aprobación o publicación de horarios.

## Integración pendiente

Seguridad y Core deben proporcionar: (1) relación verificable entre `App\Models\User` y la identidad institucional; (2) adscripciones a escuela con vigencia y revocación; (3) permisos diferenciados de lectura y gestión; y (4) vínculo comprobable de la cuenta con `core.docente` para el profesor. Matrículas debe acreditar los horarios personales de estudiantes. Una pantalla de bienvenida por rol no sustituye ninguno de estos contratos.

## Pruebas

En un entorno de prueba aislado:

```powershell
php artisan test --filter=InicioModuloTest
php artisan test --filter=AmbitoConsultaAdministrativaTest
```

Validar también en navegador con cuentas de director, secretaria, profesor y alumno. Revisar mensajes entendibles, foco por teclado, navegación y dispositivos móviles (heurísticas de Nielsen). Si una cuenta sin escuela puede consultar información de otra escuela, es una regresión de seguridad.
