# Manual de Usuario del Módulo de Bienestar Estudiantil
**Portal de Gestión de Dirección de Escuela — Escuela de Informática UNT**

---

## 1. Cuentas de Demostración Disponibles

| Rol del Sistema | Correo de Acceso | Contraseña | Permiso Spatie | Superficie Principal |
|---|---|---|---|---|
| **Estudiante** | `alumno@portal.local` | `Portal2026!` | `bienestar.solicitar` | `/bienestar/informacion` |
| **Docente** | `profesor@portal.local` | `Portal2026!` | `bienestar.reportar` | `/bienestar/profesor` |
| **Personal de Bienestar** | `secretaria@portal.local` | `Portal2026!` | `bienestar.gestionar` | `/bienestar/panel` |
| **Director de Escuela** | `director@portal.local` | `Portal2026!` | `bienestar.estadisticas` | `/bienestar/director` |
| **Público / Visitante** | *(Sin iniciar sesión)* | N/A | Acceso público | `/bienestar/informacion` |

---

## 2. Guía de Uso por Rol

### 2.1. Público y Estudiante (`alumno@portal.local`)
1. **Acceso al sitio informativo (Público):**
   - Ingrese a la ruta pública: `http://localhost:8000/bienestar/informacion`.
   - Consulte los artículos sobre causales de separación (Art. 102), carga crediticia regular (Art. 99.8), reserva de matrícula (Art. 100.11) y régimen de sanciones (Art. 101).
   - Haga clic en **"Leer detalle normativo"** para ver las explicaciones en lenguaje sencillo y la fuente reglamentaria correspondiente.
2. **Gestión de Solicitudes de Apoyo:**
   - Inicie sesión con `alumno@portal.local` / `Portal2026!`.
   - Diríjase a `http://localhost:8000/bienestar/alumno/solicitudes`.
   - Presione **"+ Nueva Solicitud de Apoyo"** para solicitar beneficios como Comedor Universitario o Beca de Exoneración de Matrícula fundamentando el motivo.
   - Revise el estado de su solicitud (`En Revisión`, `Aprobada`, `No Aprobada`) y la fecha de resolución.
3. **Consulta de Citas de Consejería:**
   - Ingrese a `http://localhost:8000/bienestar/alumno/citas`.
   - Visualice sus citas programadas con fecha, hora, modalidad (Presencial/Virtual) y estado de atención.
4. **Restricción de privacidad:**
   - Las notas clínicas privadas y diagnósticos de especialistas permanecen cifrados y reservados para el personal autorizado.
   - El estudiante no tiene acceso a paneles administrativos ni docentes (devuelve `403 Forbidden`).

---

### 2.2. Docente (`profesor@portal.local`)
1. **Inicio de sesión:**
   - Inicie sesión con `profesor@portal.local` / `Portal2026!`.
   - Diríjase a `http://localhost:8000/bienestar/profesor`.
2. **Consultar mis reportes emitidos:**
   - Visualice la lista de estudiantes reportados con su código universitario, curso, prioridad y el estado administrativo de la atención en Bienestar (`Pendiente de Revisión`, `En Atención / Citado`, `Atendido por Bienestar`).
3. **Emitir un nuevo reporte de riesgo:**
   - Haga clic en **"+ Reportar Estudiante en Riesgo"** (`/bienestar/profesor/reportar`).
   - *Opción A:* Seleccione un estudiante de su nómina del curso (datos precargados desde la matrícula).
   - *Opción B:* Busque un estudiante por su **código universitario exacto** (ej. `1021400222`) y presione **"Verificar Código"**.
   - Indique la asignatura, nivel de prioridad (Alta, Media, Baja), motivo breve y detalle explicativo de la situación observada.
   - Presione **"Enviar Reporte a Bienestar"**.

---

### 2.3. Personal de Bienestar (`secretaria@portal.local`)
1. **Inicio de sesión:**
   - Inicie sesión con `secretaria@portal.local` / `Portal2026!`.
   - Diríjase a `http://localhost:8000/bienestar/panel`.
2. **Gestión del Panel de Alertas:**
   - Revise los indicadores superiores (alertas pendientes, casos abiertos, alta prioridad).
   - Filtre las alertas por origen (Detección Automática vs Reporte Docente), nivel de prioridad o estado.
   - En la fila del estudiante, presione **"Atender / Gestionar"**.
3. **Atención de Alerta:**
   - Revise la ficha del estudiante y los antecedentes o detalles técnicos de la regla.
   - Elija una de las tres acciones resolutivas:
     * **Abrir Caso:** Seleccione el tipo de caso (Riesgo Académico, Socioeconómico) y redacte el diagnóstico inicial.
     * **Agendar Cita Directa:** Seleccione fecha/hora y modalidad (Presencial/Virtual). La cita se registra directamente en la agenda de Bienestar.
     * **Descartar Alerta:** Justifique los motivos por los cuales no procede intervención.
4. **Derivación a Especialistas Externos:**
   - Dentro del caso abierto, presione **"+ Nueva Derivación a Especialista"**.
   - Seleccione un especialista del catálogo (Dra. Silvia Vargas - Psicología, Dr. Jorge Alva - Medicina) o indique una institución externa de salud.
   - Ingrese el motivo de interconsulta y marque obligatoriamente la casilla de **"Consentimiento Informado del Estudiante"**.
   - Al registrar, el sistema permite visualizar e imprimir la **Ficha Oficial de Derivación Interdisciplinaria** con formato formal A4 y líneas de firma.
   - Al recibir la respuesta del especialista, registre el informe de contrarreferencia en la sección de seguimiento.
5. **Administración de Artículos Normativos:**
   - Ingrese a `/bienestar/informacion`.
   - Presione **"+ Redactar Artículo"** para crear borradores o publicar nuevas directivas normativas. El contenido se sanitiza automáticamente.
6. **Creación de Alertas Manuales:**
   - Desde el panel, presione **"+ Nueva Alerta Manual"** (`/bienestar/panel/alertas/crear-manual`).
   - Ingrese el código universitario del estudiante, periodo lectivo, nivel de prioridad y motivo detallado. Esta alerta tiene origen `manual_bienestar` y no se ve alterada por el comando automático.
7. **Evaluación de Solicitudes de Apoyo:**
   - Desde el panel, presione **"Solicitudes de Apoyo"** (`/bienestar/panel/solicitudes`).
   - Evalúe las peticiones de beneficios estudiantiles dictaminando su estado (`Aprobada` o `Rechazada`).
8. **Resultado Externo de Derivación:**
   - Cuando el especialista o entidad externa entregue su informe de contrarreferencia, regístrelo en la derivación correspondiente. El contenido clínico se almacena con cifrado robusto en reposo.

---

### 2.4. Director de Escuela (`director@portal.local`)
1. **Inicio de sesión:**
   - Inicie sesión con `director@portal.local` / `Portal2026!`.
   - Diríjase a `http://localhost:8000/bienestar/director`.
2. **Visualización de Estadísticas Consolidadas:**
   - Filtre por el periodo académico deseado (ej. `2026-I`).
   - Examine los totales consolidados:
     * Alertas automáticas del sistema vs reportes docentes.
     * Distribución por causal de riesgo (tercera matrícula, cuarta matrícula, inasistencias críticas, créditos insuficientes).
     * Distribución por estado de resolución.
3. **Garantía de K-Anonimato:**
   - Verifique que cualquier casilla con conteo menor a cinco casos figure rotulada con la leyenda **"menos de 5"**.
   - No existen listados nominales ni identificadores individuales, protegiendo integralmente la identidad de los estudiantes ante la Dirección.
