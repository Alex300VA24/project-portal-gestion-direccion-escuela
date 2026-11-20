# Documentación de Gestión de Horarios — índice para revisión

**Estado:** paquete de documentación candidato a un *Draft Pull Request* hacia `develop`. No representa autorización institucional, despliegue ni funcionalidad completa.

**Alcance:** módulo Gestión de Horarios del portal; las integraciones con Core, Seguridad y Prematrícula requieren contratos aprobados por sus responsables.

## Lectura recomendada para revisores

| Orden | Documento | Propósito |
| --- | --- | --- |
| 1 | [Estado de revisión](ESTADO_REVISION_PR.md) | Qué está implementado, qué se probó y qué falta. |
| 2 | [Decisiones administrativas](REVISION_ADMINISTRATIVA_PENDIENTES_PR.md) | Preguntas concretas para Seguridad, Core e infraestructura. |
| 3 | [Manual de usuario](MANUAL_USUARIO_GESTION_HORARIOS.md) | Orientación para dirección, secretaría y docente. |
| 4 | [Manual de flujos](MANUAL_FLUJOS_GESTION_HORARIOS.md) | Escenarios reproducibles y resultados esperados. |
| 5 | [Arquitectura](ARQUITECTURA_GESTION_HORARIOS.md) | Componentes Laravel, consultas, persistencia y CP-SAT. |
| 6 | [Roles, permisos y middleware](ROLES_PERMISOS_MIDDLEWARE.md) | Controles existentes y decisiones pendientes. |

## Contratos técnicos y revisión de despliegue

- [Integración con Prematrícula](INTEGRACION_PREMATRICULA.md): alcance real del intercambio de demanda.
- [Contrato propuesto de demanda automática](CONTRATO_PREMATRICULA_DEMANDA_AUTOMATICA.md): datos, versiones, notificaciones y requisitos todavía no acordados.
- [Población, cohortes y subgrupos](POBLACION_COHORTES_SUBGRUPOS.md): fuentes que faltan para dimensionar sesiones y laboratorios.
- [Contrato propuesto de generación CP-SAT](CONTRATO_GENERACION_CPSAT.md): ejecución ficticia validada frente al flujo institucional deshabilitado.
- [Migraciones para revisión](MIGRACIONES_REVISION_PR.md): objetos, dependencias y riesgo de contabilizar 17/20 tablas.

## Anexos para pruebas y uso

- [Consulta de horarios](MANUAL_CONSULTA_HORARIOS.md): lectura de horarios registrados.
- [Exportación](MANUAL_EXPORTACION_HORARIOS.md): archivos PDF/XLSX y revisión visual pendiente.
- [Revisión visual en Windows](REVISION_VISUAL_WINDOWS.md): procedimiento de comprobación aislado.
- [Acceso por rol sin adscripción](ACCESO_POR_ROL_SIN_ADSCRIPCION.md): navegación temporal para dirección, secretaría y profesor sin exponer datos académicos privados.

## Convenciones de estado

- **Implementado:** existe código que presta el comportamiento descrito, sujeto a autorizaciones.
- **Validado en prueba aislada:** demostrado con usuarios o datos ficticios; no equivale a integración institucional.
- **Parcial:** presenta información o diagnóstico, pero no completa el flujo académico.
- **Pendiente institucional:** requiere datos, reglas, permisos, contratos o aprobación de otro equipo.
- **Deshabilitado:** el servidor rechaza la operación o la ruta no ofrece una acción institucional.