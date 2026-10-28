# Convenciones de Nomenclatura en Comandos Git

En este documento se definen las reglas que seguimos para nombrar **commits** y **ramas** en el proyecto.
El objetivo es mantener un historial claro, coherente y fácil de seguir.

El proyecto se desarrollará con los siguientes modulos (alias):
- pre-matricula
- bienestar
- investigacion
- seguimiento-estudiante
- web-institucional
- calidad
- gestion-horarios
- cotecu
- gestion-practicas
- tesis-proyecto-de-tesis
- gestion-matriculas
- seguimiento-egresado

---

## 📂 Estructura de Ramas

### 🔹 Ramas Principales

- **`main`** → Contiene la versión en **producción**.
- **`develop`** → Versión en **desarrollo** antes de pasar a producción.

### 🔹 Ramas Temporales (nacen exclusivamente de **`develop`**)

|  Tipo de rama  | Prefijo |  Uso |
|---------------|--------|-----|
| **🚀 Funcionalidad** | `feature/` | Desarrollo de nuevas funciones |
| **🐞 Corrección** | `fix/` | Solución de errores en producción o desarrollo |
| **🔄 Refactorización** | `refactor/` | Mejora de código sin cambiar la funcionalidad |

### 🔹 Ramas Especiales de Gestión (manejadas por el Grupo de Gestión)

|  Tipo de rama  | Prefijo |  Uso |
|---------------|--------|-----|
| **🚨 Hotfix** | `hotfix/` | Correcciones urgentes en producción |
| **📦 Lanzamiento** | `release/` | Preparación para pasar de `develop` a `main` |

### 🔹 Comando Git para crear una rama en base a otra
- **`git switch -c <nombre-nueva-rama> <nombre-rama-origen>`** → versión moderna de Git.
- **`git checkout -b <nombre-nueva-rama> <nombre-rama-origen>`** → versión antigua de Git.


## 📂 Formato de Nombre de Ramas

```text
<prefijo>/<descripcion-corta>
```

### 📖 Significado:

- `prefijo` → Tipo de rama (ej. `feature`, `fix`, `hotfix`, etc.)
- `descripcion-corta` → Breve descripción en *kebab-case* (ej. `agrega-formulario-autenticación`)

### 💡 Ejemplos:

```text
feature/modulo-seguimiento-egresado
release/error-autenticacion
```

### 💡 Ejemplos con el comando:

```text
git switch -c feature/modulo-seguimiento-egresado develop
git switch -c release/modulo-calidad main
```

---

## 📂 Formato para Mensajes de los Commits

```text
<tipo>(<modulo>): <resumen>
```

### 📖 Significado:

- **tipo**:
|  Tipo  |  Prefijo |  Uso |
|---------------|--------|-----|
| **Funcionalidad** | `feature` | Desarrollo de nuevas funciones  |
| **Corrección** | `fix` | Solución de errores en producción o desarrollo  |
| **Refactorización** | `refactor` | Mejora de código sin cambiar la funcionalidad  |
| **Tareas Varias** | `chore` | Mantenimiento , configuración, dependencias o tareas menores  |
| **Documentación** | `docs` | Cambios solo en documentación  |
| **Estilo** | `style` | Cambios de formato o estilo que no afectan la lógica del código (espacios, indentación, formato)  |
| **Migración** | `migration` | Todo cambio que implique modificar la estructura de una tabla existente mediante una migración de Laravel debe utilizarlo obligatoriamente |
| **Hotfix** | `hotfix` | Correcciones urgentes en producción **(solo Grupo de Gestión)**  |
| **Lanzamiento** | `release` | Preparación para pasar de `develop` a `main` **(solo Grupo de Gestión)**  |

- **modulo**: calidad, curricula, egresado, etc.
- **resumen**: En imperativo con un máximo de 72 caracteres aprox

### 🔹 Comando Git para realizar un commit
- **`git commit -m "<tipo>(<modulo>): <resumen>"`**

### 💡 Ejemplos:

```text
feature(egresado): agrega formulario de datos personales
fix(curricula): resuelve error al cargar archivos pdf
```

### 💡 Ejemplos con el comando:

```text
git commit -m "feature(egresado): agrega formulario de datos personales"
git commit -m "fix(curricula): resuelve error al cargar archivos pdf"
```
---

## 📂 Formato para Pull Requests (PR)

### Título:

```text
<tipo>(<modulo>): <resumen>
```

### 💡 Ejemplos:

```text
- feature(egresado): agrega formulario de datos personales
- fix(curricula): resuelve error al cargar archivos pdf
```

### 📄 Cuerpo del PR:

- **Objetivo:** Breve explicación del problema que se resuelve.
- **Cambios clave:** Lista con los cambios realizados.
- **Cómo probar:** Pasos para verificar que funciona.
- **Impacto/Riesgos:** Indicar si hay cambios importantes o cambios que pueden romper otros modulos.

---

## ⚡ Resumen rápido (Cheat Sheet)

### Rama nueva en base a otra:

```bash
git switch -c feature/modulo-seguimiento-egresado develop
```
### Commit:

```bash
git commit -m "feature(egresado): agrega formulario de datos personales"
```

### Pull Request:

```bash
Título: feature(egresado): agrega formulario de datos personales
Cuerpo:

Objetivo
Implementar el formulario de registro para que los egresados puedan actualizar sus datos personales (nombres, contacto y carrera) desde su perfil.

Cambios clave
- Se diseñó la interfaz visual del formulario con los campos obligatorios.
- Se creó el endpoint `POST /api/egresados/perfil` para guardar la información.
- Se añadieron validaciones en el frontend para evitar campos vacíos o correos inválidos.

Cómo probar
1. Iniciar sesión en la plataforma con una cuenta de rol `egresado`.
2. Dirigirse a la sección "Mi Perfil" y hacer clic en "Editar Datos".
3. Llenar el formulario con datos de prueba válidos y presionar "Guardar".
4. Verificar en la base de datos o en la interfaz que los cambios se hayan actualizado correctamente.

Impacto / Riesgos
- Ninguno crítico. Se añade una tabla nueva en la base de datos para almacenar estos datos, por lo que requiere ejecutar las migraciones.

```

---