# Borradores independientes de demostración

No incluir este parche ni su tabla provisional en el PR institucional. Requiere la protección `ConsultaLocalDemostracion`, el catálogo aislado QA-HORARIOS y el script externo `preparar_horarios_versionados_qa.php`.

Cada propuesta congela secciones activas, actividades registradas, duraciones, docentes propuestos/confirmados y bloques horarios. Cada actividad existente se interpreta como una sesión de referencia; no se infieren subgrupos ni se crea carga académica de las secciones vacías. Las horas originales no se usan para programar las sesiones. El borrador comienza vacío y cada cambio genera una fila independiente (UUID, versión). Las versiones anteriores son de consulta; el servicio rechaza guardar sobre una versión obsoleta. Los cambios en los recursos después de crear el borrador requieren crear otra propuesta para actualizar la fuente. No modifica `actividad_academica`, `horario` ni `detalle_horario`.

La validación solo comprueba hora inicial, cobertura por bloques continuos y coincidencia de docente o de la misma sección. Es conservadora en laboratorios de una sección sin grupos identificados. No implementa disponibilidad, ambientes, restricciones H04/H21, reglas HARD/SOFT, sábado por cohorte, capacidad, aprobación ni publicación. No alimentar el motor con este contrato provisional hasta contar con un adaptador y validador completos.

Pruebas: `php artisan test --filter=HorariosVersionadosDemoDominioTest`. Integración manual: crear borrador, programar una actividad, moverla, comparar versiones, abrir V1 y verificar que no admite modificaciones. Verificar que la versión anterior conserva sus franjas y que las tablas institucionales no cambian.
