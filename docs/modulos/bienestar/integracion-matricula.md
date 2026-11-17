# Especificación de Integración con el Módulo de Matrícula
**Portal de Gestión de Dirección de Escuela — Escuela de Informática UNT**

---

## 1. Contrato de Integración (`FuenteAcademicaInterface`)

Para asegurar la total independencia entre el equipo de desarrollo de Bienestar y el equipo de Matrícula, la comunicación se realiza mediante una interfaz abstracta y un DTO inmutable.

```php
namespace App\Modules\Bienestar\Contracts;

interface FuenteAcademicaInterface
{
    /**
     * Retorna los registros académicos para evaluación automática de riesgo en un periodo.
     * @return list<RegistroAcademicoDTO>
     */
    public function obtenerRegistrosPeriodo(string $periodo): array;

    /**
     * Retorna los estudiantes a cargo de un docente en un periodo lectivo.
     * @return list<array{codigo: string, nombres: string, apellidos: string, curso: string, creditos: int}>
     */
    public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array;

    /**
     * Búsqueda puntual y exacta de un estudiante por su código universitario.
     * @return object{id_estudiante: int, codigo: string, nombres: string, apellidos: string, escuela: string}|null
     */
    public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object;
}
```

---

## 2. Estructura del DTO (`RegistroAcademicoDTO`)

```php
namespace App\Modules\Bienestar\DTO;

final readonly class RegistroAcademicoDTO
{
    public function __construct(
        public string $codigoEstudiante,
        public string $periodo,
        public string $curso,
        public int $vecesCursado,
        public float $porcentajeInasistencia,
        public int $creditosMatriculadosPeriodo,
        public ?int $creditosPendientesEgreso = null,
        public ?float $promedioPonderado = null,
    ) {}
}
```

---

## 3. Matriz de Reglas y Casos de Borde

| Causal de Riesgo | Umbral / Condición | Prioridad | Regla Generada | Comportamiento en Casos de Borde |
|---|---|---|---|---|
| **Cuarta Matrícula** | `vecesCursado >= 4` | Alta | `cuarta_matricula` | Art. 102 Ley 30220: Causal de retiro definitivo. |
| **Tercera Matrícula** | `vecesCursado == 3` | Alta | `tercera_matricula` | Art. 102 Ley 30220: Causal de separación temporal 1 año. |
| **Inhabilitación por Inasistencias** | `porcentajeInasistencia > 30.0` | Alta | `inasistencia_inhabilitacion` | **30.00%** genera alerta preventiva;<br>**30.01%** genera inhabilitación automática. |
| **Inasistencias Preventivas** | `20.0 <= porcentajeInasistencia <= 30.0` | Media | `inasistencia_preventiva` | **19.99%** no genera alerta;<br>**20.00%** genera alerta preventiva. |
| **Créditos Insuficientes** | `creditosMatriculados < 12` | Media | `creditos_insuficientes` | **Evaluada una sola vez (`curso = 'GLOBAL'`)**.<br>- Si `creditosPendientesEgreso < 12`: **Exento** (no genera alerta).<br>- Si `creditosPendientesEgreso === null`: Alerta con estado `por_verificar`.<br>- Si `creditosPendientesEgreso >= 12`: Alerta activa. |

---

## 4. Gestión de Estudiantes Omitidos e Idempotencia

1. **Estudiantes no registrados en `core.estudiante`:**
   Si el módulo de Matrícula entrega un registro con un código que no existe en la base de datos de Bienestar/Core (ej. `9999999999`), el servicio:
   * **Omite** el registro para evitar inconsistencias relacionales.
   * Emite una advertencia formal en los logs de Laravel (`Log::warning(...)`).
   * Incrementa el contador de registros omitidos en el reporte del comando.

2. **Resolución Automática y Reapertura Controlada:**
   * Cuando una condición cesa (el estudiante regularizó su asistencia o matrícula), la alerta pasa a estado `resuelta_automaticamente` y se registra `fecha_resolucion`.
   * Si la condición reaparece posteriormente, el sistema **solo reabre** la alerta si se encuentra en estado `resuelta_automaticamente`.
   * **Regla estricta:** Si la alerta fue marcada como `atendida` o `descartada` por el Personal de Bienestar, el comando nocturno **nunca** la reabre.

---

## 5. Programación del Comando

El comando se ejecuta automáticamente a diario:
```bash
php artisan bienestar:detectar-riesgo {periodo?}
```
- **Horario:** Todos los días a las `02:00` (zona horaria `America/Lima`).
- **Configuración en ServiceProvider:**
  ```php
  $schedule->command('bienestar:detectar-riesgo')
      ->dailyAt('02:00')
      ->timezone('America/Lima');
  ```
