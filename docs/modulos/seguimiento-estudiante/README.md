# Integración de Seguimiento al Estudiante

Fase 1 completada. La fase 2 tiene una primera adaptación validada en el entorno local, con comprobación pendiente en la imagen Docker de destino. La etapa 3 cuenta con una primera ampliación validada y un mapa de cobertura. El cierre estructural por flujo sigue pendiente.

La entrega solicitada comprende el código de SSE-EPI adaptado al portal, las definiciones de estructura necesarias y la documentación esencial. La carga de registros, cuentas, archivos históricos y datos de demostración queda fuera de esta entrega por indicación del usuario. Las pruebas con registros sintéticos se ejecutarán en una base y almacenamiento descartables separados del destino.

## Documentación esencial

- [Datos y accesos](DATOS_Y_ACCESOS.md) recoge las diferencias de estructura, identidades y permisos que debe resolver el código.
- [Compatibilidad y configuración](COMPATIBILIDAD_Y_CONFIGURACION.md) identifica las versiones de destino, parámetros propios y puntos compartidos que requieren coordinación.

La documentación técnica del módulo se mantiene en esta carpeta. Se incorporarán aquí las decisiones y los resultados de aceptación conforme se implementen. Los manuales históricos, respaldos y evidencias completas del proyecto original conservan su referencia en SSE-EPI.

## Versiones de referencia

| Proyecto | Referencia revisada | Uso |
| --- | --- | --- |
| SSE-EPI | `main`, commit `0fe2fbe` | Origen del código |
| Portal | `feature/seguimiento-estudiante`, commit `eebebd8`, creada desde `develop` | Destino de integración |

La fase 1 fijó `0fe2fbe463ad2231eab6f0b8f80326f2c96fcb60` como origen local y `eebebd81261cffca976cdbb5048413826aaca82b` como base del portal. `git diff main HEAD` dio cero diferencias en SSE-EPI. GitHub confirmó que el `develop` del portal coincide con esa base. La consulta de `main` remoto de SSE-EPI devolvió 404, por lo que la referencia de origen se verificó con la copia local.

## Organización del código

| Recurso | Destino |
| --- | --- |
| Controladores, modelos, servicios, validaciones y políticas | `app/Modules/SeguimientoEstudiante/` en sus capas correspondientes |
| Rutas web y API | `app/Modules/SeguimientoEstudiante/Routes/` |
| Migraciones de estructura propias | `app/Modules/SeguimientoEstudiante/Database/Migrations/` |
| Vistas y fragmentos propios | `resources/views/modules/seguimiento-estudiante/` |
| Pruebas de integración y unidad | `tests/Feature/SeguimientoEstudiante/` y `tests/Unit/SeguimientoEstudiante/` |
| Documentación | `docs/modulos/seguimiento-estudiante/` |

Las clases adicionales necesarias, como trabajos de cola y comandos, permanecerán dentro de `app/Modules/SeguimientoEstudiante/` con su namespace modular. Su registro, la carga de configuración y las entradas de recursos compilados se definirán como puntos explícitos de integración con el portal. Se reutilizarán las entidades de `app/Shared` y los contratos acordados con otros módulos.

## Plan de ejecución

### 1. Fijar el inventario y las reglas

1. Comprobar el estado de Git y fijar los commits de trabajo.
2. Inventariar pantallas, rutas, servicios, tablas consultadas, configuración, documentos, trabajos y comandos utilizados por cada función.
3. Relacionar cada proceso de SSE-EPI con las tablas y contratos del portal.
4. Registrar las decisiones pendientes de identidad, permisos, períodos, fuentes y parámetros.

Resultado de la fase 1. El [inventario por flujo y sus dependencias](DATOS_Y_ACCESOS.md#inventario-verificado-por-flujo) identifica las funciones de origen, el destino previsto y la comprobación que acompañará a cada entrega. El [registro de decisiones](COMPATIBILIDAD_Y_CONFIGURACION.md#registro-de-decisiones) indica qué falta resolver antes de habilitar los flujos correspondientes.

### 2. Adaptar versiones y dependencias

1. Adaptar el código a Laravel 13, PHP del entorno del portal, PostgreSQL 17 y Vite 7.
2. Revisar las dependencias que realmente usa el módulo y su compatibilidad con el proyecto de destino.
3. Definir la ubicación de formatos institucionales, catálogos y herramientas requeridas durante la ejecución.
4. Registrar los parámetros del módulo y los cambios compartidos necesarios.

La aceptación exige cargar las clases y compilar los recursos con las dependencias del portal. La imagen de ejecución debe poder resolver las herramientas de las funciones incluidas.

El generador de PDFs de nivelación ya utiliza Laravel 13, Dompdf y las vistas del módulo. El [resultado de las comprobaciones](COMPATIBILIDAD_Y_CONFIGURACION.md#validación-de-la-fase-2) identifica la cobertura local y las dependencias que se probarán al incorporar sus respectivos flujos.

### 3. Definir la estructura de persistencia

1. Reutilizar las tablas existentes que cubren cada proceso.
2. Diseñar migraciones nuevas para los atributos o relaciones propios que falten.
3. Definir la persistencia de los estados operativos actualmente guardados en JSONB, conservando sus reglas de bloqueo y reintento.
4. Documentar claves, estados, campos opcionales, fuentes, responsabilidad y autorización por registro.

La aceptación exige poder construir la estructura en una base de pruebas vacía y repetir la aplicación de migraciones. Esta fase prepara estructura y código, con la carga de datos del sistema original fuera del alcance.

El [esquema de persistencia](DATOS_Y_ACCESOS.md#estructura-incorporada-en-la-etapa-3) incorpora seis tablas. Se verificó creación, segunda ejecución, reversión de las seis adiciones y reaplicación en PostgreSQL 18.6 aislado. La verificación de destino en PostgreSQL 17 sigue pendiente.

El [mapa de tablas y atributos](DATOS_Y_ACCESOS.md#mapa-de-tablas-y-atributos-de-sse-epi-al-monolito) cubre las 521 columnas del origen reconstruido. Para cerrar el diseño estructural hay que resolver los campos sin destino y concretar los contratos de datos que usa cada flujo. Las primeras ampliaciones de tutoría y atenciones están verificadas. El siguiente bloque es ingreso y procedencia, seguido de los demás pendientes propios por flujo. Las decisiones de identidad y los contratos con otros módulos se pueden trabajar en paralelo con las ampliaciones propias.

### 4. Adoptar la identidad y autenticación del portal

1. Resolver la relación entre la cuenta autenticada y la persona, estudiante o docente correspondiente.
2. Adaptar el contexto académico del módulo a Spatie, la escuela y el período.
3. Aplicar permisos por acción y políticas por expediente.
4. Sustituir enlaces y controladores de acceso propios por el flujo de autenticación del portal.
5. Retirar el acceso duplicado de la versión integrada después de verificar la entrada desde el portal.

La aceptación exige probar sesión válida, ausencia de sesión, permiso insuficiente, identidad sin vinculación y acceso a un expediente ajeno. Las cuentas de prueba se crean únicamente en el entorno descartable de validación. El aprovisionamiento institucional de roles y cuentas se acordará con la administración del portal.

El [acceso inicial](DATOS_Y_ACCESOS.md#acceso-implementado-en-la-fase-4) ya usa la sesión del portal y limita la ficha académica al propio estudiante o a un tutor con permiso y asignación vigente. La validación se hizo con PostgreSQL 18 aislado. Los demás perfiles y acciones se integrarán al implementar sus recorridos.

### 5. Incorporar los procesos por entregas

| Entrega | Función | Criterio de aceptación |
| --- | --- | --- |
| A | Entrada al módulo y expediente | La cuenta del portal consulta exclusivamente su alcance autorizado |
| B | Recepción de Secretaría | El flujo admite validación, aplicación, reintento y recuperación en pruebas aisladas |
| C | Tutoría y atención | Se conservan asignación, programación, participantes, compromisos e historial |
| D | Consejería y alertas | Se aplican criterios y alcance académico, con evidencia y estados verificables |
| E | Ingreso y nivelación | Se conservan puntajes, brechas, aprobación, participación y resultados |
| F | Planes, informes e indicadores | El resultado mantiene fuente, población, corte, revisión y respaldo |
| G | Continuidad y cierre | Se conservan reglas de continuidad, cierre y rectificación del proceso |

Cada entrega trasladará juntos sus servicios, rutas, validaciones, vistas y pruebas. Las consultas usarán nombres de tabla calificados y los contratos definidos para datos externos al módulo.

La fase 5 sigue abierta. Los flujos disponibles y sus permisos se describen en [Datos y accesos](DATOS_Y_ACCESOS.md#solicitud-de-tutoría-en-la-fase-5).

| Entrega | Implementado en el portal | Pendiente para la aceptación |
| --- | --- | --- |
| A | Entrada, vínculo académico y expediente propio o asignado. | Consejería aún requiere su alcance operativo. Secretaría y Coordinación usan designaciones propias del módulo por escuela y período. |
| B | Prevalidación de CSV y lotes trazables de matrículas, notas y asistencia. Incluye referencias institucionales, revisión por escuela y período y confirmación de la última versión válida. | Acordar la entrega, aplicación y recuperación con Gestión de Matrículas. |
| C | Solicitud, programación, petición de cambio, reprogramación, cancelación, registro y corrección de la atención, participantes, cumplimiento del compromiso, avisos dentro del módulo, alta y reasignación manual por Coordinación con habilitación y capacidad. | Finalización sin relevo, asignación automática, seguimiento longitudinal y cruce con la agenda de Consejería. |
| D | Consulta de alertas SAT registradas en el expediente del tutor autorizado. | Evaluación con criterios aprobados y evidencia por sección, agenda de Consejería y derivaciones con respuesta de Bienestar. |
| E | Consulta personal del perfil, brechas, inscripciones, asistencia y calificaciones publicadas. | Recepción y procedencia de puntajes, diagnóstico, aprobación y publicación de ofertas, inscripción y recepción de resultados de la fuente autorizada. |
| F | Borrador, presentación y revisión versionada del aporte del tutor por Coordinación, con población de la escuela y período. | Plan general, informes, indicadores I1–I8 y publicación acordada con Calidad. |
| G | Consulta de la situación académica que ya figura en el expediente del tutor. | Evidencia oficial de continuidad, reglas y bloqueo de cierre, rectificación y canalización de casos. |

Los flujos de prueba usan una instancia aislada. Las migraciones de las entregas agregan estructura. Los contratos institucionales y la provisión de cuentas, catálogos y datos se tratan aparte.

### 6. Integrar recursos y ejecución automática

1. Adaptar las entradas JavaScript al inicio de Livewire y Alpine del portal.
2. Resolver la carga y descarga privada de formatos, adjuntos y documentos generados.
3. Registrar los trabajos y comandos del módulo y definir su ejecución programada.
4. Preservar la consistencia de transacciones, reservas y reintentos de importación.
5. Documentar almacenamiento persistente, configuración de correo y dependencias opcionales.

La aceptación incluye compilación, descarga autorizada de un documento sintético, ejecución del trabajador y recuperación de un trabajo interrumpido en el entorno de pruebas.

#### Recursos y tareas integradas

- Los formularios usan el arranque de Alpine y Livewire del portal. `npm run build` compila los recursos con la entrada compartida.
- El expediente permite descargar la constancia personal de Tutoría atendida. Nivelación permite descargar el resumen propio de una inscripción publicada, el programa para docentes o Coordinación dentro de su ámbito y el informe final para Coordinación cuando el cierre está publicado. El programa muestra grupos, sesiones y criterios registrados. El resumen contiene únicamente calificaciones publicadas. Todos los PDF se generan al solicitarse, con Dompdf y el escudo local.
- Los materiales PDF de Nivelación usan el disco privado `local`, bajo `storage/app/private/seguimiento-estudiante/nivelacion/`. Coordinación puede adjuntar, consultar, publicar u ocultar archivos en su ámbito con los permisos `seguimiento-estudiante.nivelacion.documentos` y `seguimiento-estudiante.nivelacion.materiales.gestionar`. La carga requiere un usuario institucional activo correspondiente a la persona vinculada, conforme a la FK de `comun.archivo.subido_por`. Admite PDF de hasta 5 MB y conserva metadatos y SHA-256. Los estudiantes descargan materiales publicados mediante su propia inscripción. En un despliegue con varias instancias, el portal debe servir el mismo volumen privado persistente y respaldarlo con el catálogo.
- `seguimiento-estudiante:recordatorios --days=1` despacha el recordatorio interno de citas de Tutoría. El planificador lo ejecuta diariamente a las 08:00 de Lima. Usa la conexión de cola del portal y la cola `seguimiento-estudiante`. Con conexión `database`, el trabajador debe consumir esa cola, por ejemplo con `php artisan queue:work database --queue=seguimiento-estudiante --timeout=60 --tries=3`. El trabajo tiene un límite de 45 segundos. Con ese trabajador, mantén la reserva del portal de 90 segundos para que supere ambos límites y permita recuperar reservas vencidas. `php artisan schedule:run` forma parte de la operación del planificador del portal. El aviso conserva una clave por cita, fecha y destinatario para que el reintento sea idempotente.

El programa y el informe de gestión requieren una escuela explícita para el acceso de Coordinación. El informe requiere estado `Finalizado` o `Cerrado`, fecha de publicación del cierre y sus apartados de contexto, metodología, ejecución, logros, análisis, conclusiones, recomendaciones y referencia. Las migraciones agregan los campos. La edición y aprobación del cierre corresponden al flujo de Nivelación de la fase 5.

Pasaron 76 pruebas en PostgreSQL 18 aislado. La suite comprueba la privacidad de las cuatro descargas, publicación y ocultamiento de adjuntos, y un trabajador de base de datos que recupera una reserva vencida y evita duplicados. También se verificaron las migraciones 18 y 19 en ambos sentidos y se revisó visualmente un PDF sintético de cada formato.

El recordatorio funciona en la bandeja del portal. El envío de correo, si se habilita después, requiere un transporte y remitente institucional configurados por el portal. La recuperación de lotes de Secretaría, los trabajos de Consejería, el recálculo de alertas y la actualización automática de asignaciones, Nivelación y continuidad dependen de completar esos flujos y sus fuentes autorizadas. La fase 6 avanza con los recursos disponibles y esos trabajos siguen pendientes.


### 7. Validar y preparar el PR

1. Ejecutar las pruebas del módulo con PostgreSQL 17 y las pruebas de aislamiento del portal.
2. Comprobar accesos por escuela, período, asignación y recurso.
3. Probar validación, estados vacíos, errores de fuente, concurrencia y reintentos.
4. Verificar el recorrido normal en la imagen de ejecución del portal.
5. Revisar los archivos de la entrega y actualizar estos documentos con los resultados y pendientes reales.
6. Preparar un PR hacia `develop` con objetivo, cambios, pasos de prueba e impacto.

La validación del alcance actual y la descripción lista para GitHub están en [Validación y borrador de PR](VALIDACION_PR.md). El PR entregará código, estructura y documentación. La ejecución de pruebas se dirigirá a recursos descartables. La aplicación de estructura o configuración sobre un entorno compartido se tratará como una etapa de despliegue diferenciada, sujeta a la coordinación del portal.

## Uso de Docker en esta planificación

Docker sigue siendo parte del entorno de ejecución previsto por el portal. Permite comprobar PHP, herramientas, recursos compilados y PostgreSQL con la configuración de destino. Su uso para validar se dirigirá a una instancia aislada, con nombre de proyecto, puertos, volumen y almacenamiento propios.

El arranque actual de la aplicación ejecuta `module:migrate all --force`. Por ello, la comprobación de arranque se realizará sobre la instancia descartable definida para las pruebas. Los datos sintéticos de esas pruebas pertenecen exclusivamente a esa instancia.

## Referencias

- [Guía del monolito para desarrollar módulos](../../../GUIA_MODULOS.md).
- [Arquitectura del portal](../../architecture.md).
- [Base de datos compartida](../../base_de_datos.md).
- [Diseño del portal](../../DESIGN.md).
- [Convenciones Git](../../PC-Convenciones-Git.md).
- [Origen SSE-EPI fijado para esta revisión](https://github.com/zoldrix05/SSE_EPI/tree/0fe2fbe).
