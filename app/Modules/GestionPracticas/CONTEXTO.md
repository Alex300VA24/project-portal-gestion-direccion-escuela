# Contexto del proyecto — Módulo Gestión de Prácticas

Documento de contexto: qué es este repositorio, qué se construyó, cómo está organizado y cómo se prueba.
Complementa a [README.md](README.md) (detalle funcional y comandos del módulo).

## 1. Origen y alcance

- **Proyecto base:** *Portal Gestión Dirección Escuela*, un monolito modular en **Laravel 13** (PHP 8.4) con
  **PostgreSQL 17**, 12 módulos y un esquema de base de datos por módulo. Proyecto de la unidad final del curso
  *Proyecto de Competencia* (UNT).
- **Módulo desarrollado:** **Gestión de Prácticas** (`app/Modules/GestionPracticas`, slug `gestion-practicas`,
  esquema PostgreSQL `gestion_practicas`).
- **Punto de partida:** el módulo solo tenía un *scaffold* (un modelo `Registro`, un controlador de portada y las
  11 tablas del esquema ya migradas). Todo lo demás se construyó en esta rama.
- **Regla de trabajo:** solo se tocó la carpeta del módulo, sus vistas y sus pruebas. No se modificó `app/Shared`,
  `config/`, `routes/` globales, migraciones ni otros módulos.

## 2. Qué se hizo

El proceso completo de prácticas preprofesionales, de la convocatoria al cierre, con cuatro actores:

| Actor | Rol (Spatie) | Qué hace |
| --- | --- | --- |
| Administrador | `practicas_administrador` | Registra empresas y sus cuentas; aprueba u observa convocatorias; valida la práctica y asigna al docente supervisor |
| Empresa | `practicas_empresa` | Completa su perfil; crea convocatorias, las envía a revisión y corrige observaciones |
| Estudiante | `practicas_estudiante` | Ve convocatorias publicadas, postula, informa su resultado, registra la práctica con la carta de aceptación y sube informes |
| Docente supervisor | `practicas_supervisor` | Ve solo sus prácticas; registra seguimiento; aprueba el informe final; evalúa por criterios y cierra |

### Flujo

```text
Empresa crea convocatoria ─► en_revision ─► Admin observa ─► Empresa corrige ─► Admin aprueba ─► publicada
Estudiante postula ─► seleccionado ─► registra práctica + carta ─► en_revision ─► Admin valida y asigna supervisor
─► en_curso ─► informes + seguimiento ─► Supervisor evalúa (nota ponderada) ─► finalizada
```

### Estados (máquinas de estado)

- **Convocatoria:** `borrador → en_revision → (observada ↔ en_revision) → publicada → cerrada | anulada`
- **Postulación:** `postulado → seleccionado | no_seleccionado | desistido`
- **Práctica:** `registrada → en_revision → (observada ↔ en_revision) → en_curso → finalizada`
- **Documento:** `pendiente → aprobado | observado | reemplazado`

### Reglas de negocio relevantes

- Una empresa solo ve y edita sus propias convocatorias; un estudiante solo ve su práctica; un supervisor solo opera
  sobre las prácticas que tiene asignadas.
- No se puede postular dos veces a la misma convocatoria, ni a una cerrada o vencida.
- Los informes solo se suben con la práctica `en_curso`; los tipos únicos (carta, plan, informe final, constancia)
  reemplazan a la versión anterior.
- Para cerrar una práctica hacen falta la evaluación registrada y el **informe final aprobado**.
- La nota final la calcula la función de base de datos `gestion_practicas.calcular_nota_final()` (promedio
  ponderado por `criterio_evaluacion`). Se aprueba con nota ≥ `Acceso::NOTA_MINIMA` (11, escala 0–20).
- La base de datos refuerza el proceso con restricciones `CHECK` (por ejemplo, una práctica `en_curso` exige
  `validado_por`), de modo que no se puede saltar un paso ni desde código.

## 3. Cómo está estructurado

Sigue `docs/architecture.md` y `GUIA_MODULOS.md` del proyecto base: **Route → Request → Controller → Service → Model**.

```text
app/Modules/GestionPracticas/
├── Controllers/   12 controladores delgados, uno por responsabilidad
│                  (Panel, EmpresaAdmin, PerfilEmpresa, ConvocatoriaEmpresa, RevisionConvocatoria,
│                   ConvocatoriaEstudiante, Postulacion, PracticaEstudiante, Documento,
│                   RevisionPractica, Supervisor + la portada pública Practica)
├── Models/        Eloquent sobre tablas calificadas `gestion_practicas.*` (extienden ModuleModel)
├── Services/      Casos de uso y transacciones; no conocen HTTP ni vistas
│                  (Convocatoria, Postulacion, Practica, Documento, Seguimiento, Evaluacion, Empresa)
├── Requests/      Validación y autorización de entrada (extienden ModuleRequest)
├── Policies/      Autorización por recurso (Convocatoria, Practica, Postulacion)
├── Routes/        web.php (46 rutas) y api.php (sin cambios)
├── Support/       Acceso (roles, permisos, constantes) · Actor (identidad del usuario) · Catalogos (listas)
├── Database/Seeders/  Roles/permisos/criterios y datos de demostración
├── README.md      Detalle funcional y comandos
└── CONTEXTO.md    Este documento

resources/views/modules/gestion-practicas/   24 vistas Blade (<x-app-layout> + Tailwind)
tests/Feature/GestionPracticas/              Pruebas del módulo
```

### Decisiones de diseño

- **Vínculo entre el login y el dominio.** El inicio de sesión (Breeze) usa `public.users`; el dominio usa
  `seguridad.usuario`, `core.estudiante`, `core.docente` y `gestion_practicas.empresa_usuario`. El vínculo es el
  **correo**: `public.users.email = seguridad.usuario.correo_institucional`. `Support/Actor.php` lo resuelve y expone
  `estudianteId`, `docenteId`, `empresaId`. Una cuenta sin vínculo ve el panel con un aviso y no puede operar.
- **Capas respetadas.** Los servicios no devuelven respuestas HTTP (`DocumentoService::ubicacion()` devuelve la
  ruta del archivo y el controlador arma la descarga). Las comprobaciones de pertenencia lanzan
  `AuthorizationException`, que Laravel responde como 403.
- **Datos compartidos.** Se reutilizan los modelos de `App\Shared\Models` (`Usuario`, `Persona`, `Estudiante`,
  `PeriodoAcademico`). `core.docente`, `comun.empresa` y `comun.archivo` no tienen modelo compartido, por lo que se
  leen con consultas directas en vez de modificar `Shared`.
- **Sin migraciones.** El esquema ya existía; no se alteró ninguna tabla.
- **Roles y permisos por seeder.** Permisos con el formato `gestion-practicas.*` (`view`, `empresas.gestionar`,
  `convocatorias.{gestionar,revisar,ver}`, `postulaciones.gestionar`, `practicas.{registrar,validar,supervisar}`).
- **Portada pública intacta.** `GET /gestion-practicas` sigue siendo pública y `GET /api/gestion-practicas` conserva su
  JSON, porque pruebas existentes del proyecto base lo exigen. El trabajo real está bajo `/gestion-practicas/panel/...`.
- **Archivos.** Se guardan en `storage/app/private/gestion-practicas/` (disco `local`) y se registran en `comun.archivo`.

## 4. Pruebas

`tests/Feature/GestionPracticas/`:

| Archivo | Qué cubre |
| --- | --- |
| `FlujoPracticasTest` | El proceso completo de los cuatro actores y el alta de una empresa por el administrador |
| `AutorizacionPracticasTest` | Cada rol solo entra a lo suyo; propiedad de convocatorias, prácticas y documentos; 404 si falta el archivo |
| `ReglasPracticasTest` | Validaciones y reglas de negocio (postulación doble, estados, cierre, informes) |
| `PantallasPracticasTest` | Renderiza todas las pantallas de cada rol |
| `PanelAccesoTest` | Acceso al panel (corre también sobre SQLite) |

Estado al momento de subir: **159 pruebas, 551 aserciones, OK** sobre PostgreSQL (suite completa del proyecto,
incluidos `ModuleStructureTest` y `ModuleSchemaIsolationTest`). Con SQLite (configuración por defecto) las pruebas que
requieren PostgreSQL se omiten.

## 5. Cómo ejecutarlo

```bash
# 1. Instalar y levantar (ver README.md de la raíz): docker compose up -d --build
# 2. Roles, permisos y criterios de evaluación (necesario en cualquier entorno nuevo)
php artisan db:seed --class="App\Modules\GestionPracticas\Database\Seeders\GestionPracticasAccesoSeeder"
# 3. Solo desarrollo: base académica mínima + una cuenta por actor
php artisan db:seed --class="App\Modules\GestionPracticas\Database\Seeders\GestionPracticasDemoSeeder"
# 4. Pruebas contra PostgreSQL (base aislada)
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=<base_de_pruebas> vendor/bin/phpunit
```

Cuentas de demostración (contraseña `Portal2026!`; solo para desarrollo local):
`practicas.admin@portal.local`, `practicas.empresa@portal.local`, `practicas.estudiante@portal.local`,
`practicas.supervisor@portal.local`. Entrada: `http://localhost:8000/gestion-practicas`.

## 6. Pendientes y límites conocidos

- El seeder de roles y permisos no se ejecuta solo: hay que correrlo en cada entorno nuevo (o engancharlo al
  arranque por acuerdo del equipo).
- Faltan modelos compartidos para `core.docente`, `comun.empresa` y `comun.archivo`; proponerlos al equipo sería un PR aparte.
- Las pruebas de servicios están como pruebas Feature; la guía sugiere además `tests/Unit/GestionPracticas/`.
- Para entregar al repositorio del curso, esta rama debe dividirse en ramas `feature/...` desde `develop` con commits
  `<tipo>(<modulo>): <resumen>` y PR con las secciones *Objetivo, Cambios clave, Cómo probar, Impacto/Riesgos*
  (ver `docs/PC-Convenciones-Git.md`).
