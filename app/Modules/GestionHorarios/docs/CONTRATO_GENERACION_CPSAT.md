# Contrato de generación CP-SAT — estado y decisiones pendientes

## Nota del ensayo integrado

La ejecución CLI con demanda agregada de Prematrícula produjo OPTIMAL, 9 sesiones y 20 horas, sin persistir sesiones. El nuevo cálculo de secciones no activa por sí mismo la generación institucional. Véase [Planificación automática de secciones](PLANIFICACION_AUTOMATICA_SECCIONES.md) · [Ajuste de demanda](AJUSTE_DEMANDA_POST_PREMATRICULA.md) · [Prioridad docente](PRIORIDAD_DOCENTE_CPSAT.md).


**Estado actual:** existe un motor Python/OR-Tools invocado desde PHP en pruebas ficticias. No hay generación, aprobación ni publicación institucional habilitada en el portal.

## 1. Flujo esperado

Precondición requerida: Prematrícula cerrada oficialmente, demanda final y vacantes consolidadas y vigentes, secciones verificadas y asignación docente completada. No se debe iniciar generación institucional desde una propuesta provisional o una consolidación invalidada. Las compuertas requieren implementación y pruebas. Véase [Cierre de Prematrícula y consolidación de vacantes](CIERRE_PREMATRICULA_CONSOLIDACION_VACANTES.md).

1. **Autorizar ámbito:** identificar usuario, escuela, periodo, plan y recursos compartidos permitidos.
2. **Capturar datos consistentes:** oferta vigente, secciones, asignación docente, disponibilidad, espacios, calendario, bloqueos, restricciones y demanda versionada.
3. **Completar identidades y reglas:** población y subgrupos, patrones T/P/L, sesiones físicas compartidas y prioridades; no fabricar valores faltantes.
4. **Preparar entrada:** validar contratos, huellas, versiones y esquema `HORARIOS_MOTOR_V1`.
5. **Resolver:** ejecutar CP-SAT con límites configurados y política HARD/SOFT aprobada.
6. **Validar salida independientemente:** revisar todas las sesiones y restricciones antes de aceptar una propuesta.
7. **Persistir bajo protocolo transaccional:** contrastar vigencia y bloquear cambios simultáneos; publicar solo mediante un flujo institucional autorizado.

El paso 7 y las conexiones institucionales de los anteriores **siguen pendientes**. La preparación con fuentes institucionales sigue informando faltantes y no entrega una entrada ejecutable; por separado, el transformador `paraMotor()` sí construye `HORARIOS_MOTOR_V1` para perfiles acotados con datos ficticios completos. Esa prueba no habilita generación institucional.

## 2. Alcance demostrado en pruebas

- **PHP:** adaptación de datos ficticios, validación de entrada, huellas y resultados.
- **Python:** restricciones HARD/SOFT acotadas, disponibilidad docente, aulas/laboratorios, capacidades, secciones A/B, repetición, sábado declarado y sesiones compartidas en escenarios ficticios.
- **Cola de prueba:** ejecución y persistencia aisladas, concurrencia de workers, cancelación previa y detección de errores o resultados obsoletos.
- **PostgreSQL:** instantáneas, demanda y protecciones verificadas únicamente con fixtures y bases desechables.

Estas pruebas no conectan el motor a una acción administrativa institucional. La imagen Docker actual del portal no incluye la instalación operativa de Python y OR-Tools para ese fin.

## 3. Estados del solucionador

| Estado | Interpretación | Acción admisible |
| --- | --- | --- |
| `OPTIMAL` | Solución óptima para el modelo y los límites evaluados. | Validar independientemente; no publicar automáticamente. |
| `FEASIBLE` | Solución válida encontrada sin prueba de optimalidad. | Validar y tratar como propuesta, no como óptimo. |
| `INFEASIBLE` | No existe solución bajo el modelo y los datos declarados. | Mostrar diagnóstico; no crear horario. |
| `UNKNOWN` | No se determinó factibilidad o imposibilidad. | Mantener diagnóstico y permitir revisión; no equiparar a inviable. |

Errores de infraestructura, entrada inválida, cancelación, ejecución abandonada o huella obsoleta se deben registrar de manera diferenciada y nunca mostrarse como solución.

## 4. Reglas que necesitan acuerdo institucional

- Días, franjas, recesos, feriados y autorización específica de sábado regular o de repetición.
- Política de conflictos entre letras A/B, cursos con apertura única y estudiantes compartidos.
- Población por sección, ciclo y subgrupos; capacidades y particiones de laboratorio.
- Asignación y disponibilidad docente reales; prioridades y preferencias de turno.
- Catálogo y bloqueos de ambientes compartidos; identidad de recursos entre escuelas.
- Versiones y vigencia de oferta, secciones, demanda y restricciones durante el cálculo.
- Validación y aprobación del resultado, auditoría, reintentos y protección contra carreras con otros escritores.

No confundir restricciones probadas en escenarios ficticios con reglas aprobadas para todos los planes académicos.

## 5. Límite actual de seguridad

Las rutas `create` y `store` devuelven HTTP 403, y `HorarioRequest::authorize()` es `false`. El trabajo CP-SAT existente utiliza escenarios de prueba explícitos y no modifica horarios institucionales. El contrato de adscripción y el permiso de gestión no están conectados a fuentes productivas.

Antes de habilitar generación, los responsables deben aprobar fuentes, permisos, reglas, entorno de Python, cola, protocolo de bloqueo, validador y flujo de revisión/aprobación.

**Este documento describe un diseño y resultados de prueba, no una promesa de generación institucional ya disponible.**

## Consulta institucional: alcance actual

El flujo institucional aún no está conectado: faltan proveedores aprobados para autorización y fuentes académicas, además de garantías de vigencia y persistencia. La ejecución CP-SAT validada con proveedores ficticios no acredita preparación para producción.

El transformador académico admite múltiples cursos, secciones regulares A/B y actividades de teoría, práctica y laboratorio de una misma escuela y plan; exige malla de 60 minutos, padrones explícitos, patrones y calendario compatibles. Para laboratorio requiere una etiqueta explícita por actividad en `grupos_laboratorio`, límite autorizado y laboratorio compatible. Sin partición, el grupo conserva el padrón íntegro.

Una partición explícita se declara en `particiones`, indexada por sección, con `id`, `seccion_id`, `curso_plan_id` y `grupos`; cada grupo tiene `id` y una lista `estudiantes`. La política debe declarar `PARTICION_EXPLICITA_V1`. Los grupos deben ser disjuntos y cubrir el padrón, y cada actividad debe identificar su grupo y contener exactamente sus participantes. El motor reserva por separado cada actividad; no se crea una sesión física compartida. Las horas requeridas se comprueban por subgrupo.

El perfil rechaza demanda, horarios existentes, bloqueos, restricciones adicionales y sesiones compartidas. `COMPLEMENTOS_MOTOR_ACADEMICO_V1` identifica los complementos de este perfil; su presencia y huella no acreditan autorización ni procedencia institucional. El perfil no representa repetición ni múltiples planes o escuelas y no habilita generación institucional. Los escenarios de partición se verificaron con datos ficticios, no con padrones institucionales.
