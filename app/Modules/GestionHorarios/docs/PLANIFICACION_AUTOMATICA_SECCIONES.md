# Distribución automática de secciones desde Prematrícula

**Alcance:** ensayo local de integración de PR 15 y 16, plan `PLAN-2018-INF`, periodo `2026-II`, base `portal_integracion_pm_gh` en PostgreSQL local puerto 5433. No habilita generación, publicación ni autorizaciones institucionales.

## Flujo de usuario

1. Abrir Preparación académica → Secciones. Seleccionar plan y periodo.
2. La tabla agrupa cursos por ciclo y muestra demanda efectiva, secciones registradas, mínimo necesario, propuesta de población y grupos LAB y estado. Los cursos con cero demanda y sin oferta/secciones se ocultan por defecto; se pueden consultar con «Mostrar cursos sin demanda».
3. La demanda se lee de la instantánea versionada. Con el programador local activo se consulta Prematrícula cada minuto; la pantalla se refresca cada 60 segundos mientras está visible y no está confirmando cambios. Puede actualizar la consulta manualmente; esto no fuerza la sincronización del proveedor.
4. **Condición obligatoria requerida:** confirmar cierre oficial de Prematrícula, sincronizar la demanda final y consolidar demanda efectiva y vacantes. Solo entonces, si hay altas o ajustes seguros, pulsar «Revisar y crear secciones del plan». Mientras el proceso esté abierto o sin consolidar, la propuesta es solo preliminar. La confirmación lista todos los cursos con cambios del plan, aunque haya filtros de búsqueda o ciclo. Al confirmar, se crean ofertas regulares faltantes y las secciones correspondientes, vinculadas a su oferta. No se crean duplicados ni se eliminan secciones. Los casos en revisión quedan intactos.
5. Continuar a Asignación docente únicamente después de verificar que las secciones y los subgrupos necesarios corresponden a la consolidación vigente. La población estimada y grupos LAB son una propuesta no nominal; todavía no se persisten padrones, requerimientos ni sesiones. El motor de prueba obtiene la población y los requerimientos de las secciones y la demanda efectiva.

## Criterios de cálculo

- Demanda = total vigente de Prematrícula o ajuste administrativo vigente posterior al cierre. Véase [Ajuste de demanda](AJUSTE_DEMANDA_POST_PREMATRICULA.md). No se cambia el original.
- Cursos con teoría/práctica: aforo máximo de aula compatible; cursos exclusivamente LAB: máximo de laboratorio. LAB de una sección mixta se subdivide según aforo de laboratorio. Para el ensayo se leen `R01` a `R08` del catálogo histórico; un ambiente con cualquier bloqueo del periodo se excluye conservadoramente. No se garantiza disponibilidad simultánea ni se autoriza un ambiente institucional.
- Mínimo = techo(demanda / aforo). Se conservan secciones existentes, si su cantidad permite un reparto no vacío; los códigos nuevos usan letras libres A–Z. Se equilibra población; el cupo de referencia y la población no son lo mismo. Con demanda 87 y aula 63, se requieren A y B con 44 y 43 estudiantes. Si hay LAB de 20, los grupos son 20+20+4 y 20+20+3.
- Demanda cero sin oferta/secciones no genera nada. Reducción que deja secciones vacías, grupos especiales, repetición, oferta cerrada, ambigüedad de asociación, falta de aforo o cambios que afecten responsables/actividades existentes exigen revisión. Se conserva cualquier horario existente y se bloquea toda aplicación en ese periodo.

## Implementación y seguridad

**Requisito adicional pendiente de implementación:** `aplicar()` debe exigir cierre confirmado y consolidación persistida vigente; contrastar la fuente no basta. La asignación docente debe exigir secciones verificadas para esa consolidación. Véase [Cierre de Prematrícula y consolidación de vacantes](CIERRE_PREMATRICULA_CONSOLIDACION_VACANTES.md).

`DistribucionAutomaticaLocal` reutiliza `PlanificacionPorAforo`, `GestionOfertaAcademica`, `PlanificarSeccion`, `GestionOfertaSeccion` y `AjustesDemanda`. La consulta por plan verifica autorización de escuela; aplicar exige permiso de gestión, plan autorizado y entorno local aislado. El consumidor no importa clases de Prematrícula: usa `CierreDemandaPrematricula` de `app/Shared` para verificar que la fuente no cambió. La confirmación lleva huella de la propuesta y versión; se recalcula bajo bloqueo de demanda y de Prematrícula, se compara la huella de origen y se ejecutan las escrituras en una transacción. Si la fuente cambió, espere/ejecute la sincronización y revise nuevamente. No se exponen capacidades manuales en esta vista local. La pantalla anterior se conserva fuera del entorno de prueba hasta aprobar el catálogo y las reglas de Core.

## Validación reproducible

- `php artisan test --filter=DistribucionAutomaticaLocalTest` y `php artisan test --filter=PlanificacionPorAforoTest`: cálculo, conservación de identidades, ajustes, demanda cero y casos especiales.
- `php artisan test tests/Unit/ModuleStructureTest.php`: independencia de módulos.
- `php artisan horarios:sincronizar-demanda 2 --simular`: comprobar versión y fuente; mantener `php artisan schedule:work` en terminal independiente.
- Navegador: con la prueba de cuatro cursos y demanda 1 cada uno, verificar cuatro secciones A de 63 plazas, dos cursos con LAB y doce cursos sin demanda ocultos; no debe ofrecer altas si ya están cubiertos.
- Con datos de prueba nuevos: demanda 64 sin responsables → dos secciones preliminares; verificar bloqueo mientras Prematrícula esté abierta y al cierre sin consolidar. Tras sincronizar y consolidar, confirmar; modificar la fuente o los cupos y comprobar rechazo de propuesta obsoleta; repetir tras reconsolidar. Estas nuevas compuertas aún requieren pruebas e implementación. Confirmar dos veces no debe duplicar filas. Si existen actividades o responsables, verificar estado de revisión ante cambios. Probar usuario sin permiso y otro plan/periodo.
- El resultado previo de CP-SAT (OPTIMAL, 9/9 sesiones, 20 h) se obtuvo mediante CLI de solo lectura; no certifica la nueva pantalla ni la persistencia de horarios. Ejecutar pruebas Laravel/PostgreSQL y navegador antes de cerrar el desarrollo.

## Pendientes institucionales

Catálogo de ambientes autorizados por escuela y franja, tratamiento de solicitudes según validación, cierre oficial de Prematrícula, identidad y alcance de la demanda, escritura de secciones coordinada con Core, asignación docente por actividad, población nominal, generación y publicación dentro del portal. La prioridad de docentes se documenta en [Prioridad docente CP-SAT](PRIORIDAD_DOCENTE_CPSAT.md); no se carga automáticamente este orden en la base.
