# Migraciones modulares PostgreSQL

## Alcance y fuentes

El runner integra Pre Matrícula, Gestión de Matrículas, Calidad, Tesis,
COTECU, Gestión de Horarios y Gestión de Prácticas. Bienestar queda
postergado y no participa en el runner.

Orden de precedencia documental:

1. `docs/gestion_horarios/README.md` para Gestión de Horarios.
2. `docs/gestion_practicas.sql` y `docs/gestion_practicas_tablas.md` para Gestión de Prácticas.
3. `modelo_bd_modular_12_esquemas.pdf` y `Guia_Laravel_PostgreSQL_15_Esquemas_4_people.pdf` para Calidad y Tesis.
4. `esquemas_y_origen_de_tablas.pdf` identifica procedencia y entidades compartidas; es referencia, no reemplaza fuentes específicas.
5. Las migraciones existentes son el contrato conservado de Pre Matrícula, Matrícula y COTECU.

## Estructura y orden

```text
database/migrations/                         # infraestructura Laravel en public
app/Shared/Database/Migrations/              # core, seguridad y comun
app/Modules/{Modulo}/Database/Migrations/    # propiedad del módulo
app/Modules/Bienestar/Database/MigrationsPending/
```

`ModulesServiceProvider` no agrega rutas al migrador global. Laravel mezcla
las rutas y las ordena solo por nombre, lo que permitiría que una migración
modular se ejecute antes de `core`, `seguridad` o `comun`.

El comando oficial ejecuta grupos separados:

```bash
php artisan module:migrate all
php artisan module:migrate gestion-practicas
php artisan module:migrate:status all
```

Cada ejecución aplica primero `database/migrations`, después `app/Shared` y
al final el módulo solicitado. Laravel conserva un único historial en
`public.migrations`. Los archivos trasladados mantienen su nombre para que
una base existente no los repita.

## Contratos integrados

| Esquema | Tablas | Fuente |
| --- | ---: | --- |
| `prematricula` | 3 | Migraciones existentes |
| `gestion_matriculas` | 9 | Migraciones existentes y vista histórica |
| `cotecu` | 10 | Contrato existente |
| `gestion_horarios` | 15 | README y SQL del módulo |
| `gestion_practicas` | 11 | SQL actualizado, incluida `empresa_usuario` |
| `calidad` | 12 | Modelo modular PDF |
| `tesis` | 22 | Modelo modular PDF |

Dependencias compartidas añadidas:

- `core.ambiente`, `core.linea_investigacion` y `core.docente_linea`.
- `seguridad.modulo_sistema`.
- Las siete tablas de `comun`.

La autenticación Laravel y Spatie continúa en `public`. No se implementa en
esta etapa el reemplazo completo por las diez tablas documentadas de
`seguridad`.

## Funciones de verificación

- `gestion_matriculas.calcular_creditos_matriculados`.
- `gestion_practicas.calcular_nota_final`.
- `calidad.calcular_valor_indicador`.
- `tesis.calcular_nota_sustentacion`.

Las FK de un módulo solo pueden apuntar al mismo esquema, `core`,
`seguridad`, `comun` o `public`. `gestion_matriculas.id_solicitud_prematricula`
permanece como referencia lógica sin FK entre módulos.

## Bienestar postergado

La migración recibida crea 17 tablas sin nombre de esquema y usa tablas
legacy de `public` (`estudiantes`, `personas` y `profesores`). El modelo
documentado exige 18 tablas dentro de `bienestar` y contratos compartidos de
`core`, `seguridad` y `comun`. La migración y sus seis dependencias legacy se
guardan en `Database/MigrationsPending`; no se ejecutan accidentalmente.

## Docker aislado

La validación no usa ni elimina `app_postgresql_data`:

```powershell
.\scripts\validate-modular-database.ps1
```

El script usa el proyecto `portal_modular_validation`, los puertos `18000` y
`15433`, y el volumen `portal_modular_validation_postgresql_data`. Ejecuta
dos pasadas, muestra el estado, valida conteos, FK y funciones, prueba todos
los `down()` en orden inverso, reconstruye la base, inicia Apache y elimina
únicamente el entorno temporal. Use `-KeepEnvironment` para
conservarlo durante una inspección manual.

## Resultado verificado

Validado el 3 de noviembre de 2026 con PostgreSQL 17 y PHP 8.4:

- Conteos exactos: Pre Matrícula 3, Matrícula 9, COTECU 10, Horarios 15,
  Prácticas 11, Calidad 12 y Tesis 22.
- Segunda ejecución idempotente y `module:migrate:status all` sin pendientes.
- Rollback completo por módulo y reconstrucción posterior sin errores.
- Renombrado legacy `tesis_proyecto_tesis` a `tesis` conservando una tabla de
  prueba preexistente.
- Prueba SQL de integridad y las cuatro funciones PostgreSQL correctas.
- Suite Laravel: 114 pruebas y 239 aserciones correctas.
