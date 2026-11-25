# Compatibilidad y configuración de Seguimiento al Estudiante

Los valores observados describen las versiones revisadas en el inventario. La validación de la fase 2 distingue las comprobaciones realizadas y las decisiones pendientes.

## Versiones y dependencias

| Componente | SSE-EPI revisado | Portal revisado | Acción prevista |
| --- | --- | --- | --- |
| Laravel | `^11.0` | `^13.0` | Adaptar el código a Laravel 13 |
| PHP | Restricción `^8.2` | Restricción raíz `^8.3`, README exige 8.4.1 o superior e imagen PHP 8.4 | Verificar las dependencias bloqueadas y ejecutar con el PHP de destino |
| PostgreSQL | Imagen 18 | Imagen 17 | Validar estructura, SQL y bloqueos en PostgreSQL 17 |
| Vite | `^5.0.0` | `^7.0.7` | Compilar mediante la configuración del portal |
| PHPUnit | `^10.5` | `^12.5.12` | Adaptar y ejecutar las pruebas pertinentes |
| Alpine | Inicio propio con `Alpine.start()` | Inicio mediante Livewire | Integrar componentes en el arranque existente del portal |
| PDF | Dompdf `^3.1` | Dompdf `3.1.6` incorporado en la fase 2 | Generador de nivelación adaptado y comprobado |
| Gráficos e iconos | Chart.js y lucide-static declarados, con SVG de Lucide ya incluidos en SSE-EPI | Dependencias directas ausentes | Revisar los consumidores al trasladar las pantallas |
| Importación documental | Ejecuta `pdftotext` | Imagen sin instalación explícita de Poppler | Definir la dependencia de ejecución para esa función |
| Piloto predictivo | Python y scikit-learn | Imagen sin instalación explícita de Python | Resolver entorno y empaquetado si se incluye la función |

La compatibilidad final se verificará con las dependencias resueltas del portal. La integración incorporará las dependencias utilizadas por las funciones incluidas y sus ajustes mínimos de instalación.

## Validación de la fase 2

- Composer agregó Dompdf 3.1.6 y cuatro dependencias transitivas. Las versiones anteriores se conservaron. Laravel inició como 13.39.0. La comprobación de plataforma pasó con PHP 8.5.4 y las restricciones de producción del archivo de bloqueo admiten PHP 8.4.1.
- `npm ci` y `npm run build` compilaron los recursos actuales del portal con Node 22.22.2 y Vite 7.3.7. El espacio de trabajo completo de SSE-EPI se integrará con sus pantallas. Su registro de componentes Alpine debe ocurrir antes del inicio de Livewire del portal y compartir esa instancia.
- Pasaron 35 pruebas unitarias y 22 pruebas de rutas modulares. Se generaron programa, informe y resumen personal de nivelación con datos sintéticos en memoria. Se verificaron texto, escudo y procedencia en los PDFs mediante Poppler. La prueba de navegación que requiere SQLite queda pendiente por falta de `pdo_sqlite` en el PHP local.
- El servicio Docker rechazó la conexión. La ejecución en PHP 8.4 dentro de la imagen y la comprobación con PostgreSQL 17 quedan pendientes. La comprobación de las restricciones de Composer cubre compatibilidad declarada de PHP.
- Composer detectó siete avisos en cuatro dependencias ya presentes del portal. Dompdf y las cuatro incorporadas quedaron fuera del listado de avisos de esta ejecución.

El servicio vive en `app/Modules/SeguimientoEstudiante/Services/Nivelacion/`, con vistas propias y el escudo en `app/Modules/SeguimientoEstudiante/Resources/images/`. El PDF conserva la presentación de fechas y horas en America/Lima. El controlador que aplica el alcance del usuario se conectará al incorporar nivelación.

## Parámetros propios y compartidos

Los parámetros de negocio de Seguimiento conservan su significado y sus valores vigentes, sujetos a la revisión funcional de cada parámetro. Se documentarán su origen, justificación, alcance y forma de modificación. Las reglas institucionales globales requieren coordinación cuando entren en conflicto con una regla propia. Las versiones, autenticación e infraestructura compartidas se adaptarán al portal con los ajustes necesarios para que el módulo funcione correctamente.

| Parámetro o comportamiento | Situación observada | Decisión prevista |
| --- | --- | --- |
| Criterios SAT, estados y reglas de continuidad | Definidos en código, configuración y persistencia de SSE-EPI | Conservar el significado funcional y documentar cada diferencia con el portal |
| Cohorte inicial de nivelación | `SSE_NIVELACION_DESDE_COHORTE`, valor predeterminado 2027 | Conservar como parámetro propio y documentar su justificación |
| Período de trabajo | Año y tipo en configuración y contexto de SSE-EPI | Conservar el comportamiento de selección y relacionarlo con `core.periodo_academico` |
| Alcance institucional | Configuración de escuela y claves operativas propias | Definir si la instalación atiende una o varias escuelas y reflejarlo en acceso y claves |
| Tiempos de los trabajos | PDF 120 s, recepción 180 s, clasificación 300 s | Conservar inicialmente y medir en el entorno del portal |
| Reserva de cola | SSE-EPI 360 s y portal 90 s por defecto | Proponer conexión de cola propia del módulo, con reserva 360 s, sobre la misma conexión PostgreSQL y las tablas de infraestructura existentes |
| Identificación de la cola | Valor `default` compartido en la configuración actual | Proponer nombre `seguimiento-estudiante` y un trabajador que la atienda |
| Frecuencia de tareas | Recuperación por minuto, actualizaciones por cinco minutos u hora y recordatorios diarios | Registrar las frecuencias necesarias y evitar duplicación de procesos |
| Horas y fechas | SSE-EPI presenta America/Lima y portal usa UTC | Mantener la interpretación de las citas y definir conversiones, presentación y zona del programador |
| Fuentes y reglas habilitadas | Emisores y adaptadores sujetos a configuración institucional | Conservar el control de procedencia y documentar contratos y estados pendientes |
| Demostración | Activación explícita en local o pruebas | Mantener su uso en validación aislada |
| Cuenta y sesión | Usuario propio en SSE-EPI y usuario con Spatie en el portal | Adoptar la identidad y sesión del portal y mantener la autorización académica del módulo |
| Tablas de sesión, caché y trabajos | Infraestructura presente en ambos proyectos | Utilizar las tablas y configuración del portal |

La conexión de cola propia es una propuesta, pendiente de comprobar contra `ReceiptTaskQueue`. Ese servicio necesita confirmar el estado operativo y el trabajo de la cola en la misma transacción PostgreSQL. Se conservará esa condición y se verificará la recuperación de una entrega repetida o interrumpida. El trabajador debe tener límites compatibles con la duración máxima de los trabajos y la reserva de la cola.

El mecanismo de carga de configuración propia se definirá con namespace del módulo, mediante valores o claves `seguimiento_estudiante` y variables existentes `SSE_*` cuando se reutilicen. El registro requerido en el arranque del portal será un punto explícito de integración. Cada modificación compartida se documentará con su impacto en los otros módulos.

## Archivos y Docker

- Ubicar las plantillas institucionales de referencia F1-F7 y los catálogos requeridos en `app/Modules/SeguimientoEstudiante/Resources/` al incorporar sus consumidores. El origen usa rutas fuera de `src` y el portal excluye `docs` de la imagen. Los respaldos y documentos generados pertenecen al almacenamiento privado configurado.
- Definir almacenamiento privado persistente para futuras cargas y documentos generados al utilizar el módulo.
- Adaptar rutas de imágenes y plantillas PDF al árbol del portal.
- Definir herramientas de ejecución, trabajador, programador y configuración de correo que requieran las funciones incluidas.
- Usar un entorno Docker descartable para verificar la integración. Los dos proyectos publican PostgreSQL en 5433 por defecto, de modo que sus entornos simultáneos deben tener puertos y volúmenes diferenciados.
- Validar el arranque con la base de pruebas separada, porque la aplicación del portal ejecuta migraciones al iniciar.

## Registro de decisiones

| Decisión | Estado | Evidencia necesaria |
| --- | --- | --- |
| Conservar parámetros propios compatibles y justificar diferencias | Criterio solicitado por el usuario | Inventario por parámetro y revisión de sus consumidores |
| Adoptar versiones del entorno del portal | Criterio solicitado por el usuario | Compilación y pruebas con las versiones de destino |
| Entregar código y estructura con pruebas en recursos aislados | Alcance solicitado por el usuario | Revisión de archivos del PR y configuración de las pruebas |
| Conexión y trabajador de cola del módulo | Propuesta técnica | Pruebas de transacción, reserva, reintento y recuperación |
| Relación entre cuenta e identidad académica | Pendiente de diseño compartido | Modelo de vínculo y pruebas de acceso |
| Ubicación de formatos y almacenamiento persistente | Pendiente de diseño de ejecución | Construcción de imagen y descarga autorizada en pruebas |
| Zona horaria y alcance por escuela | Pendiente de decisión funcional | Casos de citas, cortes, selección de período y acceso |
| Fuente de matrícula, carga y notas | Propuesta de lectura mediante contratos con Matrículas y Horarios | Confirmar disponibilidad, estado oficial, secciones y procedencia antes de integrar los flujos B y D |
| Vigencia tutorial y agenda | Conservar la comprobación de habilitación, asignación y período del origen | Confirmar cardinalidad y transiciones antes de diseñar la estructura del flujo C |
| Parámetros SAT y continuidad | Mantener las reglas propias y contrastarlas con los catálogos y reglas institucionales | Identificar los criterios efectivos en código, configuración y registros autorizados antes del flujo D |
| Traspaso de derivaciones e indicadores | Los contratos con Bienestar y Calidad siguen pendientes | Verificar respuesta de derivación y publicación de mediciones con cortes y revisiones |

La fase 2 puede empezar por dependencias y compilación con las versiones fijadas. El vínculo de identidad y el alcance por escuela son dependencias de la primera entrada autenticada. Cada contrato de datos condiciona su flujo, de modo que los demás pueden avanzar con servicios aislados en pruebas mientras se define.

## Referencias

- [Plan de integración](README.md).
- [Datos y accesos](DATOS_Y_ACCESOS.md).
- [Docker del portal](../../../docker-compose.yml).
- [Dependencias del portal](../../../composer.json).
- [Configuración de colas del portal](../../../config/queue.php).
- [Configuración de colas de SSE-EPI](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/config/queue.php).
- [Configuración de nivelación de SSE-EPI](https://github.com/zoldrix05/SSE_EPI/blob/0fe2fbe/src/config/nivelacion.php).
- [Guía de actualización de Laravel 13](https://laravel.com/framework/docs/13.x/upgrade).
- [Requisitos de Dompdf](https://github.com/dompdf/dompdf#requirements).
