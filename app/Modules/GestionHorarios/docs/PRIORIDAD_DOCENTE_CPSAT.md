# Prioridad docente para la generación de horarios

## 1. Objetivo y alcance

Establecer el orden de prioridad docente que utilizará el algoritmo CP-SAT del módulo Gestión de Horarios al optimizar la distribución de las clases. Este orden es una configuración de calidad del horario, no una autorización para incumplir restricciones académicas obligatorias.

- **Fuente del orden:** cinco capturas de la pantalla de prioridad docente proporcionadas el 25 de noviembre de 2026.
- **Alcance:** orden general de referencia para los docentes de la escuela; se aplica a quienes tengan actividades en la generación correspondiente.
- **Estado:** orden de referencia documentado. Su carga en el portal integrado, vigencia institucional y ejecución con datos reales deben verificarse por separado.

## 2. Orden de prioridad

La posición 1 representa la mayor prioridad y la posición 23 la menor dentro de esta lista. Se conserva la escritura y el orden de los nombres tal como aparecen en las capturas; los identificadores institucionales no se deducen a partir de los nombres.

| Posición | Docente |
| ---: | --- |
| 1 | JORGE LUIS GUTIERREZ GUTIERREZ |
| 2 | JOSE ANTONIO RODRIGUEZ MELQUIADES |
| 3 | YENNY MILAGRITOS SIFUENTES DIAZ |
| 4 | CARLOS ENRIQUE CASTILLO DIESTRA |
| 5 | JOSE GABRIEL CRUZ SILVA |
| 6 | EDWIN RAUL MENDOZA TORRES |
| 7 | SALAZAR CAMPOS JUAN ORLANDO |
| 8 | ANTHONY JOSE GOMEZ MORALES |
| 9 | JOSE LUIS PERALTA LUJAN |
| 10 | RICARDO MANUEL GUEVARA RUIZ |
| 11 | IRIS AUREA CRUZ FLORIAN |
| 12 | LIZ SOFIA RAYMUNDA PEDRO HUAMAN |
| 13 | VICTOR JAIME POLO ROMERO |
| 14 | CELESTINO MEDARDO QUISPE VARON |
| 15 | JORGE DAVID BRAVO ESCALANTE |
| 16 | ARAUJO GONZALEZ CRISTIAN |
| 17 | JUAN FRANCISCO PACHECO TORRES |
| 18 | LOURDES NATHALIE CAJACHUAN TERRONES DE CORONEL |
| 19 | JORGE LUIS ALVARADO RAMOS |
| 20 | MANUEL RIGELDER ABANTO CORCUERA |
| 21 | YOSIP URQUIZO GOMEZ |
| 22 | WILMER AUFREDY APAZA CHAVEZ |
| 23 | MAX LEOVIGILDO CASTRO RODRIGUEZ |

## 3. Aplicación de la prioridad en CP-SAT

La política existente del motor se identifica como `SOFT_P01_LEXICOGRAFICA_V1`. Su implementación se encuentra en `app/Modules/GestionHorarios/Engine/python/cpsat_soft/teacher_priority.py`.

El motor conserva el modelo global de restricciones obligatorias (HARD). Una vez obtenida una solución compatible, optimiza sucesivamente los criterios de cada docente según su posición. Para cada docente el orden de criterios es:

1. `huecos`: reducir los intervalos libres entre clases.
2. `dias`: reducir los días de asistencia necesarios.
3. `preferencias`: reducir la penalización por incumplimiento de preferencias.
4. `cortas`: reducir las jornadas de una o dos horas.

Después de certificar el valor óptimo de un criterio, se conserva ese valor al optimizar el siguiente. No se congelan las sesiones concretas del docente: sus días, horas y ambientes pueden cambiar si se mantiene la calidad ya certificada y se respetan las restricciones obligatorias. Al terminar los criterios docentes, el motor aborda los objetivos generales.

Si una etapa no logra certificar su óptimo dentro del tiempo de búsqueda, la jerarquía se detiene y se conserva la mejor solución compatible disponible. No se debe presentar una solución factible como una optimización jerárquica completamente certificada.

**Ejemplo:** si dos docentes compiten por una franja compatible y las alternativas respetan todas las restricciones obligatorias, el algoritmo protege primero los valores óptimos de los criterios del docente situado más arriba. La prioridad no garantiza una hora específica ni permite asignar clases fuera de la disponibilidad obligatoria.

## 4. Reglas de administración propuestas

- Mantener una lista general editable por las cuentas administrativas autorizadas, independiente de la selección del periodo.
- Identificar cada docente mediante `core.docente.id_docente`, no por coincidencia textual del nombre. Confirmar las 23 correspondencias antes de cargar el orden.
- Validar que las posiciones sean enteras, consecutivas a partir de 1 y sin duplicados; impedir docentes repetidos.
- Enviar al motor la instantánea de prioridad con su versión y el orden completo. El motor considera los docentes presentes en las actividades de la generación; no es necesario renumerar las posiciones originales al omitir docentes sin actividades.
- Los docentes sin posición registrada no reciben una etapa lexicográfica individual bajo esta política; sus actividades siguen formando parte del modelo global. La incorporación de nuevos docentes requiere revisión administrativa del orden.
- Registrar los cambios del orden y su versión para poder reproducir una ejecución. No modificar retroactivamente horarios aprobados o publicados.
- La prioridad no sustituye la asignación docente, la disponibilidad, las capacidades de los ambientes, los bloqueos institucionales ni la compatibilidad entre cursos.

### Contrato de entrada del motor (ejemplo ilustrativo)

```json
{
  "prioridad_docente": {
    "politica": "SOFT_P01_LEXICOGRAFICA_V1",
    "version": 1,
    "orden": [
      {"docente_id": "ID_VERIFICADO_1", "posicion": 1},
      {"docente_id": "ID_VERIFICADO_2", "posicion": 2}
    ]
  }
}
```

Los identificadores y la versión son ilustrativos. La entrada real debe contener la versión vigente y los identificadores canónicos que coincidan con `docente_id` en las actividades del motor; este ejemplo no es un archivo de carga.

## 5. Criterios de aceptación

| Comprobación | Resultado esperado |
| --- | --- |
| Orden inicial | Coincide con las 23 posiciones de la sección 2. |
| Identidad docente | Cada posición corresponde a un identificador institucional verificado. |
| Duplicados o posiciones discontinuas | La entrada inválida se rechaza. |
| Docente sin actividades | No participa en las etapas individuales de esa generación. |
| Conflicto entre prioridades y restricciones obligatorias | Prevalecen las restricciones obligatorias. |
| Optimización sucesiva | No empeoran los valores óptimos ya certificados. |
| Tiempo de búsqueda insuficiente | Se conserva una solución compatible, si existe, y se informa la certificación parcial. |
| Trazabilidad | Se identifica la versión del orden y los criterios efectivamente optimizados. |

## 6. Referencia técnica y pendientes

Código consultado: [`teacher_priority.py`](https://github.com/JEANPIERRECAMIL0/project-portal-gestion-direccion-escuela/blob/b342915/app/Modules/GestionHorarios/Engine/python/cpsat_soft/teacher_priority.py), revisión `b342915`.

La prueba local de integración entre Prematrícula y Gestión de Horarios utilizó cuatro docentes ficticios, por lo que no acredita la aplicación del presente orden de 23 docentes. Antes del uso institucional se debe comprobar la correspondencia de identidades, persistencia de la prioridad vigente, transmisión al motor y resultados de optimización con un escenario representativo. Este documento no modifica datos, código ni configuraciones del portal.
