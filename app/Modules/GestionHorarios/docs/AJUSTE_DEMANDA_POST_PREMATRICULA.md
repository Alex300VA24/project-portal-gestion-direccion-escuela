# Ajuste de demanda posterior a Prematrícula (prueba local)

## Pantalla de secciones

Tras el cierre oficial, los ajustes autorizados se incorporan a la consolidación de demanda efectiva y vacantes. No basta con guardar un ajuste: antes de crear secciones debe confirmarse la consolidación vigente. Una modificación posterior obliga a revisar/reconsolidar antes de nuevas altas o asignaciones. Esta compuerta adicional aún no se acredita en código. Véase [Cierre de Prematrícula y consolidación de vacantes](CIERRE_PREMATRICULA_CONSOLIDACION_VACANTES.md).

La vista local de secciones consume la demanda efectiva y calcula automáticamente la propuesta desde los aforos. La aplicación requiere confirmación explícita, y no altera cursos con responsables o actividades cuando se requieren cambios. Consulte [Planificación automática de secciones](PLANIFICACION_AUTOMATICA_SECCIONES.md).


- Dependencias: integración de PR 15 y 16, parche de agregados por turno, sincronización automática local, acceso de prueba, planificación por aforo. Migrar `ajuste_demanda` antes de probar.
- El cierre se comprueba en Prematrícula: periodo `EN_CURSO`, ventana ordinaria registrada y ninguna ventana con fin futuro. `PREMATRICULA`, `PLANIFICACION`, ausencia de ventana y `CERRADO` no autorizan ajustes. El paso a `EN_CURSO` es una interpretación conservadora del contrato actual; confirmar con el responsable de Prematrícula antes de institucionalizarla.
- En edición, no se solicita docentes mínimos: se conserva el valor almacenado y se usa 1 al crear. El estado de oferta y el ajuste de demanda tienen botones separados; cada escritura conserva sus validaciones.
- Se requiere autorización de escritura y escuela, oferta regular única abierta, demanda final sincronizada, versión y último ajuste coincidentes y ningún horario registrado. El ajuste se añade al historial con usuario, motivo, total anterior y nuevo; las instantáneas originales no se sobrescriben. Se serializa con el bloqueo de demanda y de Prematrícula.
- Oferta, consulta de secciones, propuesta de secciones e instantánea de generación consumen demanda efectiva. El conector por aforo actualizado está en `Engine/qa/planificar_por_aforo_integracion.php`; no utilizar su copia externa anterior para ajustes. Los scripts de requerimientos y CP-SAT que leen la instantánea institucional reciben los nuevos totales. La huella global incluye los datos efectivos; la versión de demanda de origen no cambia por ajustes. Si un ajuste reduce el total por debajo de las preferencias declaradas, no se inventa reparto de turnos: las preferencias efectivas se dejan sin especificar y las originales quedan en la instantánea.
- Una nueva versión de origen deja los ajustes anteriores fuera de vigencia. Una reapertura suspende su aplicación mientras el cierre no esté confirmado; si se vuelve a cerrar sin cambiar la versión, reaparece el ajuste existente. La invalidación por evento de reapertura y la revisión de impacto de horarios se deben acordar para producción.
- No altera automáticamente secciones, docentes, matrículas ni horarios. Si la demanda excede los cupos, revisar la propuesta antes de generar. Si ya existen actividades o asignaciones, el conector de aforo conserva sus bloqueos y exige revisión; no los elude.
- El servicio se limita al entorno local/testing, PostgreSQL local puerto 5433, base `portal_integracion_pm_gh`. No debe habilitarse en producción ni sustituir el contrato institucional.

## Pruebas de aceptación pendientes en Laravel/PostgreSQL

1. Abrir oferta durante Prematrícula: solo lectura de demanda. Intentar una llamada directa de guardado: rechazo.
2. Finalizar todas las ventanas y transicionar oficialmente a `EN_CURSO` en datos de prueba, sincronizar demanda. Ajustar con motivo: nuevo historial y total efectivo, original intacto.
3. Cambiar la versión de origen o guardar con modal desactualizado: rechazo o suspensión de ajuste previo.
4. Reabrir: bloquear edición y usar origen. Verificar que no se modificaron las secciones ni los horarios.
5. Comprobar un aumento que requiera más secciones, propuesta por aforo y entrada CP-SAT. Probar usuario sin permiso y escuela no autorizada.
