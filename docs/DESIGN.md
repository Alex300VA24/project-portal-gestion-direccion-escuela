# Guía de diseño

Lista los valores de diseño que hoy existen en la interfaz: tipografía, color, forma y los componentes con su uso. Todo está tomado de `resources/css/portal.css` y de las vistas. La última parte explica cómo cada módulo puede elegir su forma de construir pantallas (Blade, Livewire o Inertia).

---

## 1. Tipografía

| Rol | Fuente | Pesos | Uso |
|---|---|---|---|
| Texto e interfaz | Fira Sans | 400, 500, 600, 700 | Párrafos, títulos, botones, etiquetas, formularios |
| Código y datos | Fira Code | 500, 600 | Códigos de documento, rutas, contadores, etiquetas pequeñas en mayúsculas |

Las fuentes se cargan desde Bunny Fonts con `display=swap`. Base del documento: `400 16px/1.55 'Fira Sans', sans-serif` con `-webkit-font-smoothing: antialiased`.

### Tamaños en uso

| Tamaño | Dónde se usa |
|---|---|
| `clamp(3rem, 6.4vw, 5.4rem)` | Titular de la página pública (interlineado .98, tracking `-.04em`) |
| `clamp(2.7rem, 5vw, 5rem)` | Titular del panel de contexto en el acceso (interlineado .98, tracking `-.04em`) |
| `clamp(2.6rem, 6vw, 5.2rem)` | Título del inicio del portal |
| `clamp(2rem, 4vw, 3.5rem)` | Títulos de sección de la página pública (interlineado 1.08, tracking `-.03em`) |
| `clamp(2rem, 4vw, 3.2rem)` | Módulo destacado del inicio |
| `clamp(1.6rem, 4vw, 2.7rem)` | Nombre del módulo en las tarjetas de la página pública |
| 22 px | Título de diálogo |
| 20 px / 18 px / 17 px | Subtítulos, nombre de comunidad, texto de apoyo grande |
| 16 px | Cuerpo |
| 14 px | Navegación, etiquetas de campo, pie de página |
| 13 px | Metadatos, enlaces secundarios, rutas en Fira Code |
| 12 px | Etiquetas de estado, ayudas, pestañas pequeñas |
| 11 px | Contadores y etiquetas de datos |

Pesos: 700 para títulos, botones y etiquetas de campo (el más usado); 600 para marcas, códigos y navegación; 500 para rutas en Fira Code; 400 para el cuerpo.

Los títulos grandes llevan `text-wrap: balance`. Las etiquetas pequeñas en Fira Code se escriben en mayúsculas con `letter-spacing: .06em`.

---

## 2. Color

### Paleta base

| Token | Valor | Uso |
|---|---|---|
| `--portal-ink` | `#141a33` | Texto principal |
| `--portal-muted` | `#4a5170` | Texto secundario |
| `--portal-navy` | `#17224d` | Barra lateral, bloques destacados, panel de contexto, selección activa |
| `--portal-green` | `#0d7a50` | Acción principal, tarjeta de módulo, enlaces de acción |
| `--portal-green-dark` | `#09613f` | Enlaces sobre fondo claro, hover de la acción principal |
| `--portal-canvas` | `#f4f4ef` | Fondo de página |
| `--portal-surface` | `#fff` | Tarjetas, paneles, campos |
| `--portal-surface-alt` | `#eef0f3` | Filas de ajuste, fondos de grupo |
| `--portal-border` | `#dfe3ea` | Bordes y separadores |
| `--portal-focus` | `#f2a900` | Anillo de foco |
| `--portal-danger` | `#a11d12` | Error |
| `--portal-warning` | `#8a3f06` | Advertencia |
| `--portal-success` | `#0a5f3f` | Correcto |

Otros valores fijos: fondo del acceso y de la página pública `#f7f7f2`; acento claro sobre navy `#7de5ba`; borde de campo `#bbc2ce`.

### Módulo Calidad (variables `--quality-*`)

| Variable | Claro | Oscuro |
|---|---|---|
| `--quality-ink` | `#141a33` | `#eef2f7` |
| `--quality-muted` | `#4a5170` | `#aab5c8` |
| `--quality-navy` | `#17224d` | `#c7d2fe` |
| `--quality-green` | `#0d7a50` | `#5ee0b3` |
| `--quality-surface` | `#fff` | `#172033` |
| `--quality-canvas` | `#f4f4ef` | `#101724` |
| `--quality-surface-alt` | `#eef0f3` | `#202a3b` |
| `--quality-border` | `#dfe3ea` | `#334158` |
| `--quality-focus` | `#f2a900` | `#93c5fd` |

### Contraste alto

Activado con `html[data-contrast="alto"]`.
- Claro: texto `#000`, fondo y superficie `#fff`, borde `#2b3245`, verde `#04573a`, foco `#b45f00`.
- Oscuro: texto `#fff`, fondo y superficie `#000`, borde `#c7d0e0`, verde `#7dffcf`, foco `#ffd24a`.

### Estados

| Estado | Clase | Texto | Fondo |
|---|---|---|---|
| Correcto | `is-success` | `#0a5f3f` | `#e3f3ea` |
| Advertencia | `is-warning` | `#7b3906` | `#fcefdf` |
| Error | `is-danger` | `#8e1a11` | `#fde9e7` |
| Neutro | `is-neutral` | `#4a5170` | `#eceef3` |

---

## 3. Forma, espacio y movimiento

### Radios

| Radio | Uso |
|---|---|
| 8–9 px | Enlaces de apertura, opciones de segmento, enlace de salto |
| 10 px | Campos, botones, avisos, filas de ajuste (el más usado junto con 12 px) |
| 11–12 px | Marca, pestañas, estadísticas en línea, pestañas de tipo |
| 14 px | Grupos de procesos |
| 16 px | Bloques destacados, diálogos, tarjeta de módulo |
| 999 px / 50 % | Etiquetas de estado, indicador circular de cumplimiento |

### Elevación

- Reposo: borde de 1 px, sin sombra.
- Bloques destacados: `--portal-shadow`, `0 16px 44px rgba(23, 34, 77, .09)`.
- Botón principal del acceso: `0 13px 30px rgba(13, 122, 80, .2)`.
- Tarjeta de módulo (página pública): `0 14px 40px rgba(13, 122, 80, .17)`, sube a `0 22px 54px` con hover.
- Diálogo: `0 24px 64px rgba(8, 15, 35, .35)` sobre un fondo `rgba(8, 15, 35, .58)`.

### Anchos de contenido

| Zona | Ancho máximo |
|---|---|
| Página pública | 1240 px (`min(100% - 32px, 1240px)`) |
| Inicio del portal | 1280 px |
| Pantalla de entrada de Calidad | 1120 px |
| Área de contenido de Calidad | 1500 px, página interior de 1180 px |

### Movimiento

Curva `--portal-ease: cubic-bezier(.2, .8, .2, 1)`. Duraciones: 160 ms en campos y botones, 180 ms en tarjetas, 200 ms en la barra lateral. Con `prefers-reduced-motion: reduce` se desactivan las animaciones del acceso y de la ilustración de la página pública.

### Iconos

SVG en línea, `fill: none; stroke: currentColor; stroke-width: 1.8`, extremos y uniones redondeados. Tamaños en uso: 19–21 px en botones y avisos, 34 px en tarjetas de módulo, 40 px en estados vacíos.

---

## 4. Componentes

Cada componente indica la clase real y cuándo usarlo.

### Botón principal del acceso (`.auth-submit`)
Enviar el formulario de acceso. Alto mínimo 56 px, fondo verde, texto blanco en peso 700, radio 10 px, icono de 19 px. Muestra un indicador de carga mientras se envía (`.auth-submit-loading`).

### Botones de la página pública (`.institutional-primary`, `.institutional-secondary`)
Acciones de la portada. El principal va relleno; el secundario es transparente con borde `#bbc2ce`, texto navy, radio 10 px y peso 700. Se agrupan en `.institutional-actions` con 12 px de separación.

### Botón de icono (`.quality-icon-button`)
Acciones compactas con icono o letra (menú, tema, A− / A+). Se usa también dentro de las filas de ajuste de accesibilidad.

### Enlace de apertura (`.quality-open-link`)
Abrir un documento. Alto 40 px, texto verde de 13 px en peso 700, borde `border`, radio 9 px.

### Campo de formulario (`.auth-field`, `.auth-input-wrap`)
Etiqueta de 14 px en peso 700 sobre el campo, 8 px de separación. Campo de 56 px de alto, borde `#bbc2ce`, radio 10 px, icono a la izquierda a 16 px. Hover oscurece el borde, foco usa borde verde con halo `0 0 0 3px rgba(13, 122, 80, .15)`, error usa borde `#a11d12` con `aria-invalid="true"`. El mensaje de error va debajo (`.auth-field-error`).

### Enlace de ayuda de campo (`.auth-label-row a`)
Enlace junto a la etiqueta (por ejemplo, recuperar acceso). Verde oscuro, 13 px, subrayado fino con separación de 4 px.

### Resumen de errores (`.auth-error-summary`)
Aviso al inicio del formulario cuando falla el envío. Fondo `#fde9e7`, texto `#721c16`, icono de 21 px, título en negrita y detalle de 13 px.

### Mensaje de estado (`.auth-status`)
Confirmación en el formulario. Fondo `#e3f3ea`, texto `#0a5f3f`, radio 10 px.

### Alerta del módulo (`.quality-alert`)
Mensaje de confirmación o error dentro del área de contenido, ancho máximo de 1180 px.

### Buscador (`.quality-search`)
Campo con lupa para filtrar la lista visible. Va en la cabecera del repositorio junto al conteo de resultados.

### Selector de periodo (`.quality-period-form`, `.quality-select`)
Cambiar el periodo académico de la vista actual.

### Segmento (`.quality-segment`)
Elegir una opción entre pocas (semestre, ciclo). Cada opción mide 40 px de alto, radio 9 px, borde `border`; el contador usa Fira Code de 11 px. Hover pinta borde y texto en verde. La opción activa (`aria-current="page"`) se rellena en navy con texto blanco. Agrupa en `.quality-doc-filters`.

### Pestañas de tipo (`.quality-doctabs`)
Cambiar el tipo de contenido de una página mediante enlaces. Tres columnas iguales de 64 px de alto con título y pista de 12 px. Hover y estado activo pintan el borde en verde y un fondo de 7 % de verde. En móvil pasan a una columna.

### Pestañas internas (`.quality-tabs`)
Cambiar entre vistas dentro de una página sin navegar. Contenedor con borde y radio 11 px, botones de 42 px de alto, desplazamiento horizontal si no caben.

### Etiqueta de estado (`.quality-status`)
Pastilla de 28 px de alto, texto de 12 px en peso 700, radio completo. Variantes `is-success`, `is-warning`, `is-danger`, `is-neutral`.

### Etiqueta de código (`.quality-code`) y sobretítulo (`.quality-eyebrow`)
Identificadores y rótulos en Fira Code. El sobretítulo va en 12 px, mayúsculas, color verde.

### Panel (`.quality-panel`, `.quality-panel-heading`)
Contenedor de listas y bloques de datos. Fondo `surface`, borde `border`, radio de 12–14 px, encabezado separado del cuerpo por una línea.

### Bloque de resumen (`.quality-summary`)
Cifra principal de la pantalla. Fondo navy, texto blanco, radio 16 px, sombra `--portal-shadow`. Tres columnas: indicador circular de 145 px (`.quality-score`, degradado cónico en verde claro), descripción y hechos clave (`.quality-summary-facts`).

### Estadísticas en línea (`.quality-inline-stats`)
Fila de cifras pequeñas. Borde y radio de 12 px, etiquetas de 11 px, separadas por líneas verticales.

### Tarjetas de KPI (`.quality-kpis`)
Cuadrícula de indicadores clave del inicio.

### Fila de indicador (`.quality-indicator-row`)
Nombre, progreso y estado. Cuadrícula `minmax(0, 1.1fr) minmax(150px, .75fr) auto` con 16 px de separación y 18 × 22 px de relleno. La barra de progreso es `.quality-progress`; el nivel, `.quality-level`; la medida, `.quality-measure`.

### Tabla de indicadores (`.quality-indicator-table`)
Tabla de indicadores con encabezado propio (`.quality-indicator-head`) para la vista de lista.

### Mapa de procesos (`.quality-process-group`, `.quality-process-card`, `.quality-process-grid`, `.quality-stage-strip`)
Grupos plegables de procesos con radio 14 px, tarjetas por proceso y una tira de etapas numeradas (`.quality-stage-number`).

### Lista de planes (`.quality-plan-list`, `.quality-plan-progress`, `.quality-improvement-grid`)
Planes de mejora con su avance. La cuadrícula ocupa el ancho completo cuando solo hay un estado vacío.

### Lista de documentos (`.quality-document-list`)
Filas de `48px · contenido · 170px (mín.)`, alto mínimo 112 px, 18 × 20 px de relleno y 16 px de separación. Icono de archivo (`.quality-document-icon`, 46 × 50 px), título, código y metadatos, y a la derecha estado y enlace de apertura (`.quality-file-meta`). En móvil pasa a una columna.

### Estado vacío (`.quality-empty`)
Alto mínimo 240 px, centrado, icono de 40 px en color secundario, título, y párrafo de hasta 520 px que explica cómo aparecerá contenido. Dentro de una lista o cuadrícula ocupa todas las columnas.

### Cabecera de página (`.quality-page-heading`)
Título, descripción y acciones de la página. Dentro de `.quality-page` (máximo 1180 px). El rastro de navegación es `.quality-breadcrumb`.

### Barra lateral (`.quality-sidebar`, `.quality-nav`, `.quality-nav-link`)
Fondo navy, texto `#eef2ff`. En escritorio es fija de 248 px y el contenido se desplaza con `margin-left: 248px`; en móvil es un panel de hasta 264 px (84 vw) que se desliza, con fondo atenuado (`.quality-overlay`). Cada enlace mide 46 px de alto, 12 px entre icono y texto; el selector de módulo (`.quality-module-switch`) tiene la misma altura. Marca en `.quality-brand` con `.quality-brand-mark` de 42 px.

### Barra superior (`.quality-topbar`)
Alto fijo de 72 px, pegada arriba. Botón de menú (`.quality-menu-button`, solo móvil), rastro de navegación, acciones (`.quality-topbar-actions`) y menú de usuario.

### Menú de usuario (`.quality-user-menu`, `.quality-user-trigger`, `.quality-user-panel`)
Disparador de 46 px de alto con avatar (`.quality-avatar`), nombre y rol; panel con las acciones de cuenta, incluida **Accesibilidad** (`.quality-menu-a11y`).

### Diálogo (`.quality-modal`, `.quality-dialog`)
Ocupa la pantalla con fondo atenuado (`.quality-modal-backdrop`). Panel de hasta 460 px, relleno 22 px, radio 16 px, altura máxima `100dvh - 32px` con desplazamiento interno. Cabecera con sobretítulo, título de 22 px y botón de cierre.

### Panel de accesibilidad (`.quality-a11y-body`, `.quality-a11y-row`, `.quality-a11y-actions`)
Filas de ajuste con fondo `surface-alt`, borde, radio 10 px y 14 px de relleno: título, ayuda de 13 px y control a la derecha. Ajustes: tamaño de texto (A− / A+), alto contraste, tema claro/oscuro y restablecer.

### Marca de la página pública y del acceso
`.institutional-brand`, `.portal-brand`, `.quality-gateway-brand` (marca cuadrada de 42 px, radio 11 px, Fira Code 600) y `.auth-brand` (48 px, radio `12px 12px 4px 12px`).

### Inicio del portal (`.portal-header`, `.portal-featured`, `.portal-module-list`, `.portal-module-item`)
Cabecera de 82 px con cuenta del usuario (`.portal-account`, `.portal-avatar`, `.portal-role`). Módulo destacado en navy y listado en dos columnas, cada fila de 118 px con índice, código en Fira Code, nombre y acción.

### Página pública (`.institutional-*`)
Cabecera de 78 px, portada en dos columnas con ilustración de sistema (`.system-*`), propósito, comunidad, tarjeta de módulos en verde de 170 px y pie con borde superior.

### Acceso (`.auth-shell`)
Dos columnas, `minmax(420px, .92fr)` para contexto en navy y `minmax(500px, 1.08fr)` para el formulario. El panel de contexto incluye lista de beneficios con iconos y una red decorativa (`.auth-network`).

### Enlace de salto (`.skip-link`) y texto solo para lectores (`.sr-only`)
El enlace de salto aparece al recibir foco, arriba a la izquierda, con relleno 10 × 14 px y radio 8 px.

### Componentes Blade base
En `resources/views/components/`: `primary-button`, `secondary-button`, `danger-button`, `text-input`, `input-label`, `input-error`, `modal`, `dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link`, `auth-session-status`, `application-logo`. Se usan en las pantallas de perfil y siguen utilidades de Tailwind (gris, radio `md`, texto de 12 px en mayúsculas con tracking amplio).

---

## 5. Foco, accesibilidad y adaptación

### Foco
`outline: 3px solid var(--portal-focus)` con `outline-offset: 3px` en todos los elementos interactivos. En Calidad el color pasa a `--quality-focus` (azul claro en tema oscuro). Los campos del acceso usan además un halo verde.

### Preferencias del usuario
Guardadas en `localStorage` y aplicadas sobre `<html>`:

| Preferencia | Atributo | Valores |
|---|---|---|
| Tema | `data-theme` | claro (por defecto), `dark` |
| Contraste | `data-contrast` | normal, `alto` |
| Tamaño de texto | `data-font-size` | normal, `grande` (zoom 115 %), `xl` (zoom 130 %) |

Los componentes deben crecer en alto con su contenido; evitar alturas fijas que recorten el texto ampliado.

### Puntos de quiebre en uso
900 px (aparece la barra lateral fija de Calidad), 1050 px, 820 px, 760 px (pestañas y lista de documentos pasan a una columna) y 480 px. Mínimo del documento: 320 px.

### Reglas ya aplicadas
- Contenido de cuadrículas con `minmax(0, …)` para que el texto largo no las desborde.
- Selección de texto en navy con texto blanco.
- Barra de desplazamiento fina en navy en páginas públicas y de acceso.
- `[x-cloak]` oculto hasta que Alpine inicia.

---

# Guía de frontend por módulo

El proyecto es un monolito modular: cada módulo (`app/Modules/*`) puede elegir cómo construir sus pantallas sin afectar a los demás. Las tres opciones conviven porque todas se montan sobre las mismas rutas y controladores de Laravel y comparten los estilos de este documento.

| Opción | Estado en el proyecto | Cuándo conviene |
|---|---|---|
| Blade + Alpine | Instalado y en uso (Calidad, acceso, página pública) | Lectura, listados, formularios simples |
| Livewire 4 | Instalado (`livewire/livewire`), listo para usar | Tablas y formularios dinámicos con lógica en PHP |
| Inertia + React o Vue | No instalado; pasos de instalación abajo | Pantallas con mucho estado en el navegador |

Un módulo elige una sola opción como principal y la aplica a todas sus pantallas para mantener coherencia interna. Los módulos nuevos empiezan por Blade.

## Qué comparten todos los módulos

- Estilos: `resources/css/portal.css` y las variables de la sección 2.
- Entrada de JavaScript: `resources/js/app.js`, que arranca Livewire junto con Alpine (una sola copia de Alpine).
- Rutas: `app/Modules/{Modulo}/Routes/web.php`.
- Datos: servicios del módulo (`app/Modules/{Modulo}/Services`). Controladores, componentes Livewire y páginas Inertia llaman a los mismos servicios, así que cambiar de opción no obliga a reescribir la lógica.
- Vistas Blade de módulo: `resources/views/modules/{modulo-kebab-case}/`.

## 1. Blade + Alpine

Es la opción por defecto. El controlador pide los datos al servicio y devuelve una vista.

```php
public function documents(Request $request, CalidadReadService $service): View
{
    return view('modules.calidad.documents', $service->documents(
        $request->string('tipo')->toString() ?: 'silabo',
    ));
}
```

```blade
@extends('layouts.calidad')

@section('title', 'Documentos')

@section('content')
    <div class="quality-page" x-data="{ query: '' }">
        <input x-model.debounce.200ms="query" type="search">
        <ul class="quality-document-list">
            @forelse ($documents as $item)
                <li x-show="{{ Illuminate\Support\Js::from($item['title']) }}.toLowerCase().includes(query.toLowerCase())">
                    {{ $item['title'] }}
                </li>
            @empty
                <li class="quality-empty">…</li>
            @endforelse
        </ul>
    </div>
@endsection
```

Reglas:
- `{{ }}` escapa; `{!! !!}` solo para HTML de confianza.
- El controlador prepara datos; la vista no consulta la base.
- Alpine para estado pequeño (menús, filtros sobre lo ya cargado, diálogos). Si un comportamiento crece, moverlo a una función registrada en `resources/js/app.js`.
- Un fragmento repetido más de dos veces pasa a un componente Blade en `resources/views/components/`.

## 2. Livewire

Ya está instalado. `resources/js/app.js` importa Livewire (que incluye Alpine) y lo inicia; `config/livewire.php` tiene `inject_assets => false` y los layouts incluyen `@livewireStyles` y `@livewireScriptConfig`. No hay que añadir `@livewireScripts`, y no se debe cargar Alpine por separado.

### Crear un componente de un módulo

```bash
php artisan make:livewire Calidad/ListaDocumentos
```

Genera `app/Livewire/Calidad/ListaDocumentos.php` y su vista en `resources/views/livewire/calidad/`. Para mantener el aislamiento por módulo se pueden ubicar bajo `resources/views/modules/{modulo}/livewire/` ajustando el nombre de la vista en `render()`.

```php
namespace App\Livewire\Calidad;

use App\Modules\Calidad\Services\CalidadReadService;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaDocumentos extends Component
{
    #[Url] public string $tipo = 'silabo';
    public string $busqueda = '';

    public function render(CalidadReadService $service)
    {
        $documentos = $service->documents($this->tipo)['documents']
            ->filter(fn ($d) => str_contains(mb_strtolower($d['title']), mb_strtolower($this->busqueda)));

        return view('livewire.calidad.lista-documentos', ['documents' => $documentos]);
    }
}
```

```blade
<div class="quality-repository">
    <input type="search" wire:model.live.debounce.300ms="busqueda">
    <ul class="quality-document-list" wire:loading.class="is-loading">
        @forelse ($documents as $item)
            <li wire:key="doc-{{ $item['id'] }}">{{ $item['title'] }}</li>
        @empty
            <li class="quality-empty">…</li>
        @endforelse
    </ul>
</div>
```

Usar dentro de una vista Blade existente con `<livewire:calidad.lista-documentos />`, o como página completa en las rutas del módulo:

```php
Route::get('/documentos', \App\Livewire\Calidad\ListaDocumentos::class)->name('calidad.documents');
```

Para página completa, el layout se define con `#[Layout('layouts.calidad')]` en la clase o con `component_layout` en `config/livewire.php`.

Reglas:
- Las propiedades públicas viajan al navegador: no guardar datos sensibles en ellas.
- Validar y autorizar dentro de cada acción, igual que en un controlador.
- `wire:key` en cada elemento de un bucle; `#[Url]` para filtros compartibles.
- La lógica de negocio sigue en los servicios del módulo.
- Se reutilizan las clases de la sección 4; no se crean estilos paralelos.

## 3. Inertia con React o Vue

No está instalado. Si un módulo lo necesita, se instala una vez y queda disponible para todos.

```bash
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
```

React:

```bash
npm install @inertiajs/react react react-dom @vitejs/plugin-react
```

Vue:

```bash
npm install @inertiajs/vue3 vue @vitejs/plugin-vue
```

Pasos comunes:
1. Registrar el middleware generado en el grupo `web`.
2. Crear la vista raíz `resources/views/app.blade.php` con `@vite`, `@inertiaHead` e `@inertia`.
3. Añadir un segundo punto de entrada en `vite.config.js` (por ejemplo `resources/js/inertia.jsx` o `inertia.js`) y el plugin de React o Vue. El `app.js` actual sigue sirviendo a Blade y Livewire.
4. Las páginas viven en `resources/js/Pages/{Modulo}/`.

Controlador:

```php
return Inertia::render('Calidad/Documentos', [
    'documents' => $service->documents($tipo)['documents'],
]);
```

Reglas:
- Pasar solo los campos que la página usa, no modelos completos.
- Navegar con `Link` o `router.visit()`, no con `<a>` simples.
- Formularios con `useForm`.
- Compartir usuario, mensajes y permisos desde `HandleInertiaRequests::share()`.
- Reutilizar las clases y variables CSS de las secciones 2 a 5 para que la página sea indistinguible de las de Blade.
- Como Livewire no se necesita en una página Inertia, esas páginas usan su propia vista raíz y no los layouts Blade.

## Cómo elegir

| Necesidad del módulo | Opción |
|---|---|
| Consultas, listados y fichas con filtros por enlace | Blade |
| Menús, pestañas y diálogos sin servidor | Blade + Alpine |
| Búsqueda o filtros contra el servidor sin cambiar de página, formularios con validación en vivo | Livewire |
| Editores, tableros, arrastrar y soltar, estado complejo en el cliente | Inertia + React o Vue |

Cambiar de opción en un módulo se hace pantalla por pantalla: el servicio y la ruta se mantienen y solo cambia la capa de presentación.
