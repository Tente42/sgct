# Rutas, Middleware y Configuración - Documentación Detallada

---

## Rutas Web (routes/web.php)

### 1. Rutas Públicas (sin autenticación)

| Método | URI | Controlador | Nombre | Middleware |
|---|---|---|---|---|
| GET | `/login` | AuthController@showLogin | login | - |
| POST | `/login` | AuthController@login | iniciar-sesion | throttle:10,1 |
| POST | `/logout` | AuthController@logout | logout | - |
| GET | `/doom` | Closure (vista doom) | doom | - |

---

### 2. Rutas de Gestión PBX (`/pbx/*`)

**Middleware:** `auth`

| Método | URI | Controlador | Nombre | Acceso |
|---|---|---|---|---|
| GET | `/pbx` | PbxConnectionController@index | pbx.index | Todos |
| GET | `/pbx/select/{pbx}` | PbxConnectionController@select | pbx.select | Todos |
| GET | `/pbx/sync-status/{pbx}` | PbxConnectionController@checkSyncStatus | pbx.syncStatus | Todos |

**Middleware adicional:** `admin`

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| POST | `/pbx` | PbxConnectionController@store | pbx.store |
| PUT | `/pbx/{pbx}` | PbxConnectionController@update | pbx.update |
| DELETE | `/pbx/{pbx}` | PbxConnectionController@destroy | pbx.destroy |
| GET | `/pbx/setup/{pbx}` | PbxConnectionController@setup | pbx.setup |
| POST | `/pbx/sync-extensions/{pbx}` | PbxConnectionController@syncExtensions | pbx.syncExtensions |
| POST | `/pbx/sync-calls/{pbx}` | PbxConnectionController@syncCalls | pbx.syncCalls |
| POST | `/pbx/finish-sync/{pbx}` | PbxConnectionController@finishSync | pbx.finishSync |
| POST | `/pbx/disconnect` | PbxConnectionController@disconnect | pbx.disconnect |

---

### 3. Rutas de Usuarios (`/usuarios/*`)

**Middleware:** `auth`, `admin`

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/usuarios` | UserController@index | users.index |
| GET | `/usuarios/crear` | UserController@create | users.create |
| POST | `/usuarios` | UserController@store | users.store |
| GET | `/usuarios/{user}/editar` | UserController@edit | users.edit |
| PUT | `/usuarios/{user}` | UserController@update | users.update |
| DELETE | `/usuarios/{user}` | UserController@destroy | users.destroy |

### 3b. Rutas API de Usuarios (`/api/usuarios/*`)

**Middleware:** `auth`, `admin`  
**Propósito:** Endpoints JSON para el modal de gestión de usuarios en la página de centrales PBX.

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/api/usuarios` | UserController@apiIndex | users.api.index |
| POST | `/api/usuarios` | UserController@apiStore | users.api.store |
| PUT | `/api/usuarios/{user}` | UserController@apiUpdate | users.api.update |
| DELETE | `/api/usuarios/{user}` | UserController@apiDestroy | users.api.destroy |

---

### 4. Rutas Privadas (requieren auth + central seleccionada)

**Middleware:** `auth`, `pbx.selected`

#### Solo lectura (todos los usuarios)

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/` | CdrController@index | home |
| GET | `/dashboard` | CdrController@index | dashboard |
| GET | `/graficos` | CdrController@showCharts | cdr.charts |
| GET | `/configuracion` | ExtensionController@index | extension.index |
| GET | `/export-pdf` | CdrController@descargarPDF | cdr.pdf |
| GET | `/exportar-excel` | CdrController@exportarExcel | calls.export |
| GET | `/tarifas` | SettingController@index | settings.index |

#### Requieren permisos específicos (verificados en controlador)

| Método | URI | Controlador | Nombre | Permiso |
|---|---|---|---|---|
| POST | `/sync` | CdrController@syncCDRs | cdr.sync | canSyncCalls |
| POST | `/extension/update` | ExtensionController@update | extension.update | canEditExtensions |
| POST | `/extension/update-ips` | ExtensionController@updateIps | extension.updateIps | canUpdateIps |
| POST | `/tarifas` | SettingController@update | settings.update | canEditRates |

#### Desvíos de llamadas

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/extension/forwarding` | ExtensionController@getCallForwarding | extension.forwarding.get |
| POST | `/extension/forwarding` | ExtensionController@updateCallForwarding | extension.forwarding.update |

#### Estadísticas

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/stats/kpi-turnos` | StatsController@index | stats.kpi-turnos |
| GET | `/stats/kpi-turnos/api` | StatsController@apiKpis | stats.kpi-turnos.api |
| POST | `/stats/kpi-turnos/sync` | StatsController@sincronizarColas | stats.sync-colas |

---

## Rutas de Autenticación (routes/auth.php)

Rutas generadas por **Laravel Breeze**:

### Rutas Guest (no autenticado)

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/register` | RegisteredUserController@create | register |
| POST | `/register` | RegisteredUserController@store | - |
| GET | `/login` | AuthenticatedSessionController@create | login |
| POST | `/login` | AuthenticatedSessionController@store | - |
| GET | `/forgot-password` | PasswordResetLinkController@create | password.request |
| POST | `/forgot-password` | PasswordResetLinkController@store | password.email |
| GET | `/reset-password/{token}` | NewPasswordController@create | password.reset |
| POST | `/reset-password` | NewPasswordController@store | password.store |

### Rutas Auth (autenticado)

| Método | URI | Controlador | Nombre |
|---|---|---|---|
| GET | `/verify-email` | EmailVerificationPromptController | verification.notice |
| GET | `/verify-email/{id}/{hash}` | VerifyEmailController | verification.verify |
| POST | `/email/verification-notification` | EmailVerificationNotificationController@store | verification.send |
| GET | `/confirm-password` | ConfirmablePasswordController@show | password.confirm |
| POST | `/confirm-password` | ConfirmablePasswordController@store | - |
| PUT | `/password` | PasswordController@update | password.update |
| POST | `/logout` | AuthenticatedSessionController@destroy | logout |

---

## Middleware

### 1. `AdminMiddleware` (app/Http/Middleware/AdminMiddleware.php)

**Alias:** `admin`  
**Propósito:** Verifica que el usuario sea administrador.

#### `handle(Request $request, Closure $next): Response`

**Lógica:**
1. Si no autenticado O no es admin:
   - Si espera JSON → respuesta 403 con mensaje
   - Si es web → redirige a dashboard con flash error
2. Si es admin → pasa la request

---

### 2. `CheckPbxSelected` (app/Http/Middleware/CheckPbxSelected.php)

**Alias:** `pbx.selected`  
**Propósito:** Verifica que el usuario tenga una central PBX seleccionada en sesión.

#### Rutas Excluidas

```php
protected array $excludedRoutes = [
    'login',
    'iniciar-sesion',
    'logout',
    'pbx.*',  // Todas las rutas PBX
];
```

#### `handle(Request $request, Closure $next): Response`

**Lógica:**
1. Si no autenticado → deja pasar (otro middleware lo manejará)
2. Si ruta excluida → deja pasar
3. Si no hay `active_pbx_id` en sesión → redirige a `pbx.index` con warning

#### `isExcludedRoute(Request $request): bool` (privado)
Compara el nombre de la ruta actual contra la lista de exclusiones, soportando wildcards (`pbx.*`).

---

## Seeders

### `DatabaseSeeder`
Ejecuta en orden:
1. `PbxConnectionSeeder` (primero, necesario para relaciones)
2. `SettingSeeder`
3. `UserSeeder`

### `PbxConnectionSeeder`
Crea la **Central Principal** con:
- IP: `10.36.1.10`
- Puerto: `7110`
- Usuario: `cdrapi`
- Password: `123api`
- SSL: false

### `SettingSeeder`
Crea tres tarifas por defecto:
- `price_mobile`: $80/min (Celular)
- `price_national`: $40/min (Fijo Nacional)
- `price_international`: $500/min (Internacional)

### `UserSeeder`
Crea dos usuarios desde `config/services.php`:
1. **Admin:** Lee `services.admins.name/email/pass`, rol `admin`
2. **Usuario:** Lee `services.users.name/email/pass`, rol `user`

---

## View Components

### `AppLayout` (app/View/Components/AppLayout.php)
Renderiza el layout principal: `layouts.app`

### `GuestLayout` (app/View/Components/GuestLayout.php)
Renderiza el layout para invitados: `layouts.guest`
