# Exportación de horarios

Dompdf 3.1.6 y PhpSpreadsheet 5.11.0 están incorporados localmente. La pantalla administrativa incluye acciones de descarga PDF y Excel, pero su uso institucional permanece bloqueado hasta contar con el contrato de autorización usuario–escuela. El contenedor institucional que está en ejecución todavía requiere desplegar de forma coordinada la imagen con las extensiones PHP `gd` y `zip`.

## Funcionamiento

La consulta PHP agrupa los datos por plan curricular, ciclo y código de sección. El PDF A4 horizontal muestra la universidad, logotipo disponible, facultad, escuela, periodo, plan, estado, franjas registradas y relación de asignaturas y docentes. El Excel genera una hoja por grupo, cuadrícula semanal, detalle completo y relación de asignaturas y docentes; sus textos se escriben explícitamente como texto y su área de impresión abarca la cuadrícula. Si faltan datos institucionales se indican como no registrados.

Las actividades que coinciden exactamente se conservan dentro de la misma franja. Las superposiciones parciales se muestran en franjas separadas, sin representar carriles proporcionales. La representación de sesión compartida, subgrupos, vistas por docente o ambiente y colores por curso depende de contratos y trabajo adicional. El receso académico no está confirmado y no se inventa una franja.

## Revisión

Se generaron muestras con datos ficticios desde PostgreSQL aislado. La revisión visual PDF cubre únicamente dos páginas A4 con una actividad por sección; falta comprobar un caso denso, superposiciones, saltos y nombres largos múltiples. El XLSX se valida programáticamente, pero está pendiente abrirlo en Excel o LibreOffice y revisar hoja por hoja y su vista de impresión. En un entorno de prueba aislado, abra las muestras facilitadas, verifique encabezados, todas las sesiones y detalle, compare la cuadrícula con la tabla de sesiones y revise que ningún texto quede cortado. No utilice información institucional real para estos ensayos.

## Verificación visual posterior en Excel

La muestra ficticia se abrió en Microsoft Excel en modo de solo lectura y se exportó a PDF para inspección. Se detectó un defecto: aunque el XLSX declara A4 y el área de impresión de la cuadrícula, en esta instalación Excel informó el área vacía y la vista exportada incluyó el detalle, reduciendo la cuadrícula hasta resultar poco legible; el PDF de impresión resultante fue tamaño carta. Queda pendiente identificar si intervienen la interpretación del archivo, Excel o el controlador de impresión, corregirlo y repetir la prueba. No se considera validada la impresión XLSX.

El PDF institucional de muestra se renderizó en dos páginas A4 y se revisaron ambas: el encabezado, logotipo y catálogo se vieron sin cortes para este conjunto reducido. Falta verificar contenido denso, superposiciones, grupos y nombres extensos múltiples. Las muestras no representan una comparación visual equivalente con los documentos de referencia, que no se generaron en esta revisión.
