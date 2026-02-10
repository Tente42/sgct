# Modelos Eloquent - Documentación Detallada

---

## 1. `Call` (app/Models/Call.php)

**Tabla:** `calls`  
**Propósito:** Representa un registro de llamada (CDR) sincronizado desde la central Grandstream.

### Global Scopes (booted)

| Scope | Descripción |
|---|---|
| `current_pbx` | Filtra automáticamente todas las consultas por `pbx_connection_id` según la central activa en sesión. Solo se aplica si hay usuario logueado Y central seleccionada. |
| `creating` (evento) | Auto-asigna `pbx_connection_id` al crear nuevas llamadas si no se especifica y hay central activa. |

### Campos Fillable

| Campo | Tipo | Descripción |
|---|---|---|
| `pbx_connection_id` | int | ID de la central PBX origen |
| `unique_id` | string | ID único de la llamada en la central |
| `start_time` | datetime | Hora de inicio de la llamada |
| `answer_time` | datetime | Hora en que se contestó |
| `end_time` | datetime | Hora de fin |
| `source` | string | Origen (número del anexo o número externo) |
| `destination` | string | Destino (número marcado) |
| `dstanswer` | string | Extensión/agente que contestó |
| `caller_name` | string | Nombre del que llama |
| `duration` | int | Duración total en segundos |
| `billsec` | int | Segundos facturables (tiempo hablado) |
| `disposition` | string | Estado: ANSWERED, BUSY, NO ANSWER, FAILED |
| `action_type` | string | Tipo de acción: DIAL, QUEUE[6500], etc. |
| `lastapp` | string | Última aplicación ejecutada en Asterisk |
| `channel` | string | Canal de origen (ej: PJSIP/1760-00006d6f) |
| `dst_channel` | string | Canal de destino |
| `src_trunk_name` | string | Nombre del trunk de origen |
| `userfield` | string | Clasificación UCM: Inbound, Outbound, Internal |
| `recording_file` | string | Archivo de grabación (si existe) |

### Relaciones

| Método | Tipo | Modelo Relacionado | Descripción |
|---|---|---|---|
| `pbxConnection()` | BelongsTo | PbxConnection | Central PBX a la que pertenece la llamada |

### Accessors (Atributos Calculados)

#### `getCostAttribute(): int`
Calcula el **costo de la llamada** basado en el destino y la duración.

**Lógica:**
1. Si `billsec <= 3` → Costo = 0 (llamada muy corta)
2. Si `userfield !== 'Outbound'` → Costo = 0 (solo se cobran salientes)
3. Si destino es interno (3-4 dígitos) → Costo = 0
4. Si destino empieza con `800` → Costo = 0 (toll-free Chile)
5. Clasifica el destino por regex:
   - `9XXXXXXXX` o `+569XXXXXXXX` → Tarifa celular
   - `2XXXXXXXX` o `+562XXXXXXXX` → Tarifa fijo RM
   - `[3-8]XXXXXXXX` → Tarifa fijo regiones
   - `600XXXXXX` → Tarifa nacional
   - `+XX...` o `00XX...` (no Chile) → Tarifa internacional
6. Fórmula: `ceil(billsec / 60) * tarifa_por_minuto`

**Cache estática:** Usa `$cachedPrices` para evitar múltiples consultas a la tabla `settings`.

#### `getCallTypeAttribute(): string`
Determina el **tipo de llamada** según el número de destino.

**Retorna:** `Interna`, `Local`, `Celular`, `Nacional` o `Internacional`

### Métodos Estáticos

| Método | Retorno | Descripción |
|---|---|---|
| `getPrices()` | array | Obtiene las tarifas cacheadas de la tabla `settings` |
| `clearPricesCache()` | void | Limpia el cache estático de tarifas |

---

## 2. `Extension` (app/Models/Extension.php)

**Tabla:** `extensions`  
**Propósito:** Representa una extensión/anexo telefónico de la central PBX.

### Global Scopes (booted)

| Scope | Descripción |
|---|---|
| `current_pbx` | Filtra por `pbx_connection_id` según la central activa en sesión |
| `creating` (evento) | Auto-asigna `pbx_connection_id` al crear |

### Campos Fillable

| Campo | Tipo | Descripción |
|---|---|---|
| `pbx_connection_id` | int | ID de la central PBX |
| `extension` | string | Número de extensión (ej: 1001, 4444) |
| `first_name` | string | Nombre de pila |
| `last_name` | string | Apellido |
| `fullname` | string | Nombre completo/alias personalizado |
| `email` | string | Correo electrónico |
| `phone` | string | Teléfono de contacto |
| `ip` | string | Dirección IP del dispositivo registrado |
| `permission` | string | Permisos: Internal, Local, National, International |
| `do_not_disturb` | boolean | Modo No Molestar activado |
| `max_contacts` | int | Máximo de dispositivos registrados simultáneamente |
| `secret` | string | Contraseña SIP |

### Relaciones

| Método | Tipo | Modelo Relacionado |
|---|---|---|
| `pbxConnection()` | BelongsTo | PbxConnection |

### Casts

| Campo | Tipo Cast |
|---|---|
| `do_not_disturb` | boolean |
| `max_contacts` | integer |

---

## 3. `PbxConnection` (app/Models/PbxConnection.php)

**Tabla:** `pbx_connections`  
**Propósito:** Representa una conexión a una central PBX Grandstream. Es el modelo central del sistema multi-tenant.

### Constantes de Estado

| Constante | Valor | Descripción |
|---|---|---|
| `STATUS_PENDING` | `pending` | Central creada, sin sincronizar |
| `STATUS_SYNCING` | `syncing` | Sincronización en progreso |
| `STATUS_READY` | `ready` | Lista para usar |
| `STATUS_ERROR` | `error` | Error en sincronización |

### Campos Fillable

| Campo | Tipo | Descripción |
|---|---|---|
| `name` | string | Nombre de la central |
| `ip` | string | Dirección IP de la central |
| `port` | int | Puerto de la API (ej: 7110) |
| `username` | string | Usuario para autenticación API |
| `password` | string (encrypted) | Contraseña (encriptada automáticamente) |
| `verify_ssl` | boolean | Verificar certificado SSL |
| `status` | string | Estado actual de la central |
| `sync_message` | string | Mensaje de progreso/error de sincronización |
| `last_sync_at` | datetime | Última sincronización exitosa |

### Casts

| Campo | Tipo Cast | Descripción |
|---|---|---|
| `password` | encrypted | Se encripta/desencripta automáticamente |
| `port` | integer | |
| `verify_ssl` | boolean | |
| `last_sync_at` | datetime | |

### Métodos

| Método | Retorno | Descripción |
|---|---|---|
| `isReady()` | bool | ¿La central está lista para usar? |
| `isSyncing()` | bool | ¿Está sincronizando? |
| `isPending()` | bool | ¿Está pendiente? |
| `getStatusDisplayName()` | string | Nombre en español del estado (Pendiente, Sincronizando, Lista, Error) |

### Relaciones

| Método | Tipo | Modelo Relacionado | Descripción |
|---|---|---|---|
| `calls()` | HasMany | Call | Llamadas de esta central |
| `extensions()` | HasMany | Extension | Extensiones de esta central |
| `users()` | BelongsToMany | User | Usuarios que tienen acceso a esta central. Usa tabla pivot `pbx_connection_user` |

---

## 4. `QueueCallDetail` (app/Models/QueueCallDetail.php)

**Tabla:** `queue_call_details`  
**Propósito:** Almacena detalles de llamadas de colas, sincronizados desde `queueapi` de Grandstream.

### Global Scopes (booted)

| Scope | Descripción |
|---|---|
| `pbx` | Filtra por `pbx_connection_id` según la sesión activa |

### Campos Fillable

| Campo | Tipo | Descripción |
|---|---|---|
| `pbx_connection_id` | int | ID de la central PBX |
| `queue` | string | Número de la cola (ej: 6500) |
| `caller` | string | Número del llamante |
| `agent` | string | Número del agente que atendió |
| `call_time` | datetime | Fecha/hora de la llamada |
| `wait_time` | int | Tiempo de espera en segundos |
| `talk_time` | int | Tiempo de conversación en segundos |
| `connected` | boolean | ¿Se conectó la llamada? |

### Scopes Locales (Query Scopes)

| Método | Parámetros | Descripción |
|---|---|---|
| `scopeForQueue()` | string $queue | Filtra por cola específica |
| `scopeConnected()` | - | Solo llamadas conectadas |
| `scopeForAgent()` | string $agent | Filtra por agente |
| `scopeBetweenDates()` | string $start, $end | Filtra por rango de fechas |

### Relaciones

| Método | Tipo | Modelo Relacionado |
|---|---|---|
| `pbxConnection()` | BelongsTo | PbxConnection |

---

## 5. `Setting` (app/Models/Setting.php)

**Tabla:** `settings`  
**Propósito:** Almacena configuraciones clave-valor (principalmente tarifas de llamadas).

### Campos Fillable

| Campo | Tipo | Descripción |
|---|---|---|
| `key` | string | Clave única (ej: price_mobile) |
| `label` | string | Etiqueta visible (ej: Precio Minuto Celular) |
| `value` | mixed | Valor de la configuración |

### Métodos Estáticos

| Método | Retorno | Descripción |
|---|---|---|
| `get(string $key, $default)` | mixed | Obtiene el valor de un setting por su key |
| `allAsArray()` | array | Devuelve todos los settings como array `key => value` |

---

## 6. `User` (app/Models/User.php)

**Tabla:** `users`  
**Propósito:** Usuarios del sistema con roles y permisos granulares.

### Campos Fillable

| Campo | Tipo | Descripción |
|---|---|---|
| `name` | string | Nombre de usuario |
| `email` | string | Correo electrónico |
| `password` | string (hashed) | Contraseña |
| `role` | string | Rol: admin, supervisor, user |
| `can_sync_calls` | boolean | Permiso: sincronizar llamadas |
| `can_edit_extensions` | boolean | Permiso: editar extensiones |
| `can_update_ips` | boolean | Permiso: actualizar IPs |
| `can_edit_rates` | boolean | Permiso: editar tarifas |
| `can_manage_pbx` | boolean | Permiso: gestionar centrales |
| `can_export_pdf` | boolean | Permiso: exportar PDF |
| `can_export_excel` | boolean | Permiso: exportar Excel |
| `can_view_charts` | boolean | Permiso: ver gráficos |

### Métodos de Rol

| Método | Retorno | Descripción |
|---|---|---|
| `isAdmin()` | bool | ¿Es administrador? (`role === 'admin'`) |
| `isUser()` | bool | ¿Es usuario regular? (`role === 'user'`) |
| `getRoleDisplayName()` | string | Nombre en español del rol |

### Métodos de Permisos

| Método | Retorno | Descripción |
|---|---|---|
| `hasPermission(string $perm)` | bool | Verifica un permiso específico. **Los admin siempre retornan true.** |
| `canSyncCalls()` | bool | Shortcut: ¿puede sincronizar llamadas? |
| `canEditExtensions()` | bool | Shortcut: ¿puede editar extensiones? |
| `canUpdateIps()` | bool | Shortcut: ¿puede actualizar IPs? |
| `canEditRates()` | bool | Shortcut: ¿puede editar tarifas? |
| `canManagePbx()` | bool | Shortcut: ¿puede gestionar centrales? |
| `canExportPdf()` | bool | Shortcut: ¿puede exportar PDF? |
| `canExportExcel()` | bool | Shortcut: ¿puede exportar Excel? |
| `canViewCharts()` | bool | Shortcut: ¿puede ver gráficos? |

### Métodos Estáticos

*No hay métodos estáticos en este modelo.*

### Relaciones

| Método | Tipo | Modelo Relacionado | Descripción |
|---|---|---|---|
| `pbxConnections()` | BelongsToMany | PbxConnection | Centrales PBX que el usuario tiene permitido ver. Usa tabla pivot `pbx_connection_user` |

> **Nota:** Los usuarios con rol `admin` ven todas las centrales automáticamente sin necesidad de asignación en la tabla pivot.
