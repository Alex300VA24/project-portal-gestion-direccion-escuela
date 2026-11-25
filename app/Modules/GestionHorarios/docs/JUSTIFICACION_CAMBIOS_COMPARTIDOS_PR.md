# Justificación de cambios compartidos — Gestión de Horarios

**Estado:** documento de revisión para un *Draft Pull Request* hacia `develop`. No constituye aprobación de cambios compartidos ni autorización para fusionar o desplegar.

**Rama de trabajo:** `feature/gestion-horarios-integracion`.

## 1. Resumen

El módulo Gestión de Horarios incorpora exportación de horarios y controles de autorización académica. Durante su desarrollo se modificaron archivos que pueden afectar a todo el portal: `Dockerfile`, `composer.json`, `composer.lock` y `app/Modules/GestionHorarios/Policies/RegistroPolicy.php`. Se documentan por separado para que el propietario del repositorio determine si deben formar parte del PR, adaptarse o quedar excluidos.

Esta justificación se basa en **fragmentos de `git diff` facilitados por el desarrollador**, no en una revisión completa de la rama. Las partes aún no visibles se marcan como pendientes.

## 2. `Dockerfile`

### Cambios observados

- La etapa `dependencies` cambió de `composer:2` a `php:8.4-cli` y copia el ejecutable Composer desde `composer:2`.
- Se añaden paquetes del sistema y extensiones PHP para esa etapa: `bcmath`, `gd`, `mbstring` y `zip`.
- En la imagen de ejecución se incorporan las bibliotecas de PNG y ZIP y se habilitan las extensiones PHP `gd` y `zip`, además de las existentes.

### Motivo técnico y efectos

El módulo dispone de exportadores PDF/XLSX que usan dependencias con requisitos de extensiones PHP. El cambio intenta que la instalación de dependencias y la imagen operativa dispongan de las extensiones necesarias. No modifica directamente una regla académica, pero **afecta a la construcción y al tamaño/superficie de la imagen compartida del portal**.

**Revisión requerida:** el equipo de infraestructura debe validar que el conjunto de extensiones satisface el `composer.lock` actual y la política de imágenes PHP. La etiqueta `php:8.4-cli` no fija una versión de parche. Este `Dockerfile` **no acredita la instalación operativa de Python/OR-Tools** para generación institucional.

## 3. `composer.json` y `composer.lock`

### Cambios observados

El diff completo de `composer.json` muestra la incorporación de:

```json
"dompdf/dompdf": "^3.1",
"phpoffice/phpspreadsheet": "^5.11"
```

Además se modificó el script compartido `composer run dev`: se retiró `php artisan pail --timeout=0` y el proceso `logs`, pasando de cuatro procesos concurrentes a tres (`server`, `queue`, `vite`). El motivo comunicado es que Pail requiere la extensión `pcntl`, no disponible en la ejecución local de Windows. **Este cambio afecta al flujo de desarrollo del equipo**, que perdería ese visor de logs al usar el script general. El estado Git también señala `composer.lock` modificado, pero **no se recibió su diferencia**.

### Motivo técnico y efectos

La dependencia Dompdf se utiliza para generar exportaciones PDF de horarios, mientras que PhpSpreadsheet sirve para los archivos XLSX. Al ser dependencias Composer del proyecto raíz, su incorporación puede alterar la resolución de dependencias, requisitos PHP/extensiones y despliegue de **todos los módulos**. La actualización de `composer.lock` debe ser reproducible y corresponder al manifiesto. La adaptación del script `dev` a Windows debe acordarse por separado: no es imprescindible para el contrato funcional del módulo y puede interferir con la experiencia de desarrollo de otros integrantes.

**Revisión requerida:** enviar el resumen/diferencia de `composer.lock`; comprobar `composer validate`, compatibilidad de PHP y extensiones, instalación limpia y exportación en un entorno aislado. Acordar si el ajuste de `dev` se incorpora al portal o se mantiene como comando local alternativo. No excluir el lock sin comprobar consistencia.

## 4. `app/Modules/GestionHorarios/Policies/RegistroPolicy.php`

### Cambio observado

El diff completo confirma que se eliminó `protected string $permission = 'gestion-horarios.view'` y se introdujo `viewAny(User $user)`, que devuelve únicamente `$user->hasAnyRole(['director_escuela', 'secretaria'])`. También se añadió `gestionar(User $user)`, que delega en `viewAny()`, y el import `App\Modules\GestionHorarios\Services\EscuelaAutorizada`, que **no aparece utilizado** en el código mostrado. La indentación y los espacios en blanco nuevos requieren corrección de formato.

### Riesgo y decisión

**Riesgo concreto:** el nuevo `viewAny()` prescinde del permiso `gestion-horarios.view` y `gestionar()` reproduce esa autorización solo por rol. Esto puede ampliar el resultado positivo de la policy frente a la versión original; no demuestra adscripción vigente a una escuela ni permiso específico de gestión académica. Aunque otros servicios puedan aplicar comprobaciones adicionales, la policy en sí no las exige. El acceso temporal por rol solo debería mostrar navegación o estado, nunca consultas privadas o escrituras. Los proveedores institucionales de escuela e identidad docente siguen pendientes.

**Revisión requerida:** **no incorporar esta modificación al PR sin aprobación expresa** de Seguridad o del propietario del módulo. Preferir el permiso vigente y el contrato separado de escritura, conservando la experiencia de navegación temporal en las vistas o servicios que no exponen datos privados. Si no se autoriza, mantener la modificación fuera del PR sin perder la copia de trabajo; revisar las pruebas y el comportamiento de la rama propuesta para evitar que dependan de un cambio omitido.

## 5. Qué no habilitan estos cambios

- No acreditan la relación entre el usuario `App\Models\User` y su escuela o docente institucional.
- No habilitan generación, aprobación ni publicación institucional de horarios.
- No resuelven contratos de demanda de Prematrícula ni matrícula vigente.
- No autorizan aplicar migraciones sobre la base compartida.
- No convierten las pruebas ficticias de CP-SAT o exportación en una validación productiva.

## 6. Comprobaciones pendientes antes de decidir el contenido del PR

1. Revisar los cambios de `composer.lock` y su correspondencia con las dependencias Dompdf y PhpSpreadsheet.
2. Verificar que las dependencias de exportación realmente requieren las extensiones instaladas en ambas etapas de Docker.
3. Confirmar con los responsables del portal si los archivos raíz compartidos se integran en este PR o en uno separado.
4. Revisar por separado las cuatro migraciones nuevas y sus dependencias de Core/Seguridad; la tabla `ejecucion_cpsat_prueba` es de ensayo y necesita decisión explícita sobre despliegue.
5. Ejecutar pruebas y revisiones de dependencias sobre el conjunto **exacto** de archivos que se pretenda subir.
6. Comprobar que ningún control de acceso privado dependa exclusivamente del rol, aunque la portada permita confirmar el ingreso.
7. Separar la decisión sobre eliminar Pail de `composer run dev` del resto de dependencias del módulo; evaluar una alternativa compatible con Windows que no cambie el flujo de todos los desarrolladores.

## 7. Comandos de verificación (solo lectura)

Desde la raíz del proyecto:

```powershell
git diff -- composer.json
git diff -- app/Modules/GestionHorarios/Policies/RegistroPolicy.php
git diff --stat -- composer.lock
git diff --check
git status --short
```

**Conclusión:** los cambios compartidos tienen motivos técnicos identificables, pero **aún no deben seleccionarse automáticamente para el commit**. Su incorporación requiere confirmar el diff completo, sus dependencias y la aceptación del responsable del repositorio.