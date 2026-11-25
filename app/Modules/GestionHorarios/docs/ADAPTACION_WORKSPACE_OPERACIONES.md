# Adaptación del espacio de trabajo: planificación y simulación semanal

## Operaciones incorporadas

- En Distribución de secciones, ingresar una capacidad objetivo, revisar la propuesta de un curso regular y confirmar. Se conservan todas las secciones existentes y se asignan códigos libres A–Z. La propuesta se recalcula en una transacción con bloqueo de demanda y de secciones; si cambian las fuentes, se rechaza. La creación es aditiva y no inscribe estudiantes. Cuando existe la tabla de asociaciones, se usa el servicio institucional para asociar las nuevas secciones; si no existe, quedan sin asociación.
- En la misma pantalla, seleccionar ciclo y sección semanal; abrir el editor de borrador. Mover actividades mediante selección de día/bloque o arrastrar y soltar. El cálculo conserva duración y exige bloques contiguos registrados. Permite deshacer hasta cinco cambios, restablecer y comprobar cambios del origen. Las coincidencias temporales son advertencias, no validación de todas las restricciones.
- No se guardan los movimientos del editor en PostgreSQL; se trata de una simulación de actividades existentes. No hay edición de ambiente, fijación, liberación, asignación de sesiones pendientes ni validación HARD/SOFT integrada en este bloque.

## Diferencias estructurales verificadas

El proyecto original crea actividades con duración y sesiones pendientes en el contexto de una propuesta. En el esquema institucional `actividad_academica` exige día y horas, y `detalle_horario` refiere a una actividad por ID sin almacenar una franja independiente. Modificar esa actividad podría alterar otras versiones que la referencien. Se necesita acordar el modelo de actividades sin programar y sesiones versionadas antes de habilitar persistencia del editor o generación integral. No se crean franjas ficticias.

La demanda agregada no discrimina repetición ni grupos de asesoría. El alta automática se limita a una oferta regular abierta y rechaza códigos especiales, demanda pendiente de revisión, capacidades inválidas, asociaciones ambiguas y periodos con horarios existentes. La capacidad objetivo se introduce manualmente; no certifica capacidad física ni factibilidad. No se modifican migraciones ni permisos.

## Pruebas

- `php artisan test --filter=AdaptacionWorkspaceDominioTest` (sin escritura en base).
- `php artisan test --filter=PlanificacionDemandaDemoTest` (requiere entorno testing y base local `horarios_demo_2026_ii_demanda`, catálogo QA-HORARIOS; las escrituras de prueba se revierten). Requiere el curso de ejemplo 13034 con oferta regular y demanda y sin versiones de horario, y sin asociaciones ambiguas si existe esa tabla.
- Comprobación manual en navegador: vista de secciones, capacidad objetivo, propuesta, confirmar, volver a consultar; abrir editor, mover una actividad, deshacer, intentar cruzar un receso, comprobar cambios del origen, cerrar. No usar datos institucionales para pruebas de escritura.

## Matriz de trabajo pendiente

| Original | Estado en esta entrega |
| --- | --- |
| useWorkspace | Navegación previa de cinco pestañas; planificación aditiva ahora preparada |
| useEditorSemanal | Simulación de franjas de actividades existentes; persistencia, ambientes, fijación y sesiones pendientes requieren contrato |
| useDocentesPeriodo | Consultas parciales existentes; alcance institucional de recursos no resuelto |
| useDirectorioDocentes | Directorio completo y edición sujetos al catálogo de Core y permisos |
| useDialogo | Confirmaciones Livewire; revisión completa de accesibilidad pendiente |
| CP-SAT | Motor incluido para pruebas; captura ejecutable y persistencia institucional pendientes |

No se modifica el proyecto original, las migraciones compartidas, el stash, los proveedores institucionales ni los módulos ajenos.
