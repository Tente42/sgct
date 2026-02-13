# Rutas, Middleware y Configuración — Documentación Detallada

> Documenta el sistema de rutas, los middleware de autorización, los seeders de datos iniciales y los View Components del sistema.

---

## Arquitectura de Rutas

El sistema tiene **3 capas de acceso**, cada una protegida por middleware encadenados:

```
Capa 1 — Públicas (sin auth)
    └── login, logout, doom

Capa 2 — Autenticadas (auth)
    ├── Gestión PBX (selección, estado sync)
    └── Admin-only (CRUD PBX, sync, usuarios)

Capa 3 — Con central seleccionada (auth + pbx.selected)
    ├── Solo lectura (dashboard, gráficos, exports, tarifas)
    └── Con permisos específicos (sync CDR, editar extensiones, actualizar IPs, tarifas)
```

---

## Rutas Web (routes/web.php)

### 1. Rutas Públicas (sin autenticación)

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| GET | `/login` | AuthController@showLogin | login | Formulario de login (custom, no Breeze) |
| POST | `/login` | AuthController@login | iniciar-sesion | Procesa login con `throttle:10,1` |
| POST | `/logout` | AuthController@logout | logout | Cierra sesión |
| GET | `/doom` | Closure → vista doom | doom | Easter egg / página especial |

> **`throttle:10,1`**: Máximo 10 intentos de login por minuto por IP. Previene ataques de fuerza bruta.

---

### 2. Rutas de Gestión PBX (`/pbx/*`)

**Middleware:** `auth`

Estas rutas están disponibles incluso sin central seleccionada — son las que permiten seleccionar una.

| Método | URI | Controlador | Nombre | Acceso | Descripción |
|---|---|---|---|---|---|
| GET | `/pbx` | PbxConnectionController@index | pbx.index | Todos | Lista centrales disponibles |
| GET | `/pbx/select/{pbx}` | PbxConnectionController@select | pbx.select | Todos | Selecciona/cambia central activa |
| GET | `/pbx/sync-status/{pbx}` | PbxConnectionController@checkSyncStatus | pbx.syncStatus | Todos | Polling AJAX de progreso de sync |

**Middleware adicional:** `admin` — Solo administradores

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| POST | `/pbx` | PbxConnectionController@store | pbx.store | Crear nueva central |
| PUT | `/pbx/{pbx}` | PbxConnectionController@update | pbx.update | Editar configuración central |
| DELETE | `/pbx/{pbx}` | PbxConnectionController@destroy | pbx.destroy | Eliminar central (CASCADE) |
| GET | `/pbx/setup/{pbx}` | PbxConnectionController@setup | pbx.setup | Wizard de configuración inicial |
| POST | `/pbx/sync-extensions/{pbx}` | PbxConnectionController@syncExtensions | pbx.syncExtensions | Inicia sync extensiones (job) |
| POST | `/pbx/sync-calls/{pbx}` | PbxConnectionController@syncCalls | pbx.syncCalls | Inicia sync llamadas (job) |
| POST | `/pbx/finish-sync/{pbx}` | PbxConnectionController@finishSync | pbx.finishSync | Marca wizard como completado |
| POST | `/pbx/disconnect` | PbxConnectionController@disconnect | pbx.disconnect | Desselecciona central activa |

> **Flujo del Wizard:** `store` → `setup` → `syncExtensions` → `syncCalls` → `finishSync` → redirige al dashboard

---

### 3. Rutas de Usuarios (`/usuarios/*`)

**Middleware:** `auth`, `admin` — Exclusivo administradores

#### 3a. Interfaz Web (renderiza Blade)

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| GET | `/usuarios` | UserController@index | users.index | Listado con tabla paginada |
| GET | `/usuarios/crear` | UserController@create | users.create | Formulario de creación |
| POST | `/usuarios` | UserController@store | users.store | Guardar nuevo usuario |
| GET | `/usuarios/{user}/editar` | UserController@edit | users.edit | Formulario de edición |
| PUT | `/usuarios/{user}` | UserController@update | users.update | Actualizar usuario |
| DELETE | `/usuarios/{user}` | UserController@destroy | users.destroy | Eliminar usuario |

#### 3b. API JSON (para modal Alpine.js en página PBX)

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| GET | `/api/usuarios` | UserController@apiIndex | users.api.index | Lista JSON con pivot |
| POST | `/api/usuarios` | UserController@apiStore | users.api.store | Crear + asignar centrales |
| PUT | `/api/usuarios/{user}` | UserController@apiUpdate | users.api.update | Editar + sync pivot |
| DELETE | `/api/usuarios/{user}` | UserController@apiDestroy | users.api.destroy | Eliminar (protege último admin) |

---

### 4. Rutas Privadas (requieren auth + central seleccionada)

**Middleware:** `auth`, `pbx.selected`

#### 4a. Solo lectura — Todos los usuarios autenticados

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| GET | `/` | CdrController@index | home | Dashboard principal (alias) |
| GET | `/dashboard` | CdrController@index | dashboard | Dashboard principal |
| GET | `/graficos` | CdrController@showCharts | cdr.charts | Gráficos con Chart.js |
| GET | `/configuracion` | ExtensionController@index | extension.index | Listado de extensiones |
| GET | `/export-pdf` | CdrController@descargarPDF | cdr.pdf | Descarga PDF con DomPDF |
| GET | `/exportar-excel` | CdrController@exportarExcel | calls.export | Descarga Excel con Maatwebsite |
| GET | `/tarifas` | SettingController@index | settings.index | Ver tarifas actuales |

#### 4b. Acciones protegidas — Verificadas por permiso en el controlador

| Método | URI | Controlador | Nombre | Permiso requerido |
|---|---|---|---|---|
| POST | `/sync` | CdrController@syncCDRs | cdr.sync | `canSyncCalls` |
| POST | `/extension/update` | ExtensionController@update | extension.update | `canEditExtensions` |
| POST | `/extension/update-ips` | ExtensionController@updateIps | extension.updateIps | `canUpdateIps` |
| POST | `/tarifas` | SettingController@update | settings.update | `canEditRates` |

> **Nota de seguridad:** Los permisos no son middleware — se verifican dentro del controlador con `abort_unless($user->canX(), 403)`. Esto permite mostrar la vista (ej: listado de extensiones) sin el botón de edición a usuarios sin permiso.

#### 4c. Desvíos de llamadas (Call Forwarding)

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| GET | `/extension/forwarding` | ExtensionController@getCallForwarding | extension.forwarding.get | Obtiene config actual de desvío |
| POST | `/extension/forwarding` | ExtensionController@updateCallForwarding | extension.forwarding.update | Actualiza desvío en PBX |

#### 4d. Estadísticas de Colas

| Método | URI | Controlador | Nombre | Descripción |
|---|---|---|---|---|
| GET | `/stats/kpi-turnos` | StatsController@index | stats.kpi-turnos | Vista de KPIs |
| GET | `/stats/kpi-turnos/api` | StatsController@apiKpis | stats.kpi-turnos.api | JSON con 7 datasets |
| POST | `/stats/kpi-turnos/sync` | StatsController@sincronizarColas | stats.sync-colas | Sync manual de colas |

---

## Rutas de Autenticación (routes/auth.php)

Rutas generadas por **Laravel Breeze** — se mantienen por compatibilidad pero el login principal usa `AuthController` custom.

### Rutas Guest (middleware: `guest`)

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/register` | RegisteredUserController@create | register |
| POST | `/register` | RegisteredUserController@store | — |
| GET | `/login` | AuthenticatedSessionController@create | login |
| POST | `/login` | AuthenticatedSessionController@store | — |
| GET | `/forgot-password` | PasswordResetLinkController@create | password.request |
| POST | `/forgot-password` | PasswordResetLinkController@store | password.email |
| GET | `/reset-password/{token}` | NewPasswordController@create | password.reset |
| POST | `/reset-password` | NewPasswordController@store | password.store |

### Rutas Auth (middleware: `auth`)

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/verify-email` | EmailVerificationPromptController | verification.notice |
| GET | `/verify-email/{id}/{hash}` | VerifyEmailController | verification.verify |
| POST | `/email/verification-notification` | EmailVerificationNotificationController@store | verification.send |
| GET | `/confirm-password` | ConfirmablePasswordController@show | password.confirm |
| POST | `/confirm-password` | ConfirmablePasswordController@store | — |
| PUT | `/password` | PasswordController@update | password.update |
| POST | `/logout` | AuthenticatedSessionController@destroy | logout |

> **Conflicto de rutas:** Tanto `AuthController@showLogin` (custom) como `AuthenticatedSessionController@create` (Breeze) registran GET `/login`. El custom se registra primero en `web.php` y toma precedencia.

---

## Middleware

### 1. `AdminMiddleware` (app/Http/Middleware/AdminMiddleware.php)

**Alias:** `admin`  
**Registro:** `bootstrap/app.php`  
**Propósito:** Restringe acceso a rutas de administración (CRUD centrales, usuarios, sync).

#### `handle(Request $request, Closure $next): Response`

```
¿Autenticado Y es admin?
├── Sí → $next($request)
└── No → ¿Espera JSON?
         ├── Sí → Response 403 {"message": "No autorizado"}
         └── No → redirect('/dashboard')->with('error', 'No autorizado')
```

> **Detección de formato:** Usa `$request->expectsJson()` que evalúa el header `Accept: application/json` — las peticiones AJAX de Alpine.js incluyen este header automáticamente.

---

### 2. `CheckPbxSelected` (app/Http/Middleware/CheckPbxSelected.php)

**Alias:** `pbx.selected`  
**Propósito:** Asegura que el usuario haya seleccionado una central PBX antes de acceder a funcionalidades que dependen de ella (CDR, extensiones, estadísticas).

#### Rutas Excluidas

```php
protected array $excludedRoutes = [
    'login',
    'iniciar-sesion',
    'logout',
    'pbx.*',  // Wildcard: todas las rutas que empiecen con pbx.
];
```

> **Razonamiento:** Las rutas PBX deben ser accesibles sin central seleccionada — son precisamente las que permiten seleccionar una.

#### `handle(Request $request, Closure $next): Response`

```
1. ¿No autenticado?      → deja pasar (auth middleware se encargará)
2. ¿Ruta excluida?       → deja pasar
3. ¿session('active_pbx_id')? 
   ├── Existe  → $next($request)
   └── No      → redirect('pbx.index') + flash warning 
                  "Debe seleccionar una central..."
```

#### `isExcludedRoute(Request $request): bool`

Compara el nombre de la ruta actual contra la lista de exclusiones. Soporta wildcards con `Str::is()`:
- `'pbx.*'` matchea `pbx.index`, `pbx.select`, `pbx.store`, etc.

---

## Diagrama de Flujo de Middleware

```
Request HTTP
    │
    ▼
¿Ruta pública? ──Sí──► Controlador (sin auth)
    │
   No
    │
    ▼
auth middleware
    │
    ├── No autenticado → redirect /login
    │
    ▼
¿Tiene middleware admin?
    │
    ├── Sí: AdminMiddleware → ¿es admin? ──No──► 403
    │
    ▼
¿Tiene middleware pbx.selected?
    │
    ├── Sí: CheckPbxSelected → ¿tiene central? ──No──► redirect /pbx
    │
    ▼
Controlador → Verificación de permisos internos
    │
    ├── ¿canSyncCalls? ¿canEditExtensions? etc.
    │
    ▼
Respuesta
```

---

## Seeders

### `DatabaseSeeder`
Ejecuta en orden estricto (las dependencias FK lo requieren):
1. `PbxConnectionSeeder` — Primero: la central es FK de extensiones, llamadas, etc.
2. `SettingSeeder` — Segundo: tarifas independientes
3. `UserSeeder` — Tercero: los usuarios se vinculan a centrales vía pivot

### `PbxConnectionSeeder`

Crea la **Central Principal** de desarrollo:

| Campo | Valor | Nota |
|---|---|---|
| `name` | Central Principal | — |
| `host` | 10.36.1.10 | IP interna |
| `port` | 7110 | Puerto REST API UCM |
| `username` | cdrapi | Usuario API dedicado |
| `password` | 123api | Se encripta automáticamente (cast `encrypted`) |
| `verify_ssl` | false | Desarrollo: certificado autofirmado |
| `is_active` | true | — |

### `SettingSeeder`

Crea las tres tarifas base del sistema (mercado chileno):

| Key | Valor | Descripción | Justificación |
|---|---|---|---|
| `price_mobile` | 80 | $80 CLP/min | Tarifa celular promedio Chile |
| `price_national` | 40 | $40 CLP/min | Tarifa fijo nacional |
| `price_international` | 500 | $500 CLP/min | Tarifa internacional promedio |

> Todas las tarifas son **por minuto** y en **CLP** (pesos chilenos). El costo de una llamada se calcula como: `ceil(billsec/60) × tarifa_por_minuto`.

### `UserSeeder`

Crea dos usuarios leyendo credenciales desde `config/services.php`:

| Rol | Config Key | Valor default | Descripción |
|---|---|---|---|
| Admin | `services.admins.name/email/pass` | (desde .env) | Acceso total |
| User | `services.users.name/email/pass` | (desde .env) | Acceso lectura |

> **Seguridad:** Las credenciales iniciales no están hardcodeadas — vienen de variables de entorno via `config/services.php`. Esto permite diferentes credenciales por ambiente (dev/staging/prod).

---

## View Components

### `AppLayout` (app/View/Components/AppLayout.php)

**Renderiza:** `resources/views/layouts/app.blade.php`

Layout principal para usuarios autenticados. Incluye:
- Navegación superior con selector de central PBX
- Sidebar con menú de módulos
- Área de contenido con slot `{{ $slot }}`
- Scripts de Alpine.js y Chart.js

### `GuestLayout` (app/View/Components/GuestLayout.php)

**Renderiza:** `resources/views/layouts/guest.blade.php`

Layout minimalista para páginas de login/registro. Sin navegación ni sidebar — solo el formulario centrado.
