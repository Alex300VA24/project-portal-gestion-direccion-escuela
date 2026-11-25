# Gestión de Horarios Académicos

Módulo Laravel / Livewire / Blade para preparación, consulta y exportación de horarios. El motor CP-SAT está en `Engine/python/`. Las operaciones de generación, edición validada, aprobación y publicación probadas con datos históricos pertenecen a QA aislado (`Engine/qa/`); no están habilitadas como operación institucional. No se autoriza acceso únicamente por rol: la gestión exige permiso y escuela acreditada; la identidad docente necesita el proveedor correspondiente.

## Documentación

- [Índice y documentos del módulo](docs/INDICE_DOCUMENTACION_PR.md)
- [Auditoría del ZIP y stash, decisiones de integración y checklist](docs/AUDITORIA_PREPARACION_PR.md)
- [Instalación, variables de entorno y preparación del Draft PR](docs/INTEGRACION_ENTORNO_PR.md)
- [Arquitectura](docs/ARQUITECTURA_GESTION_HORARIOS.md) y [roles, permisos y middleware](docs/ROLES_PERMISOS_MIDDLEWARE.md)
- [Manual de usuario](docs/MANUAL_USUARIO_GESTION_HORARIOS.md), [flujos](docs/MANUAL_FLUJOS_GESTION_HORARIOS.md), [consulta](docs/MANUAL_CONSULTA_HORARIOS.md) y [exportación](docs/MANUAL_EXPORTACION_HORARIOS.md)
- [Contrato de Prematrícula](docs/CONTRATO_PREMATRICULA_DEMANDA_AUTOMATICA.md), [contrato del motor](docs/CONTRATO_GENERACION_CPSAT.md) y [migraciones](docs/MIGRACIONES_REVISION_PR.md)

## Pruebas

Desde la raíz: `php tests/Unit/GestionHorarios/smoke/DescargaConsultaPublicadaSmoke.php`. Los scripts `*Smoke.php` son independientes; PHPUnit no los descubre automáticamente. Usar la suite institucional de Feature y Unit en bases descartables, no en una base que conserva una publicación QA. Las pruebas especializadas PostgreSQL requieren las variables indicadas por cada clase y pueden omitirse si no están habilitadas. Un caso omitido no es un caso aprobado.

Los cambios de Composer y Docker requieren revisión conjunta. No subir credenciales, datos históricos, entornos virtuales ni seeders locales con información real. No ejecutar inicializaciones QA en el entorno institucional.
