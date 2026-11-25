# Integración institucional: activación controlada

Esta revisión conserva la sincronización, el ajuste de demanda, la consolidación y la distribución automática de secciones. Se eliminan el proveedor de acceso ficticio, los nombres de bases, los puertos, las cuentas, los planes/periodos de demostración y la lista fija de ambientes del camino nuevo de integración. Los nombres históricos de las clases `*Local` se mantienen para no romper sus referencias y pruebas; su activación ya no depende del nombre de la base.

## Contratos pendientes de acreditar

- Seguridad/Core: `ProveedorEscuelasAutorizadas` y `ProveedorPermisoGestionAcademica`, sin fallback a roles o cuentas de prueba.
- Recursos: `ProveedorAlcanceRecursosAcademicos` y `ProveedorAmbientesPlanificacion`, que debe acreditar el uso de los aforos y bloqueos del plan y periodo. Los IDs se validan contra el ámbito existente. Ausencia de proveedor significa ausencia de autorización.
- Prematrícula: aprobar la captura completa institucional por periodo (incluye ceros del catálogo; no enviar subconjuntos por escuela), política de validación de solicitudes y transición oficial de cierre. `EN_CURSO` con ventanas finalizadas no se considera cierre si no se acredita la política. Se requiere una marca de cierre estable en lugar de `xmin` antes de uso productivo.

## Configuración (deshabilitada por defecto)

- `HORARIOS_INTEGRACION_HABILITADA=false`: conserva los flujos previos sin habilitar la nueva integración. Cuando se habilita, las altas/asociaciones manuales de Gestión de Horarios quedan bloqueadas fuera del servicio de consolidación, aun si los proveedores faltan. No habilitar globalmente sin una ruta de revisión de grupos especiales y migraciones verificadas.
- `HORARIOS_INTEGRACION_CIERRE_POR_TRANSICION=false`: impide consolidar o ajustar hasta que se acredite el cierre. No activar solo porque terminó una ventana.
- `HORARIOS_INTEGRACION_PERIODO=0`: sin tarea programada. Tras aprobación, indicar un periodo explícito; `horarios:sincronizar-demanda {periodo} --simular` no escribe. El comando es administrativo de consola y no imprime las filas de demanda.

## Verificación previa al merge y despliegue

Probar en PostgreSQL 17 aislado: dos escuelas y planes, cambios de demanda, rechazo de accesos cruzados y proveedores ausentes, reapertura, validaciones pendientes, concurrencia, idempotencia, propuestas obsoletas y conservación de secciones. Revisar que la consulta de demanda y el repositorio compartan la conexión y que todos los escritores de Core respeten los bloqueos. Los bloqueos actuales por periodo y tablas son conservadores; revisar concurrencia y alcance con Core. El cálculo de aforo no certifica que exista un horario factible. Los grupos LAB son estimados y la asignación docente institucional y publicación siguen pendientes.

Los manuales de ensayo local anteriores se conservan como evidencia histórica, no como instrucciones para producción. La configuración y los proveedores de demostración quedan en la rama de respaldo; no ejecutar sus scripts en la rama institucional. La nueva implementación es candidata para revisión, no una autorización de despliegue.
