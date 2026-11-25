# Edición de recursos (demostración local)

**No incorporar este parche al PR institucional.** Depende de ConsultaLocalDemostracion y del servidor QA 8001 ya habilitado. No modifica la autorización de producción.

- Disponibilidad: editar estado, tratamiento y bloques de un docente sintético del periodo 2026-II. Guarda en las tablas existentes; evita cambios concurrentes mediante huella de declaración, franjas y calendario; rechaza escrituras si hay horarios existentes, apertura incorporada para el docente o una declaración con autor institucional. No certifica factibilidad. Una declaración PRESENTADA sin franjas no es una declaración ausente. El significado de bloques sin registrar debe resolverse con el motor y el contrato académico.
- Prioridad: requiere ejecutar el script CLI externo preparar_prioridad_qa.php en la base aislada. Persiste revisiones JSONB en una tabla exclusivamente QA, con versión y actor de demostración. No importa los nombres ni la prioridad del original; el orden se define explícitamente. No se integra todavía con el motor CP-SAT. La interfaz permite agregar, subir, bajar, quitar y guardar.
- Prueba de dominio: EdicionRecursosDemoDominioTest (sin acceso a la base). Las pruebas de persistencia y de navegador deben ejecutarse en la instalación local.
- No modifica el esquema compartido ni habilita creación, edición definitiva, aprobación, publicación o generación institucional.
