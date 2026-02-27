# 09 — DOCUMENTACIÓN FRONTEND

> Documenta todas las vistas Blade, componentes Alpine.js, gráficos Chart.js, estilos CSS y funciones JavaScript del sistema. El frontend sigue un patrón **Server-Side Rendering** con reactividad selectiva via Alpine.js.

---

## Arquitectura Frontend

```
┌──────────────────────────────────────────────────────────┐
│                    Browser (Cliente)                     │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  Blade (SSR)          Alpine.js (reactivity)             │
│  ┌────────────┐       ┌──────────────────────┐           │
│  │ Layouts    │       │ extensionEditor()    │           │
│  │ Partials   │       │ pbxManager()         │           │
│  │ Components │       │ syncManager()        │           │
│  │ Sections   │       │ userForm()           │           │
│  └────────────┘       │ userManager()        │           │
│                       └──────────────────────┘           │
│  Chart.js (CDN)       Fetch API / Axios                  │
│  ┌────────────┐       ┌──────────────────────┐           │
│  │ Pie        │       │ CSRF Token (meta)    │           │
│  │ Line       │       │ JSON endpoints       │           │
│  │ Bar        │       │ Polling (sync status)│           │
│  │ Area       │       └──────────────────────┘           │
│  └────────────┘                                          │
│                                                          │
│  Tailwind CSS         Font Awesome (CDN)                 │
│  ┌────────────┐       ┌──────────────────────┐           │
│  │ Utilities  │       │ Iconos SVG           │           │
│  │ Custom CSS │       │ v6.5.2               │           │
│  │ Animations │       └──────────────────────┘           │
│  └────────────┘                                          │
└──────────────────────────────────────────────────────────┘
```

**Patrón de comunicación:**
- **Navegación/CRUD**: Formularios Blade estándar (POST/PUT/DELETE con CSRF)
- **Modales y interacción**: Alpine.js con `x-data`, `x-show`, `x-model`
- **Datos asíncronos**: `fetch()` con headers CSRF para JSON endpoints
- **Progreso/Polling**: `setInterval` cada 2s para estados de sincronización
- **Gráficos**: Chart.js v4 via CDN, inicializados en `DOMContentLoaded`

---

## Stack Tecnológico Frontend

| Tecnología | Versión | Propósito |
|---|---|---|
| **Blade** | Laravel 11 | Motor de plantillas server-side |
| **Alpine.js** | 3.15.3 | Reactividad del lado del cliente |
| **Tailwind CSS** | 3.4.19 | Framework de utilidades CSS |
| **Chart.js** | 4.4.1 (CDN) | Gráficos interactivos |
| **Font Awesome** | 6.5.2 (CDN) | Iconografía |
| **Vite** | (build tool) | Bundler de assets |
| **Axios** | (npm) | Peticiones HTTP (via Bootstrap.js) |

---

## Estructura de Archivos Frontend

```
resources/
├── css/
│   └── app.css                         ← Tailwind + utilidades custom
├── js/
│   ├── app.js                          ← Entry point (Alpine.js init)
│   └── bootstrap.js                    ← Axios config
└── views/
    ├── layouts/
    │   ├── app.blade.php               ← Layout principal (autenticado)
    │   ├── guest.blade.php             ← Layout para invitados (auth)
    │   ├── navigation.blade.php        ← Barra de navegación (Breeze default, no usada activamente)
    │   └── sidebar.blade.php           ← Sidebar fijo lateral izquierdo
    ├── components/                     ← Componentes Blade (Breeze)
    │   ├── application-logo.blade.php
    │   ├── auth-session-status.blade.php
    │   ├── danger-button.blade.php
    │   ├── dropdown.blade.php
    │   ├── dropdown-link.blade.php
    │   ├── input-error.blade.php
    │   ├── input-label.blade.php
    │   ├── modal.blade.php
    │   ├── nav-link.blade.php
    │   ├── primary-button.blade.php
    │   ├── responsive-nav-link.blade.php
    │   ├── secondary-button.blade.php
    │   └── text-input.blade.php
    ├── auth/                           ← Vistas de autenticación (Breeze)
    │   ├── confirm-password.blade.php
    │   ├── forgot-password.blade.php
    │   ├── login.blade.php
    │   ├── register.blade.php
    │   ├── reset-password.blade.php
    │   └── verify-email.blade.php
    ├── profile/                        ← Perfil de usuario (Breeze)
    │   ├── edit.blade.php
    │   └── partials/
    │       ├── delete-user-form.blade.php
    │       ├── update-password-form.blade.php
    │       └── update-profile-information-form.blade.php
    ├── pbx/
    │   ├── index.blade.php             ← Selector de centrales (standalone)
    │   └── setup.blade.php             ← Configuración/sincronización de PBX
    ├── users/
    │   ├── index.blade.php             ← Listado de usuarios
    │   ├── create.blade.php            ← Crear usuario + permisos
    │   └── edit.blade.php              ← Editar usuario + permisos
    ├── settings/
    │   └── index.blade.php             ← Configuración de tarifas
    ├── stats/
    │   └── kpi-turnos.blade.php        ← Dashboard KPI de colas
    ├── errors/
    │   └── 419.blade.php               ← Redirige a login (sesión expirada)
    ├── configuracion.blade.php         ← Gestión de anexos/extensiones
    ├── dashboard.blade.php             ← Dashboard vacío (placeholder)
    ├── doom.blade.php                  ← Easter egg: enlace a juego DOOM
    ├── graficos.blade.php              ← Gráficos de llamadas (pie + línea)
    ├── login.blade.php                 ← Login personalizado (standalone)
    ├── pdf_reporte.blade.php           ← Template para exportar PDF (DomPDF)
    ├── reporte.blade.php               ← Vista principal: reporte de llamadas
    └── welcome.blade.php              ← Página de bienvenida (Laravel default)
```

---

## 1. LAYOUTS

### 1.1 `layouts/app.blade.php` — Layout Principal

**Tipo:** Layout Blade con componente `<x-app-layout>`  
**Líneas:** ~90

#### Estructura:
- **HTML Head:** Meta tags, CSRF token, fuente Figtree (bunny.net), Vite assets (`app.css` + `app.js`), Font Awesome 6.5.2 CDN
- **Body:** `x-data="{ sidebarOpen: false }"` — Estado Alpine.js para sidebar mobile
- **Sidebar:** `@include('layouts.sidebar')` — Sidebar fijo izquierdo
- **Indicador de Sincronización:** Widget flotante (bottom-right) que muestra progreso de sincronización en tiempo real
- **Main Content:** Flex column con `margin-left: 16rem` para compensar el sidebar fijo
- **Header Slot:** `$header` — Cabecera de página opcional
- **Content Slot:** `$slot` — Contenido principal con animación `page-transition-slide`
- **Scripts Stack:** `@stack('scripts')` — Para inyectar JS desde vistas hijas

#### Funcionalidad JavaScript (inline):
- **`checkSync()`**: Polling cada 2 segundos a `/pbx/sync-status/{pbxId}` via `fetch`. Muestra/oculta indicador con barra de progreso animada. Cambia color según estado (azul=progreso, rojo=error, verde=completado).

---

### 1.2 `layouts/guest.blade.php` — Layout Invitados

**Tipo:** Layout Blade con componente `<x-guest-layout>`  
**Líneas:** ~35

#### Estructura:
- Layout centrado verticalmente con fondo gris claro
- Logo de aplicación (`<x-application-logo>`)
- Card blanca de max-width 448px para formularios de autenticación
- Usa Vite para assets (sin Font Awesome)

---

### 1.3 `layouts/navigation.blade.php` — Barra Navegación (Breeze)

**Tipo:** Partial incluible  
**Líneas:** ~80

#### Nota:
Esta vista es el componente de navegación por defecto de Laravel Breeze. **No se usa activamente** en la aplicación — fue reemplazada por el sidebar personalizado. Se mantiene como referencia.

#### Contenido:
- Barra superior con logo y enlace al Dashboard
- Dropdown con nombre de usuario + opciones (Perfil, Logout)
- Botón hamburguesa para móvil que controla `sidebarOpen`

---

### 1.4 `layouts/sidebar.blade.php` — Sidebar Principal

**Tipo:** Partial incluible (`@include`)  
**Líneas:** ~130

#### Estructura Visual:
```
┌─────────────────────┐
│   Central UCM       │  ← Logo (bg-gray-900)
├─────────────────────┤
│ 📞 Llamadas         │  ← Siempre visible
│ 🎯 Colas            │  ← @if canViewCharts()
│ 👤 Anexos           │  ← Siempre visible
│ 💰 Tarifas          │  ← Siempre visible
├─ ADMINISTRACIÓN ────┤  ← @if isAdmin()
│ 👥 Gestión Usuarios │
├─────────────────────┤
│ Central: [nombre]   │  ← session('active_pbx_name')
│ [Cambiar Central]   │  ← Botón indigo
│ [Cerrar Sesión]     │  ← Botón rojo
│ Nombre + Badge Rol  │
└─────────────────────┘
```

#### Menú Items:
| Item | Ruta | Condición de Visibilidad |
|---|---|---|
| Llamadas | `route('dashboard')` | Siempre (autenticado) |
| Colas | `route('stats.kpi-turnos')` | `Auth::user()->canViewCharts()` |
| Anexos | `route('extension.index')` | Siempre |
| Tarifas | `route('settings.index')` | Siempre |
| Gestión Usuarios | `route('users.index')` | `Auth::user()->isAdmin()` |

> **Nota:** El enlace "Gráficos" fue movido del sidebar a la vista principal de llamadas (`reporte.blade.php`) como un botón entre "Dashboard de Control" y "Sincronizar Ahora".

#### Sección Inferior (siempre visible):
- **Central activa**: Muestra `session('active_pbx_name')` con ícono de servidor verde
- **Cambiar Central**: Botón que lleva a `route('pbx.index')`
- **Cerrar Sesión**: Formulario POST a `route('logout')`
- **Info usuario**: Nombre + badge de rol (Administrador amarillo / Usuario gris)
- **Guest fallback**: Si no autenticado, muestra botón "Iniciar Sesión"

---

## 2. VISTAS PRINCIPALES

### 2.1 `reporte.blade.php` — Reporte de Llamadas (Vista Principal del Sistema)

**Ruta:** `GET /` y `GET /dashboard` (`route('dashboard')`)  
**Controlador:** `CdrController@index`  
**Líneas:** ~280  
**Layout:** `<x-app-layout>`

> **Esta es la vista más usada del sistema.** Es la primera que ve el usuario al ingresar (después de seleccionar central). Concentra consulta, filtrado, exportación y sincronización de CDR.

#### Flujo de Datos:

```
CdrController@index
    │
    ├── buildCallQuery($request) → Query filtrada y ordenada
    │     ├── whereDate(start_time, >=, fecha_inicio)
    │     ├── whereDate(start_time, <=, fecha_fin)
    │     ├── where(source, $anexo)  (si filtro activo)
    │     ├── tipo_llamada filter (REGEXP en userfield + source)
    │     └── applySorting($sort, $dir) con SQL custom para type/cost
    │
    ├── $totalLlamadas = $query->count()
    ├── $minutosFacturables = $query->sum('billsec') / 60
    ├── $totalPagar = sum de cost accessor (via SQL CASE WHEN)
    │
    └── return view('reporte', compact(...))
```

#### Variables recibidas:
- `$totalLlamadas`, `$minutosFacturables`, `$totalPagar` — KPIs resumen
- `$llamadas` — Paginación Eloquent de `Call`
- `$fechaInicio`, `$fechaFin`, `$anexo` — Filtros activos
- `$titulo` — Título para PDF

#### Secciones:

**a) Header + Botones de Acción:**
- Título "Dashboard de Control" con fecha de generación
- Botón "Gráficos" (indigo-600) — Solo si `canViewCharts()`. Redirige a `route('cdr.charts')`
- Botón "Sincronizar Ahora" (POST a `route('cdr.sync')`) — Solo si `canSyncCalls()`. Muestra `confirm()` antes de ejecutar
- Al hacer clic en Sincronizar: confirma con alerta, luego cambia texto a "Buscando..." con spinner, deshabilita botón

**b) Tarjetas KPI (3 columnas):**
| Tarjeta | Color | Dato |
|---|---|---|
| Total Llamadas | Azul `border-blue-500` | `$totalLlamadas` |
| Tiempo Facturable | Cyan `border-cyan-500` | `$minutosFacturables` min |
| Total a Cobrar | Verde `border-green-500` | `$totalPagar` CLP |

**c) Filtros de Búsqueda:**
- **Fecha Desde/Hasta**: `<input type="date">`
- **Anexo/Origen**: Input texto con ícono teléfono
- **Tipo de Llamada**: Toggle buttons (Salientes | Todas | Entrantes)
  - `internal` → Salientes (azul), `all` → Todas (gris), `external` → Entrantes (verde)
  - Cada botón es un `<button type="submit">` con name `tipo_llamada`
- **Exportar PDF**: Botón rojo con `onclick="pedirTituloYDescargar()"` — Solo si `canExportPdf()`
- **Exportar Excel**: Link verde a `route('calls.export')` con fechas por defecto (hoy) si no hay filtros activos — Solo si `canExportExcel()`
- **Limpiar**: Botón gris que resetea a `url('/')`

**d) Tabla de Registros CDR:**
- Columnas: Hora, Origen/Nombre, Destino, Tipo, Duración, Costo, Estado
- **Sorting**: URLs con `sort` y `dir` query params en headers clicables (Hora, Tipo, Duración, Costo)
- **Origen**: Muestra extensión + `fullname` de relación + botón editar nombre (ícono lápiz)
- **Tipo**: Badges por color (Celular=púrpura, Internacional=rojo, Interna=gris, Nacional=azul)
- **Estado**: ANSWERED=verde, NO ANSWER=rojo, BUSY=amarillo, FAILED=gris
- **Paginación**: `$llamadas->appends(request()->input())->links()`

#### JavaScript (`@push('scripts')`):

- **`editarNombre(extension, nombreActual)`**: Dispara evento Alpine `open-modal` para editar nombre local
- **`pedirTituloYDescargar()`**: 
  1. Pide título con `prompt()`
  2. Manipula el formulario temporalmente: cambia `action` a `route('cdr.pdf')` y `target` a `_blank`
  3. Envía formulario, luego restaura valores originales

---

### 2.2 `graficos.blade.php` — Gráficos de Llamadas

**Ruta:** `GET /graficos` (`route('cdr.charts')`)  
**Controlador:** `CdrController@charts`  
**Líneas:** ~130  
**Layout:** `<x-app-layout>`

#### Variables recibidas:
- `$pieChartLabels`, `$pieChartData` — Datos para gráfico de torta
- `$lineChartLabels`, `$lineChartData` — Datos para gráfico de líneas
- `$fechaInicio`, `$fechaFin`, `$anexo` — Filtros

#### Secciones:

**a) Botón Volver:**
- Botón "Volver a Llamadas" (gris) — Redirige a `route('dashboard')`

**b) 2 Gráficos (grid 2 columnas):**
| Gráfico | Tipo | Canvas ID | Datos |
|---|---|---|---|
| Llamadas por Estado | Pie | `graficoTorta` | `$pieChartLabels`/`$pieChartData` |
| Tendencia de Llamadas | Line | `graficoLineas` | `$lineChartLabels`/`$lineChartData` |

**b) Filtros:**
- Fecha inicio/fin, Anexo, botón Filtrar + Limpiar

#### JavaScript:
- Carga Chart.js via CDN (`cdn.jsdelivr.net`)
- Inicializa 2 charts con `new Chart()` en `DOMContentLoaded`
- Pie chart: 5 colores predefinidos rgba
- Line chart: línea cyan con `tension: 0.1`, eje Y desde 0

---

### 2.3 `configuracion.blade.php` — Gestión de Anexos

**Ruta:** `GET /configuracion` (`route('extension.index')`)  
**Controlador:** `ExtensionController@index`  
**Líneas:** ~796  
**Layout:** `<x-app-layout>`

#### Variables recibidas:
- `$extensions` — Paginación de Extension
- `$anexo` — Filtro de búsqueda

#### Componente Alpine.js: `extensionEditor()`

Estado del componente:
```javascript
{
    showModal: false,
    currentStep: 1,           // 1=datos, 2=desvíos
    isSaving: false,
    forwardingLoading: false,
    errorMessage: '',
    successMessage: '',
    formData: { extension, first_name, last_name, email, phone, permission, do_not_disturb, max_contacts, secret },
    forwardingData: { timetype, queues[], cfu: {dest_type, destination}, cfb: {...}, cfn: {...} },
    forwardingBackup: null,
    forwardingLoaded: false
}
```

#### Secciones:

**a) Header + Botones de Acción:**
- Botón "Actualizar IPs": `POST route('extension.updateIps')` — Solo si `canUpdateIps()`
- Botón "Sincronizar Ahora": Click muestra `confirm()` y luego dispara `iniciarSyncExtensiones()` (AJAX `POST` a `route('extension.sync')`) — Solo si `canSyncCalls()`. El botón se deshabilita y muestra spinner mientras la sincronización está en curso. La respuesta JSON indica éxito o error.

**a.1) Banners de Sincronización (dinámicos):**
- **Banner amarillo** (`#extensionSyncBanner`): Visible durante la sincronización. Muestra spinner animado y barra de progreso pulsante. Polling cada 3 segundos a `GET /extension/sync-status` para mostrar progreso extensión por extensión.
- **Banner verde** (`#extensionSyncCompleteBanner`): Aparece al completar. Incluye enlace "Recargar página para ver los cambios".
- **Banner rojo**: Aparece si la sincronización falla (cambia colores del banner amarillo).

**a.2) Sidebar — Indicador de sincronización:**
- El enlace "Anexos" en el sidebar muestra un spinner amarillo (`fa-sync fa-spin`) y un tooltip "Sincronizando anexos, espere..." mientras la sincronización está en curso.
- Polling global desde `app.blade.php` cada 3 segundos a `GET /extension/sync-status`.

**b) Tabla de Extensiones:**
- Columnas: Anexo, First Name, Last Name, Email, Phone, IP, Permission, DND, Max Contacts, Acciones
- IP muestra verde si tiene valor, gris si `---`
- Permission con badges por color (International=púrpura, National=azul, Local=verde, Internal=gris)
- DND: Círculo rojo (activo) o verde (disponible)
- Botón "Editar" con Alpine.js `@click="openModal({...})"` — Solo si `canEditExtensions()`

**c) Modal Multi-paso:**

**Paso 1 — Datos del Anexo:**
- Campos: Nombre, Apellido, Email, Teléfono, Permisos (select), Max Contactos SIP (select 1-10), Contraseña SIP/IAX, DND (checkbox)
- Banner "Desvíos de Llamadas" con botón "Configurar" → `goToStep2()`

**Paso 2 — Desvíos de Llamadas:**
- Selector de horario (timetype): Todo el tiempo, Oficina, Fuera de oficina, Feriados, Fines de semana
- **CFU (Incondicional)**: dest_type (none/extension/queue/custom) + destination
- **CFB (Ocupado)**: Mismo formato
- **CFN (No Respuesta)**: Mismo formato
- Si tipo = "queue": Select dinámico con colas cargadas desde la PBX
- Cada tarjeta cambia color de borde cuando está activa

#### Métodos Alpine.js:

| Método | Descripción |
|---|---|
| `openModal(data)` | Abre modal con datos pre-llenados, resetea desvíos |
| `closeModal()` | Cierra modal (no si está guardando) |
| `resetForwarding()` | Reinicia todos los datos de desvío |
| `hasForwardingConfigured()` | Retorna true si algún desvío está activo |
| `goToStep2()` | Carga desvíos via `GET route('extension.forwarding.get')` con fetch() |
| `parseDestType(value, destType)` | Mapea códigos PBX a tipos UI (`1`→extension, `5`→queue, `2`→custom) |
| `confirmForwarding()` | Valida que destinos activos tengan valor |
| `cancelForwarding()` | Restaura backup y vuelve a paso 1 |
| `saveAll()` | Muestra `confirm()`, luego POST datos del anexo a `route('extension.update')` + POST desvíos a `route('extension.forwarding.update')` (JSON), luego `window.location.reload()` |

---

### 2.4 `dashboard.blade.php` — Dashboard (Placeholder)

**Ruta:** No usada directamente (dashboard redirige a reporte)  
**Líneas:** ~20  
**Layout:** `<x-app-layout>`

Contenido mínimo: card blanca con "You're logged in!"

---

### 2.5 `login.blade.php` — Login Personalizado

**Ruta:** `GET /login`  
**Líneas:** ~40  
**Layout:** Standalone (sin layout framework)

Vista de login minimalista con CSS inline:
- Card centrada (300px) con fondo blanco y sombra
- Campos: Usuario (name), Contraseña
- Formulario POST a `route('iniciar-sesion')`
- Muestra errores de validación en rojo

**Nota:** Esta es la vista de login custom, diferente a las vistas Breeze en `auth/`.

---

### 2.6 `pdf_reporte.blade.php` — Template PDF

**Ruta:** Generado por `CdrController@exportPdf`  
**Líneas:** ~65  
**Layout:** Standalone (HTML puro para DomPDF)

#### Estructura:
- CSS inline con estilos de impresión (fuente Arial 11px, bordes sólidos negros)
- Header: Título centrado, central IP, fecha de generación
- Tabla resumen: Periodo, cantidad de llamadas, minutos, total a pagar
- Nota de truncado (si hay más registros que los mostrados)
- Tabla detalle: Fecha/Hora, Origen, Destino, Tipo (3 chars), Segundos, Costo
- Pie de página con leyenda de abreviaciones

---

### 2.7 `welcome.blade.php` — Landing Page Laravel

**Ruta:** `GET /welcome` (ruta por defecto Laravel, probablemente no usada)  
**Líneas:** ~280  
**Layout:** Standalone

Vista por defecto de Laravel 11 con:
- Detección de assets Vite / fallback CSS inline con Tailwind v4
- Links a documentación y Laracasts
- Botón "Deploy now" a cloud.laravel.com
- Logo Laravel animado con SVG
- Soporte dark mode
- Navegación condicional (Dashboard si auth, Log In/Register si guest)

---

### 2.8 `doom.blade.php` — Easter Egg

**Ruta:** `GET /doom`  
**Líneas:** ~120  
**Layout:** Standalone (fullscreen)

Página temática de DOOM (estilo retro):
- Fuente "Press Start 2P" (retro pixelada)
- Fondo degradado negro-rojo con animaciones CSS (pulse, glow)
- Botón "JUGAR DOOM" que abre `https://dos.zone/doom-dec-1993/` en nueva pestaña
- Link "VOLVER AL TRABAJO" → `route('login')`

---

## 3. MÓDULO PBX

### 3.1 `pbx/index.blade.php` — Selector de Centrales

**Ruta:** `GET /pbx` (`route('pbx.index')`)  
**Controlador:** `PbxConnectionController@index`  
**Líneas:** ~530  
**Layout:** Standalone (sin layout app — tiene su propio HTML/CSS)

#### Componente Alpine.js: `pbxManager()`

```javascript
{
    showModal: false,
    showDeleteModal: false,
    isEditing: false,
    formAction: 'route("pbx.store")',
    deleteAction: '',
    deleteConnectionName: '',
    form: { name, ip, port, username, password, verify_ssl }
}
```

#### Secciones:

**a) Header:**
- Nombre app + info usuario + badge Admin + botón Salir

**b) Grid de Cards (responsive, auto-fill 280px):**

Cada card de PBX connection muestra:
- Header: Nombre + badge "ACTIVA" (si aplica) + badge de estado (PENDING/SYNCING/ERROR)
- Body: Usuario, SSL, última sincronización
- Botones según estado:

| Estado | Botón | Acción |
|---|---|---|
| `pending` | CONFIGURAR CENTRAL (amarillo) | `route('pbx.setup', $conn)` |
| `syncing` | VER PROGRESO (cyan) | `route('pbx.setup', $conn)` |
| `error` | REINTENTAR SYNC (rojo) | `route('pbx.setup', $conn)` |
| `ready` + no activa | CONECTAR (azul) | `route('pbx.select', $conn)` |
| `ready` + activa | DESCONECTAR (gris) | `POST route('pbx.disconnect')` |

- Acciones admin: Editar + Eliminar (deshabilitado si activa)

**c) Modal Crear/Editar:**
- Campos: Nombre, IP, Puerto, Usuario, Contraseña, Verificar SSL
- POST a `route('pbx.store')` (crear) o PUT a `/pbx/{id}` (editar)

**d) Modal Confirmar Eliminación:**
- Advertencia roja sobre eliminación de llamadas y extensiones asociadas
- DELETE a `/pbx/{id}`

---

### 3.2 `pbx/setup.blade.php` — Sincronización de PBX

**Ruta:** `GET /pbx/{pbx}/setup` (`route('pbx.setup')`)  
**Controlador:** `PbxConnectionController@setup`  
**Líneas:** ~479  
**Layout:** `<x-app-layout>`

#### Componente Alpine.js: `syncManager()`

```javascript
{
    pbxId: $pbx->id,
    status: $pbx->status,
    isSyncing: boolean,
    syncOptions: { extensions: true, calls: false, year: current },
    currentStep: '',
    currentMonth: 1,
    currentMessage: 'Iniciando...',
    progress: 0,
    extensionCount: $extensionCount,
    callCount: $callCount,
    logs: [],
    urls: { syncExtensions, syncCalls, finishSync, syncStatus }
}
```

#### Paneles condicionales:

**Panel Inicial (status !== ready, !syncing):**
- Advertencia "necesita sincronización"
- Checkbox: Sincronizar Extensiones (default: checked)
- Checkbox: Sincronizar Llamadas + selector de año (2020-actual)
- Botón "Iniciar Sincronización"

**Panel de Progreso (syncing):**
- Indicador circular con spinner
- Texto de paso actual (1/2 Extensiones, 2/2 Llamadas Mes X/12)
- Barra de progreso porcentual
- Log de actividad en terminal simulada (fondo negro, texto en colores según tipo)
- Advertencia "No cierres esta página"

**Panel Completado (ready):**
- Check verde con "¡Central Lista!"
- Botones: Ir al Dashboard / Sincronizar de nuevo

**Panel Error:**
- X roja con mensaje de error
- Botones: Reintentar / Volver

#### Flujo de Sincronización (`startSync()`):
1. POST a `route('pbx.syncExtensions')` → obtiene extensiones
2. Loop meses 1-12: POST a `route('pbx.syncCalls')` con `{year, month}`
3. Polling `refreshCounts()` entre meses para actualizar conteo
4. POST a `route('pbx.finishSync')` para finalizar
5. Actualiza estado a `ready`

#### Progreso:
- Si solo extensiones: 0% → 100%
- Si extensiones + llamadas: extensiones = 20%, llamadas = 80% (distribuido en 12 meses)

---

## 4. MÓDULO USUARIOS

### 4.1 `users/index.blade.php` — Listado de Usuarios

**Ruta:** `GET /users` (`route('users.index')`)  
**Controlador:** `UserController@index`  
**Líneas:** ~165  
**Layout:** `<x-app-layout>`

#### Secciones:
- **Header**: Título + botón "Nuevo Usuario" (verde)
- **Tabla**: Usuario (avatar circular con inicial), Email, Rol (badge), Permisos (badges múltiples), Acciones
- **Badges de permisos**: Sync (azul), SyncExt (azul), SyncQ (azul), Ext (púrpura), IPs (naranja), Tar (naranja), PBX (rojo), PDF (rosa), XLS (verde), Graf (indigo), o "Solo lectura". Permisos de vista deshabilitados se muestran tachados (~~Anexos~~, ~~Tarifas~~)
- **Acciones**: Editar + Eliminar (con confirm JS). No disponibles para el usuario actual
- **Paginación**

---

### 4.2 `users/create.blade.php` — Crear Usuario

**Ruta:** `GET /users/create` (`route('users.create')`)  
**Controlador:** `UserController@create`  
**Líneas:** ~270  
**Layout:** `<x-app-layout>`

#### Componente Alpine.js: `userForm()`

```javascript
{
    selectedRole: 'user',
    permissions: { can_sync_calls, can_sync_extensions, can_sync_queues, can_edit_extensions, can_update_ips, can_edit_rates, can_manage_pbx, can_export_pdf, can_export_excel, can_view_charts, can_view_extensions, can_view_rates },
    updateRole(),     // Si admin → activa todos los permisos
}
```

#### Estructura 2 columnas:

**Columna Izquierda — Datos:**
- Nombre, Email, Contraseña, Confirmar Contraseña, Rol (user/supervisor/admin)

**Columna Derecha — Permisos (5 categorías):**
- **Sincronización**: Sincronizar Llamadas, Sincronizar Anexos, Sincronizar Colas
- **Acciones de API**: Editar Anexos, Actualizar IPs, Gestionar PBX
- **Configuración**: Editar Tarifas
- **Visualización de Secciones**: Ver Anexos, Ver Tarifas
- **Reportes**: Exportar PDF, Exportar Excel, Ver Gráficos
- Nota: Admin tiene todos los permisos automáticamente
- Cada permiso usa patrón `hidden input (value 0/1) + checkbox (x-model)`

---

### 4.3 `users/edit.blade.php` — Editar Usuario

**Ruta:** `GET /users/{user}/edit` (`route('users.edit')`)  
**Controlador:** `UserController@edit`  
**Líneas:** ~323  
**Layout:** `<x-app-layout>`

Misma estructura que `create.blade.php` con diferencias:
- Contraseña es opcional ("dejar vacío para mantener")
- Carga valores existentes del usuario (`$user->can_*`)
- Metodo `@method('PUT')`
- Muestra info: fecha creación + última actualización
- Botón "Eliminar Usuario" separado fuera del formulario principal (formulario DELETE con confirm)

---

## 5. MÓDULO SETTINGS

### 5.1 `settings/index.blade.php` — Tarifas

**Ruta:** `GET /settings` (`route('settings.index')`)  
**Controlador:** `SettingController@index`  
**Líneas:** ~100  
**Layout:** `<x-app-layout>`

#### Variables recibidas:
- `$settings` — Colección de Setting (key, value, label)

#### Estructura:
- Grid de cards (3 columnas) — cada tarifa con:
  - Label con ícono de tag
  - Input numérico con prefijo `$`
  - Texto "Precio en pesos por minuto"
  - Input readonly si `!canEditRates()`
- Botón "Guardar Cambios" — Solo si `canEditRates()`

---

## 6. MÓDULO STATS / KPIs

### 6.1 `stats/kpi-turnos.blade.php` — KPIs de Colas

**Ruta:** `GET /stats/kpi-turnos` (`route('stats.kpi-turnos')`)  
**Controlador:** `QueueStatsController@kpiTurnos`  
**Líneas:** ~1098 (la vista más extensa)  
**Layout:** `<x-app-layout>`

#### Variables recibidas:
- `$kpisPorHora` — Array indexado por hora con métricas
- `$kpisPorCola` — Array indexado por cola
- `$totales` — Resumen global
- `$colasDisponibles` — Lista de colas para filtro
- `$fechaInicio`, `$fechaFin`, `$colaFiltro` — Filtros activos
- `$ultimaSincronizacion` — Timestamp
- `$agentesPorCola` — Detalle de agentes por cola
- `$rendimientoAgentes` — Métricas individuales por agente

#### Secciones:

**a) Header + Filtros:**
- Título con última sincronización (diffForHumans)
- Botón "Sincronizar Colas" (solo admin) → muestra `confirm()` antes de abrir modal de sync
- Filtros: Fecha desde/hasta, Cola (select), botón Filtrar

**b) 5 Tarjetas KPI:**
| Tarjeta | Borde | Dato |
|---|---|---|
| Volumen Total | Azul | `$totales['volumen']` |
| Atendidas | Verde | `$totales['atendidas']` + % |
| % Abandono | Rojo/Amarillo/Naranja (dinámico) | `$totales['abandono_pct']` |
| Espera Promedio | Cyan | `$totales['tiempo_espera_promedio']` |
| Agentes Activos | Púrpura | `count($totales['agentes'])` |

**c) 3 Gráficos Chart.js (solo si volumen > 0):**

| Canvas ID | Tipo | Datos |
|---|---|---|
| `lineChart` | Line | Volumen por hora (área fill, puntos, tension 0.4) |
| `barChart` | Bar | Atendidas vs Abandonadas (verde/rojo) |
| `areaChart` | Line (fill) | Tiempo de espera (gradiente cyan, segmentos coloreados: >60s=rojo, >30s=amarillo) |

**d) Tabla KPIs por Hora:**
- Columnas: Hora, Volumen, Atendidas, % Abandono, Espera Prom., ASA, Agentes
- Solo muestra filas con volumen > 0
- Badges coloreados según umbrales (abandono >20%=rojo, >15%=amarillo)
- Footer con totales

**e) Alertas y Recomendaciones:**
- PHP server-side genera alertas basadas en umbrales:
  - Abandono > 20% → `danger`
  - Abandono > 15% → `warning`
  - Espera > 60s → `danger`
  - Espera > 30s → `warning`

**f) Tabla KPIs por Cola:**
- Columnas: Cola, Volumen, Atendidas, % Abandono, ASA, Agentes
- Botón para ver detalle de agentes → abre modal

**g) Rendimiento de Agentes:**
- Tabla con: Agente (extensión), Llamadas Atendidas, Tasa Atención, Tiempo Total, Promedio/Llamada, Espera Promedio
- Tasa color-coded: >=80%=verde, >=60%=amarillo, <60%=rojo
- Si no hay datos: advertencia con comando artisan para sincronizar

#### Modales:

**Modal Agentes por Cola:**
- Tabla dinámica generada con JavaScript (`mostrarAgentes(cola)`)
- Muestra: Agente, Intentos, Contestadas, Efectividad, Tiempo Total, Espera Promedio

**Modal Sincronización:**
- Select de días (1/7/15/30/90/180/365)
- POST a `route('stats.sync-colas')` con `{days}`
- Muestra resultado: registros insertados/omitidos
- Auto-recarga si no hay registros nuevos

**g.1) Mensaje sin datos de agentes:**
- Cuando no hay datos de agentes sincronizados, se muestra un mensaje simple: "No hay datos de agentes disponibles. Usa el botón Sincronizar Colas para obtenerlos."

---

## 7. ERROR VIEWS

### 7.1 `errors/419.blade.php` — Sesión Expirada

**Líneas:** ~15

Redirección automática a `route('login')` via JavaScript (`window.location.href`). No muestra contenido visible.

---

## 8. ARCHIVOS JS/CSS

### 8.1 `resources/js/app.js` — Entry Point

**Líneas:** 8

```javascript
import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

Inicializa Alpine.js como framework de reactividad global.

### 8.2 `resources/js/bootstrap.js` — Bootstrap HTTP

**Líneas:** 5

```javascript
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

Configura Axios como cliente HTTP con header X-Requested-With para AJAX.

### 8.3 `resources/css/app.css` — Estilos Globales

**Líneas:** ~65

Contenido:
- **Tailwind directives**: `@tailwind base/components/utilities`
- **`[x-cloak]`**: Oculta elementos Alpine.js hasta que se inicializan
- **`.chart-container`**: Height 300px, max 400px con canvas responsive
- **`.chart-fixed`**: Gráfico fijo 100x100px
- **Animaciones**: 
  - `fadeIn` — opacity 0→1 (0.4s)
  - `slideIn` — opacity 0→1 + translateY 10px→0 (0.5s)
- **`.page-transition`**: usa fadeIn
- **`.page-transition-slide`**: usa slideIn (aplicada al main content en layout)

---

## 9. COMPONENTES BLADE (Laravel Breeze)

Los componentes en `resources/views/components/` son los estándar de Laravel Breeze 2.x:

| Componente | Uso |
|---|---|
| `application-logo` | SVG del logo de la aplicación |
| `auth-session-status` | Muestra mensajes de sesión en auth |
| `danger-button` | Botón rojo para acciones destructivas |
| `dropdown` | Dropdown con Alpine.js |
| `dropdown-link` | Link dentro de un dropdown |
| `input-error` | Muestra errores de validación bajo un input |
| `input-label` | Label estilizado para formularios |
| `modal` | Modal genérico con Alpine.js |
| `nav-link` | Link de navegación con estado activo |
| `primary-button` | Botón primario (azul/indigo) |
| `responsive-nav-link` | Link de nav para mobile |
| `secondary-button` | Botón secundario (gris) |
| `text-input` | Input de texto estilizado |

---

## 10. VISTAS AUTH (Laravel Breeze)

Las vistas en `resources/views/auth/` son las estándar de Laravel Breeze:

| Vista | Descripción |
|---|---|
| `login.blade.php` | Formulario de login (Breeze) — usa guest layout |
| `register.blade.php` | Formulario de registro |
| `forgot-password.blade.php` | Solicitar reset de contraseña |
| `reset-password.blade.php` | Restablecer contraseña |
| `confirm-password.blade.php` | Confirmar contraseña actual |
| `verify-email.blade.php` | Verificación de email |

**Nota:** La app usa `login.blade.php` custom (standalone) en la raíz de views para el login real. Las vistas Breeze se mantienen como parte del scaffolding.

---

## 11. PATRONES Y CONVENCIONES

### Comunicación Frontend-Backend

| Patrón | Uso | Ejemplo |
|---|---|---|
| **Blade Server-Side** | Rendering principal de todas las vistas | `@foreach($llamadas as $call)` |
| **Alpine.js Reactivity** | Modales, formularios multi-paso, estados UI | `x-data="extensionEditor()"` |
| **Fetch API** | Llamadas AJAX a endpoints JSON | Sync status, desvíos, queue stats |
| **CSRF Token** | Meta tag + headers en fetch/axios | `<meta name="csrf-token">` + `X-CSRF-TOKEN` |
| **Session Flash Messages** | Feedback de operaciones | `session('success')`, `session('error')` |
| **Server-Side Sorting** | Query params en URLs | `?sort=start_time&dir=desc` |
| **Paginación Laravel** | `->links()` con preservación de filtros | `$llamadas->appends(request()->input())` |
| **Permisos en UI** | Condicionales Blade | `@if(Auth::user()->canSyncCalls())` |

### Patrón de Protección de Permisos en UI

```
Backend:                              Frontend:
┌─────────────────┐                ┌─────────────────────────────┐
│ abort_unless(   │                │ @if(Auth::user()->canX())   │
│   $user->canX(),│  ← seguridad   │   <button>Acción</button>   │
│   403           │                │ @endif                      │
│ )               │                │                             │
└─────────────────┘                └─────────────────────────────┘
```

> **Doble protección:** El backend siempre verifica permisos (abort 403). El frontend solo oculta botones para UX — nunca como única línea de defensa.

### Interactividad Alpine.js — Componentes Principales

| Vista | Componente | Complejidad | Funcionalidades |
|---|---|---|---|
| `configuracion.blade.php` | `extensionEditor()` | Alta | Modal 2 pasos, edición SIP, desvíos PBX, 2 POSTs secuenciales |
| `pbx/index.blade.php` | `pbxManager()` | Media | CRUD modales centrales PBX, doble modal (edit + delete confirm) |
| `pbx/index.blade.php` | `userManager()` | Alta | CRUD usuarios via API JSON, asignación de centrales, toggle permisos |
| `pbx/setup.blade.php` | `syncManager()` | Alta | Sync multi-paso con polling, barra de progreso, log en terminal |
| `users/create.blade.php` | `userForm()` | Baja | Auto-activar permisos según rol seleccionado |
| `layouts/app.blade.php` | inline | Baja | Sidebar toggle + polling de sync indicator |

### Patrón Fetch con CSRF

Todas las llamadas `fetch()` en el frontend siguen este patrón:

```javascript
fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(data)
})
.then(r => r.json())
.then(data => { /* actualizar UI */ })
.catch(err => { /* mostrar error */ });
```

### Paleta de Colores por Módulo

| Módulo | Color Primario |
|---|---|
| Llamadas/CDR | Azul (`blue-500`) |
| Cobros/Tarifas | Verde (`green-500`) |
| Extensiones | Gris oscuro (`gray-800`) |
| Usuarios Admin | Amarillo (`yellow-500`) |
| Colas/KPIs | Indigo/Púrpura (`indigo-600`/`purple-600`) |
| Errores | Rojo (`red-500`) |
| PBX | Gris oscuro (`gray-800`) |

---

## 12. ÍNDICE DE FUNCIONES JAVASCRIPT

| Función | Archivo | Descripción |
|---|---|---|
| `checkSync()` | `layouts/app.blade.php` | Polling estado sincronización (cada 2s) |
| `editarNombre(ext, nombre)` | `reporte.blade.php` | Dispara modal edición nombre extensión |
| `pedirTituloYDescargar()` | `reporte.blade.php` | Prompt título + genera PDF |
| `extensionEditor()` | `configuracion.blade.php` | Componente Alpine.js completo extensiones |
| `extensionEditor.openModal(data)` | `configuracion.blade.php` | Abre modal edición con datos |
| `extensionEditor.goToStep2()` | `configuracion.blade.php` | Carga desvíos desde PBX (fetch) |
| `extensionEditor.saveAll()` | `configuracion.blade.php` | Muestra confirm() + guarda extensión + desvíos (2 POSTs) |
| `extensionEditor.parseDestType()` | `configuracion.blade.php` | Mapea códigos PBX a UI (1→ext, 5→queue) |
| `pbxManager()` | `pbx/index.blade.php` | Componente Alpine.js CRUD centrales |
| `pbxManager.openCreateModal()` | `pbx/index.blade.php` | Abre modal crear central |
| `pbxManager.openEditModal(conn)` | `pbx/index.blade.php` | Abre modal editar central |
| `pbxManager.confirmDelete(id, name)` | `pbx/index.blade.php` | Modal confirmación eliminación |
| `syncManager()` | `pbx/setup.blade.php` | Componente Alpine.js sincronización |
| `syncManager.startSync()` | `pbx/setup.blade.php` | Inicia sincronización extensiones + llamadas |
| `syncManager.syncExtensions()` | `pbx/setup.blade.php` | POST sync extensiones |
| `syncManager.syncCallsMonth(y, m)` | `pbx/setup.blade.php` | POST sync llamadas por mes |
| `syncManager.finishSync()` | `pbx/setup.blade.php` | POST finalizar sincronización |
| `syncManager.pollStatus()` | `pbx/setup.blade.php` | Polling estado (cada 2s) |
| `userForm()` | `users/create.blade.php` | Componente Alpine.js permisos |
| `userForm.updateRole()` | `users/create.blade.php` | Auto-activa permisos si admin |
| `userManager()` | `pbx/index.blade.php` | Componente Alpine.js gestión usuarios (modal) |
| `userManager.openModal()` | `pbx/index.blade.php` | Abre modal y carga usuarios |
| `userManager.loadUsers()` | `pbx/index.blade.php` | Fetch API: carga usuarios + centrales disponibles |
| `userManager.showCreateForm()` | `pbx/index.blade.php` | Resetea formulario para crear nuevo usuario |
| `userManager.showEditForm(user)` | `pbx/index.blade.php` | Carga datos del usuario en formulario |
| `userManager.saveUser()` | `pbx/index.blade.php` | POST crear usuario con centrales asignadas |
| `userManager.updateUser()` | `pbx/index.blade.php` | PUT actualizar usuario con centrales asignadas |
| `userManager.deleteUser(user)` | `pbx/index.blade.php` | DELETE eliminar usuario (con confirm) |
| `userManager.togglePbx(id)` | `pbx/index.blade.php` | Toggle central en lista de permitidas |
| `userManager.selectAllPbx()` | `pbx/index.blade.php` | Seleccionar todas las centrales |
| `userManager.getPermBadges(user)` | `pbx/index.blade.php` | Genera badges de permisos por usuario |
| `userManager.getRoleBadge(user)` | `pbx/index.blade.php` | Genera badge del rol del usuario |
| `mostrarAgentes(cola)` | `stats/kpi-turnos.blade.php` | Abre modal detalle agentes por cola |
| `cerrarModal()` | `stats/kpi-turnos.blade.php` | Cierra modal agentes |
| `sincronizarColas()` | `stats/kpi-turnos.blade.php` | Muestra confirm() + abre modal sincronización colas |
| `ejecutarSincronizacion()` | `stats/kpi-turnos.blade.php` | POST sync colas con días seleccionados |
