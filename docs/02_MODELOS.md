# Modelos Eloquent — Documentación Detallada

> Este documento describe los 6 modelos Eloquent del sistema, sus reglas de negocio, mecanismos de aislamiento multi-tenant y lógica de facturación.

---

## 1. `Call` (app/Models/Call.php)

**Tabla:** `calls`  
**Propósito:** Representa un registro de llamada (CDR) sincronizado desde la central Grandstream. Es el modelo con mayor lógica de negocio del sistema, implementando cálculo de costos específico para el mercado de telecomunicaciones chileno.

### Patrón Multi-Tenant

| Mecanismo | Detalle |
|---|---|
| **Global Scope `current_pbx`** | Agrega automáticamente `WHERE pbx_connection_id = session('active_pbx_id')` a todas las consultas. Solo se activa cuando hay usuario autenticado Y central seleccionada en sesión. |
| **Evento `creating`** | Al insertar un nuevo registro, auto-asigna `pbx_connection_id` desde la sesión si no se especifica explícitamente. Garantiza que toda llamada queda asociada a una central. |

> **Nota de seguridad:** Operaciones administrativas (eliminación de central, sincronización) deben usar `::withoutGlobalScope('current_pbx')` para acceder a todos los registros.

### Campos Fillable

| Campo | Tipo | Descripción | Ejemplo |
|---|---|---|---|
| `pbx_connection_id` | int | FK a la central PBX origen | `1` |
| `unique_id` | string | ID único de la llamada en Asterisk/UCM | `1738234567.42` |
| `start_time` | datetime | Hora de inicio de la llamada | `2026-02-10 14:30:00` |
| `answer_time` | datetime | Hora en que se contestó (null si no contestada) | `2026-02-10 14:30:05` |
| `end_time` | datetime | Hora de fin de la llamada | `2026-02-10 14:35:00` |
| `source` | string | Origen: número del anexo interno o número externo | `1760`, `+56912345678` |
| `destination` | string | Destino: número marcado o extensión receptora | `956781234`, `4444` |
| `dstanswer` | string | Extensión/agente que finalmente contestó | `4445` |
| `caller_name` | string | Nombre del llamante (CallerID name) | `Juan Pérez` |
| `duration` | int | Duración total en segundos (ring + conversación) | `305` |
| `billsec` | int | Segundos facturables (solo tiempo de conversación efectiva) | `270` |
| `disposition` | string | Estado final de la llamada | `ANSWERED`, `BUSY`, `NO ANSWER`, `FAILED` |
| `action_type` | string | Tipo de acción en el dialplan Asterisk | `DIAL`, `QUEUE[6500]` |
| `lastapp` | string | Última aplicación ejecutada en el dialplan | `Dial`, `Queue` |
| `channel` | string | Canal SIP de origen | `PJSIP/1760-00006d6f` |
| `dst_channel` | string | Canal SIP de destino | `PJSIP/4444-00006d70` |
| `src_trunk_name` | string | Nombre del trunk por donde entró/salió la llamada | `trunk_movistar` |
| `userfield` | string | Clasificación del UCM | `Inbound`, `Outbound`, `Internal` |
| `recording_file` | string | Ruta al archivo de grabación (si existe) | `/recordings/20260210-143000.wav` |

### Relaciones

| Método | Tipo | Modelo Relacionado | Descripción |
|---|---|---|---|
| `pbxConnection()` | BelongsTo | PbxConnection | Central PBX a la que pertenece la llamada |

### Accessors (Atributos Calculados)

#### `getCostAttribute(): int` — Motor de Facturación Chileno

Calcula el **costo de la llamada** en CLP (pesos chilenos) basado en el destino y la duración. Este accessor implementa las reglas de facturación específicas del mercado de telecomunicaciones chileno.

**Reglas de exclusión (costo = 0):**

| Condición | Razón |
|---|---|
| `billsec <= 3` | Período de gracia — llamadas muy cortas (ring sin conversación real) |
| `userfield !== 'Outbound'` | Solo se cobran llamadas salientes. Entrantes e internas son gratuitas |
| Destino 3-4 dígitos (`/^\d{3,4}$/`) | Extensión interna — llamada entre anexos |
| Destino empieza con `800` (`/^800/`) | Número toll-free Chile — sin costo para el llamante |

**Clasificación por regex del destino chileno:**

| Patrón | Regex | Tarifa Aplicada | Tipo |
|---|---|---|---|
| Celular sin prefijo | `/^9\d{8}$/` | `price_mobile` | Celular |
| Celular con +56 | `/^(\+?56)9\d{8}$/` | `price_mobile` | Celular |
| Fijo Santiago sin prefijo | `/^2\d{8}$/` | `price_national` | Nacional |
| Fijo Santiago con +56 | `/^(\+?56)2\d{8}$/` | `price_national` | Nacional |
| Fijo Regiones sin prefijo | `/^[3-8]\d{8}$/` | `price_national` | Nacional |
| Fijo Regiones con +56 | `/^(\+?56)[3-8]\d{8}$/` | `price_national` | Nacional |
| Costo compartido 600 | `/^600\d+$/` | `price_national` | Nacional |
| Internacional (no Chile) | `/^(\+|00)/` AND NOT `/^(\+?56)/` | `price_international` | Internacional |
| **Cualquier otro destino** | Default fallback | `price_national` | Nacional |

**Fórmula de cálculo:**
$$\text{costo} = \lceil \frac{\text{billsec}}{60} \rceil \times \text{tarifa\_por\_minuto}$$

> Los minutos se redondean **siempre hacia arriba** (`ceil`). Una llamada de 61 segundos se cobra como 2 minutos.

**Cache estática de tarifas:** Usa propiedad `$cachedPrices` para evitar queries repetidas a la tabla `settings` cuando se calculan costos de múltiples llamadas en una misma request (ej: listado de 50 registros paginados).

#### `getCallTypeAttribute(): string`

Determina el **tipo de llamada** según el número de destino. Usa los mismos patrones regex que `getCostAttribute`.

| Retorno | Condición |
|---|---|
| `Interna` | Destino 3-4 dígitos |
| `Celular` | Destino móvil chileno (9XXXXXXXX) |
| `Nacional` | Destino fijo nacional, 600 o 800 |
| `Internacional` | Prefijo + o 00 (no Chile) |
| `Local` | Fallback por defecto |

### Métodos Estáticos

| Método | Retorno | Descripción |
|---|---|---|
| `getPrices()` | array | Obtiene las tarifas desde tabla `settings` y las cachea estáticamente. Keys: `price_mobile`, `price_national`, `price_international`. Si no existen, usa defaults (80, 40, 500 CLP) |
| `clearPricesCache()` | void | Limpia el cache estático. **Debe llamarse** cuando se actualizan tarifas en `settings` |

---

## 2. `Extension` (app/Models/Extension.php)

**Tabla:** `extensions`  
**Propósito:** Representa una extensión/anexo telefónico de la central PBX. Almacena tanto datos sincronizados desde la API Grandstream como datos editados localmente.

### Patrón Multi-Tenant

| Mecanismo | Detalle |
|---|---|
| **Global Scope `current_pbx`** | Filtra por `pbx_connection_id` según la central activa en sesión |
| **Evento `creating`** | Auto-asigna `pbx_connection_id` al crear nuevos registros |

### Campos Fillable

| Campo | Tipo | Descripción | Ejemplo |
|---|---|---|---|
| `pbx_connection_id` | int | FK a la central PBX | `1` |
| `extension` | string | Número de extensión (único por central) | `1001`, `4444` |
| `first_name` | string | Nombre de pila del usuario | `Juan` |
| `last_name` | string | Apellido del usuario | `Pérez` |
| `fullname` | string | Nombre personalizado/alias local (editable sin tocar la PBX) | `Recepción Principal` |
| `email` | string | Correo electrónico asociado | `juan@empresa.cl` |
| `phone` | string | Teléfono de contacto directo | `+56912345678` |
| `ip` | string | Dirección IP del dispositivo registrado (actualizada desde `listAccount`) | `192.168.1.105` |
| `permission` | string | Nivel de permisos de marcado. Determina a dónde puede llamar esta extensión | `Internal`, `Local`, `National`, `International` |
| `do_not_disturb` | boolean | Modo No Molestar activado en la PBX | `true`/`false` |
| `max_contacts` | int | Máximo de dispositivos SIP registrados simultáneamente (1-10) | `2` |
| `secret` | string | Contraseña SIP/IAX para registro del dispositivo. Campo sensible | `Ab3$kL9m` |

### Jerarquía de Permisos de Marcado

Los permisos son acumulativos según la configuración de la PBX Grandstream:

```
Internal ⊂ Local ⊂ National ⊂ International

Internal     → Solo llamadas entre extensiones internas
Local        → Internal + llamadas locales
National     → Local + llamadas nacionales  
International→ National + llamadas internacionales
```

**Mapeo API ↔ Local:**

| Formato API Grandstream | Formato Local |
|---|---|
| `internal` | `Internal` |
| `internal-local` | `Local` |
| `internal-local-national` | `National` |
| `internal-local-national-international` | `International` |

### Relaciones

| Método | Tipo | Modelo Relacionado |
|---|---|---|
| `pbxConnection()` | BelongsTo | PbxConnection |

### Casts

| Campo | Tipo Cast | Propósito |
|---|---|---|
| `do_not_disturb` | boolean | Convierte `0/1` o `yes/no` a `true/false` PHP |
| `max_contacts` | integer | Garantiza operaciones numéricas correctas |

---

## 3. `PbxConnection` (app/Models/PbxConnection.php)

**Tabla:** `pbx_connections`  
**Propósito:** Representa una conexión a una central PBX Grandstream UCM. Es el **modelo central del sistema multi-tenant** — todo el modelo de datos gira alrededor de este modelo. Define la identidad, credenciales y estado de sincronización de cada central administrada.

### Máquina de Estados

El campo `status` implementa una máquina de estados finitos que controla el ciclo de vida de cada central:

```
┌──────────┐  syncExtensions()  ┌──────────┐  finishSync()   ┌─────────┐
│ PENDING  │ ────────────────►  │ SYNCING  │ ──────────────► │  READY  │
│ (creada) │                    │ (import) │                 │ (activa)│
└──────────┘                    └────┬─────┘                 └─────────┘
                                     │ error
                                     ▼
                                ┌─────────┐  reintentar
                                │  ERROR  │ ──────────────► SYNCING
                                └─────────┘
```

| Constante | Valor | Display (español) | Significado operativo |
|---|---|---|---|
| `STATUS_PENDING` | `pending` | Pendiente | Recién creada, requiere configuración inicial y sincronización |
| `STATUS_SYNCING` | `syncing` | Sincronizando... | Proceso de importación de datos en curso (extensiones y/o llamadas) |
| `STATUS_READY` | `ready` | Lista | Completamente funcional, puede ser seleccionada por usuarios |
| `STATUS_ERROR` | `error` | Error | Fallo en la sincronización, requiere intervención del admin |

**Reglas de acceso según estado:**
- **Solo admin** puede acceder a centrales en estado `pending`, `syncing` o `error`
- **Todos los usuarios** pueden acceder a centrales con estado `ready` (si tienen asignación en tabla pivot)
- La selección de central (`select()`) valida estado + permisos de acceso

### Campos Fillable

| Campo | Tipo | Descripción | Seguridad |
|---|---|---|---|
| `name` | string | Nombre descriptivo de la central | — |
| `ip` | string | Dirección IP o hostname de la central | — |
| `port` | int | Puerto de la API REST (ej: 7110, 8089) | — |
| `username` | string | Usuario para autenticación en la API | — |
| `password` | string | Contraseña de la API | **Encriptado at-rest** (cast `encrypted`) |
| `verify_ssl` | boolean | ¿Verificar certificado SSL al conectar? (default: false) | — |
| `status` | string | Estado actual en la máquina de estados | — |
| `sync_message` | string | Mensaje de progreso o error de la última sincronización | — |
| `last_sync_at` | datetime | Timestamp de la última sincronización exitosa | — |

### Casts y Seguridad

| Campo | Tipo Cast | Propósito de seguridad |
|---|---|---|
| `password` | `encrypted` | Se encripta automáticamente al guardar y se desencripta al leer. Usa la `APP_KEY` de Laravel. **Nunca se almacena en texto plano.** |
| `port` | `integer` | Validación de tipo |
| `verify_ssl` | `boolean` | Conversión limpia |
| `last_sync_at` | `datetime` | Instancia Carbon para cálculos de tiempo (`diffForHumans()`) |

### Métodos de Estado

| Método | Retorno | Uso |
|---|---|---|
| `isReady()` | bool | Controla si la central es seleccionable por usuarios no-admin |
| `isSyncing()` | bool | Muestra indicador de progreso en la UI |
| `isPending()` | bool | Redirige a página de setup en lugar de dashboard |
| `getStatusDisplayName()` | string | Nombre en español para badges en la UI: Pendiente, Sincronizando, Lista, Error |

### Relaciones

| Método | Tipo | Modelo Relacionado | Cardinalidad | Descripción |
|---|---|---|---|---|
| `calls()` | HasMany | Call | 1:N | Todas las llamadas CDR de esta central. Puede ser miles/millones de registros |
| `extensions()` | HasMany | Extension | 1:N | Extensiones/anexos telefónicos. Típicamente 10-500 por central |
| `users()` | BelongsToMany | User | N:M | Usuarios con acceso autorizado. Tabla pivot: `pbx_connection_user` |

> **Eliminación en cascada:** Al eliminar una central con `destroy()`, el controlador elimina manualmente calls y extensions usando `withoutGlobalScope` para evitar limitación por el scope activo. La tabla pivot usa `CASCADE DELETE` en la FK.

---

## 4. `QueueCallDetail` (app/Models/QueueCallDetail.php)

**Tabla:** `queue_call_details`  
**Propósito:** Almacena cada intento individual de llamada dentro de una cola, sincronizado desde la API `queueapi` de Grandstream. Cada registro representa **un intento de contacto con un agente**, no una llamada completa — una llamada puede generar múltiples registros si suena en varios agentes.

### Patrón Multi-Tenant

| Mecanismo | Detalle |
|---|---|
| **Global Scope `pbx`** | Filtra por `pbx_connection_id` según la sesión activa. Nombre de scope diferente al de Call/Extension pero misma funcionalidad |

### Campos Fillable

| Campo | Tipo | Descripción | Origen API |
|---|---|---|---|
| `pbx_connection_id` | int | FK a la central PBX | Asignado en sync |
| `queue` | string | Número de la cola (ej: 6500, 6501) | `extension` (!) — campo confuso en la API |
| `caller` | string | Número del llamante que entró a la cola | `callernum` |
| `agent` | string | Extensión del agente que atendió/intentó atender. `"NONE"` si ninguno contestó | `agent` |
| `call_time` | datetime | Fecha/hora del intento de llamada | `start_time` |
| `wait_time` | int | Segundos que esperó en cola antes de ser atendido o abandonar | `wait_time` (viene como string) |
| `talk_time` | int | Segundos de conversación efectiva (0 si no conectó) | `talk_time` (viene como string) |
| `connected` | boolean | ¿Se conectó exitosamente esta llamada con este agente? | `connect` ("yes"/"no") |

> **Conocimiento crítico de la API:** El campo `extension` en la respuesta de Grandstream indica el **número de cola**, NO la extensión del agente. El campo `agent` contiene la extensión del agente. Esta nomenclatura confusa está documentada en `ESTRUCTURA_QUEUEAPI.md`.

### Deduplicación

La API Grandstream envía **~22% de registros duplicados** en sus respuestas. El sistema implementa deduplicación en 3 capas:

1. **In-batch:** Clave compuesta `{queue}|{caller}|{agent}|{call_time}` — evita procesar duplicados en la misma respuesta
2. **DB check:** `exists()` antes de cada insert — evita duplicados entre sincronizaciones
3. **Unique constraint:** Restricción de BD como safety net

### Scopes Locales (Query Scopes)

| Método | Parámetros | Descripción | Uso |
|---|---|---|---|
| `scopeForQueue()` | `string $queue` | `WHERE queue = $queue` | Filtrar KPIs por cola específica |
| `scopeConnected()` | — | `WHERE connected = true` | Métricas de llamadas atendidas |
| `scopeForAgent()` | `string $agent` | `WHERE agent = $agent` | Rendimiento individual de agente |
| `scopeBetweenDates()` | `string $start, $end` | `WHERE call_time BETWEEN` | Filtros de rango de fechas |

### Relaciones

| Método | Tipo | Modelo Relacionado |
|---|---|---|
| `pbxConnection()` | BelongsTo | PbxConnection |

---

## 5. `Setting` (app/Models/Setting.php)

**Tabla:** `settings`  
**Propósito:** Almacena configuraciones clave-valor del sistema. Actualmente se usa exclusivamente para **tarifas de facturación de llamadas**, pero el diseño es extensible a cualquier configuración global.

### Campos Fillable

| Campo | Tipo | Descripción | Ejemplo |
|---|---|---|---|
| `key` | string | Clave única identificadora | `price_mobile` |
| `label` | string | Etiqueta visible en la UI | `Precio Minuto Celular` |
| `value` | mixed | Valor de la configuración (almacenado como string en BD) | `80` |

### Configuraciones Actuales (Seeders)

| Key | Label | Default | Unidad |
|---|---|---|---|
| `price_mobile` | Precio Minuto Celular | 80 | CLP/minuto |
| `price_national` | Precio Minuto Fijo Nacional | 40 | CLP/minuto |
| `price_international` | Precio Minuto Internacional | 500 | CLP/minuto |

### Métodos Estáticos

| Método | Retorno | Descripción |
|---|---|---|
| `get(string $key, $default)` | mixed | Obtiene el valor de un setting por su key. Si no existe, retorna `$default` |
| `allAsArray()` | array | Devuelve todos los settings como array asociativo `['key' => 'value']`. Usado para cargar todas las tarifas de una vez |

> **Relación con el modelo Call:** El accessor `getCostAttribute()` de `Call` consume las tarifas de `Setting` a través de `Call::getPrices()`, que usa cache estática para evitar N+1 queries.

---

## 6. `User` (app/Models/User.php)

**Tabla:** `users`  
**Propósito:** Usuarios del sistema con modelo de autorización dual: roles jerárquicos + permisos booleanos granulares. Controla tanto el acceso funcional como el acceso a centrales PBX específicas.

### Campos Fillable

| Campo | Tipo | Descripción | Default |
|---|---|---|---|
| `name` | string | Nombre de usuario (usado para login) | — |
| `email` | string | Correo electrónico | — |
| `password` | string | Contraseña (hasheada con bcrypt) | — |
| `role` | string | Rol: `admin`, `supervisor`, `user` | `user` |
| `can_sync_calls` | boolean | Permiso: sincronizar CDRs desde la central | `false` |
| `can_edit_extensions` | boolean | Permiso: editar extensiones y desvíos en la PBX | `false` |
| `can_update_ips` | boolean | Permiso: actualizar IPs de extensiones | `false` |
| `can_edit_rates` | boolean | Permiso: modificar tarifas de facturación | `false` |
| `can_manage_pbx` | boolean | Permiso: crear/editar/eliminar conexiones PBX | `false` |
| `can_export_pdf` | boolean | Permiso: generar y descargar reportes PDF | `false` |
| `can_export_excel` | boolean | Permiso: generar y descargar reportes Excel | `false` |
| `can_view_charts` | boolean | Permiso: acceder a gráficos y dashboard de colas/KPIs | `false` |

### Modelo de Autorización

**Jerarquía de Roles:**

| Rol | Acceso UI | Bypass de permisos | Acceso a centrales |
|---|---|---|---|
| `admin` | Completo (todas las secciones + administración) | **Sí** — `hasPermission()` siempre retorna `true` | **Todas** las centrales automáticamente |
| `supervisor` | Según permisos asignados | No | Solo centrales asignadas en tabla pivot |
| `user` | Según permisos asignados | No | Solo centrales asignadas en tabla pivot |

**Regla de negocio clave:** Los permisos booleanos solo son evaluados para `supervisor` y `user`. Cuando se crea un usuario con rol `admin`, el controlador automáticamente activa todos los permisos booleanos a `true` (por consistencia, aunque no se evalúan).

### Métodos de Rol

| Método | Retorno | Descripción |
|---|---|---|
| `isAdmin()` | bool | `role === 'admin'` |
| `isUser()` | bool | `role === 'user'` |
| `getRoleDisplayName()` | string | `admin` → `Administrador`, `supervisor` → `Supervisor`, `user` → `Usuario` |

### Métodos de Permisos

| Método | Retorno | Lógica |
|---|---|---|
| `hasPermission(string $perm)` | bool | **Admin → siempre `true`**. Otros roles → evalúa `$this->{$perm}` dinámicamente |
| `canSyncCalls()` | bool | `hasPermission('can_sync_calls')` |
| `canEditExtensions()` | bool | `hasPermission('can_edit_extensions')` |
| `canUpdateIps()` | bool | `hasPermission('can_update_ips')` |
| `canEditRates()` | bool | `hasPermission('can_edit_rates')` |
| `canManagePbx()` | bool | `hasPermission('can_manage_pbx')` |
| `canExportPdf()` | bool | `hasPermission('can_export_pdf')` |
| `canExportExcel()` | bool | `hasPermission('can_export_excel')` |
| `canViewCharts()` | bool | `hasPermission('can_view_charts')` |

### Relaciones

| Método | Tipo | Modelo Relacionado | Descripción |
|---|---|---|---|
| `pbxConnections()` | BelongsToMany | PbxConnection | Centrales PBX que el usuario puede acceder. Tabla pivot: `pbx_connection_user` con `CASCADE DELETE` en ambas FK |

### Protecciones de Integridad

El `UserController` implementa las siguientes protecciones:
- **No auto-eliminación:** Un admin no puede eliminarse a sí mismo
- **Último admin:** No se puede eliminar si es el único usuario con rol `admin` en el sistema
- **No auto-edición** (vistas): El botón editar no aparece para el usuario actual en el listado

> **Nota:** Los usuarios con rol `admin` ven todas las centrales automáticamente sin necesidad de registros en la tabla pivot `pbx_connection_user`. La verificación se realiza en `PbxConnectionController@select()` y `PbxConnectionController@index()`.

---

## Diagrama de Relaciones Completo

```
    ┌──────────────────┐
    │   PbxConnection  │ ◄── Modelo central multi-tenant
    │──────────────────│
    │ id, name, ip     │
    │ port, username   │
    │ password (enc)   │
    │ status, sync_msg │
    │ last_sync_at     │
    └──┬───┬───┬───┬───┘
       │   │   │   │
   1:N │   │   │   │ N:M (pivot: pbx_connection_user)
       │   │   │   │
       ▼   │   │   └──────────────────┐
 ┌────────┐│   │                      │
 │  Call  ││ 1:N                      ▼
 │────────││   │                 ┌─────────┐
 │unique  ││   ▼                 │  User   │
 │source  ││┌───────────────┐    │─────────│
 │dest    │││QueueCallDetail│    │name     │
 │billsec ││├───────────────│    │role     │
 │cost(*) │││queue, agent   │    │can_sync │
 │type(*) │││wait_time      │    │can_edit │
 └────────┘││connected      │    │...x8    │
           │└───────────────┘    └─────────┘
          1:N
           │
           ▼
      ┌───────────┐          ┌──────────┐
      │ Extension │          │ Setting  │ ◄── Sin relaciones FK
      │───────────│          │──────────│     Standalone clave-valor
      │extension  │          │key       │
      │fullname   │          │value     │
      │ip, perms  │          │label     │
      │DND, secret│          └──────────┘
      └───────────┘

(*) Accessors calculados, no columnas en BD
```
