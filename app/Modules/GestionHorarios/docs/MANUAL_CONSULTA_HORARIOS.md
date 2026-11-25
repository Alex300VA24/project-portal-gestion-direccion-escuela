# Consulta de horarios

Ingrese con una cuenta de dirección o secretaría. En Gestión de Horarios seleccione «Consultar horarios registrados», elija el periodo y después la versión del horario. Opcionalmente escriba el ciclo o el código exacto de sección. La tabla muestra únicamente los días y franjas con actividades registradas; cada tarjeta indica curso, tipo de actividad, sección, ambiente y docente confirmado cuando está disponible. Las actividades simultáneas aparecen por separado.

Si no aparecen actividades, revise los filtros. Si aparece un mensaje de error, intente nuevamente o contacte a soporte. Esta pantalla no modifica horarios ni asegura que sean factibles. El acceso público existente muestra solo la relación de horarios publicados; no existe cuadrícula pública detallada. **Como consulta distinta**, un usuario autenticado puede consultar semanalmente actividades publicadas por ciclo/sección, ambiente o docente, con controles de publicación y periodo en el servidor.

Queda pendiente mejorar los formularios que piden identificadores numéricos, sustituyéndolos por selectores con catálogos autorizados para reducir errores sin exponer recursos ajenos.

Verificación visual pendiente: en un entorno Laravel aislado con datos de prueba, ingrese como director o secretaria y abra `/gestion-horarios/administracion/horarios`. Compruebe selección de periodo y versión, filtros, actividades simultáneas y estados vacíos. Revise a 1440 y 375 píxeles, desplace la tabla con teclado y compruebe que no haya errores en consola. No use una base compartida para cargar datos de prueba.
