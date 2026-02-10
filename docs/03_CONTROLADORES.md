# Controladores - Documentación Detallada

---

## 1. `CdrController` (app/Http/Controllers/CdrController.php)

**Propósito:** Controlador principal del dashboard de llamadas. Gestiona reportes, sincronización CDR, exportaciones y gráficos.

**Traits usados:** `GrandstreamTrait`, `ProcessesCdr`

### Métodos Públicos

#### `index(Request $request)`
**Ruta:** `GET /dashboard`  
**Vista:** `reporte`  
**Descripción:** Dashboard principal de llamadas. Muestra un listado paginado de llamadas con filtros.

**Parámetros del Request:**
- `fecha_inicio` (default: hoy)
- `fecha_fin` (default: hoy)
- `anexo` (opcional)
- `titulo` (default: 'Reporte de Llamadas')
- `tipo_llamada` (all/internal/external)
- `sort` (start_time/billsec/tipo/costo)
- `dir` (asc/desc)

**Lógica:**
1. Construye query con `buildCallQuery()` aplicando filtros
2. Genera datos para gráfico (llamadas por día)
3. Calcula totales: total llamadas, segundos, minutos facturables, costo
4. Aplica ordenamiento y pagina (50 por página)

---

#### `syncCDRs()`
**Ruta:** `POST /sync`  
**Permiso:** `canSyncCalls()`  
**Descripción:** Sincroniza CDRs desde la API Grandstream.

**Lógica:**
1. Verifica permiso del usuario
2. Busca la última llamada en BD para determinar desde cuándo sincronizar
3. Si no hay llamadas, sincroniza los últimos 30 días
4. Llama a `cdrapi` con rango de fechas
5. Procesa paquetes CDR con `processCdrPackets()`
6. Retorna conteo de nuevas y actualizadas

---

#### `descargarPDF(Request $request)`
**Ruta:** `GET /export-pdf`  
**Permiso:** `canExportPdf()`  
**Descripción:** Genera y descarga un reporte PDF de llamadas contestadas.

**Lógica:**
1. Incrementa memory_limit a 1024M y max_execution_time a 300s
2. Construye query filtrando solo `ANSWERED`
3. Limita a 500 registros para el PDF
4. Calcula totales (costo se calcula por chunks para eficiencia)
5. Genera PDF con DomPDF en formato carta vertical
6. Nombre del archivo: `Reporte_Llamadas_DDMMYYYY_HHiiss.pdf`

---

#### `exportarExcel(Request $request)`
**Ruta:** `GET /exportar-excel`  
**Permiso:** `canExportExcel()`  
**Descripción:** Exporta un reporte Excel usando `CallsExport`.

---

#### `showCharts(Request $request)`
**Ruta:** `GET /graficos`  
**Permiso:** `canViewCharts()`  
**Vista:** `graficos`  
**Descripción:** Muestra gráficos de llamadas.

**Datos generados:**
- Gráfico de torta: llamadas por disposition (ANSWERED, BUSY, etc.)
- Gráfico de línea: llamadas por día

---

### Métodos Privados

#### `buildCallQuery(string $fechaInicio, string $fechaFin, ?string $anexo, ?string $tipoLlamada)`
Construye el query base para llamadas con filtros.

**Filtrado por tipo de llamada:**
- `internal`: Usa `userfield IN ('Internal', 'Outbound')` + fallback regex para registros sin userfield
- `external`: Usa `userfield = 'Inbound'` + fallback regex

---

#### `validateSort(string $sort): string`
Valida que el campo de ordenamiento sea válido. Campos permitidos: `start_time`, `billsec`, `tipo`, `costo`.

---

#### `applySorting($query, string $sortBy, string $sortDir)`
Aplica ordenamiento al query. Para `tipo` y `costo` usa SQL CASE con `orderByRaw`.

---

#### `getTypeSortSql(string $dir): string`
Genera SQL CASE para ordenar por tipo de llamada (Interna=1, Local=2, Celular=3, Nacional=4, Internacional=5).

---

#### `getCostSortSql(string $dir): string`
Genera SQL CASE para ordenar por costo calculado en la BD.

---

#### `processCdrPackets(array $packets): array`
Procesa paquetes CDR: extrae segmentos, consolida y hace `updateOrCreate` en la BD. Retorna `['nuevas' => N, 'actualizadas' => N]`.

---

## 2. `ExtensionController` (app/Http/Controllers/ExtensionController.php)

**Propósito:** Gestiona extensiones: edición, actualización de IPs y desvíos de llamadas.

**Trait usado:** `GrandstreamTrait`

### Métodos Públicos

#### `index(Request $request)`
**Ruta:** `GET /configuracion`  
**Vista:** `configuracion`  
**Descripción:** Lista todas las extensiones paginadas (50 por página) con filtro por número.

---

#### `update(Request $request)`
**Ruta:** `POST /extension/update`  
**Permiso:** `canEditExtensions()`  
**Descripción:** Actualiza una extensión tanto en la **central Grandstream** como en la **BD local**.

**Proceso completo (6 fases):**
1. **Validación:** extension, first_name, last_name, email, phone, permission, max_contacts, secret
2. **Conexión:** Verifica conexión con la central (`testConnection()`)
3. **Obtener ID:** Consulta `getUser` para obtener el `user_id` interno de la central
4. **Preparar datos:** Traduce permisos (Internal → internal, National → internal-local-national, etc.) y DND (boolean → yes/no)
5. **Enviar cambios:** Dos peticiones a la API:
   - `updateUser`: Datos de identidad (nombre, apellido, email, teléfono)
   - `updateSIPAccount`: Config SIP (permisos, contactos, DND, secret)
6. **Verificar y guardar:** Si ambas peticiones exitosas → `applyChanges` + actualizar BD local

---

#### `updateName(Request $request)`
**Ruta:** (no mapeada en rutas explícitamente)  
**Descripción:** Actualiza solo el nombre personalizado (`fullname`) de una extensión.

---

#### `updateIps()`
**Ruta:** `POST /extension/update-ips`  
**Permiso:** `canUpdateIps()`  
**Descripción:** Actualiza las IPs de TODAS las extensiones desde la API.

**Lógica:**
1. Llama a `listAccount` con opciones `extension,addr`
2. Itera cada cuenta y actualiza el campo `ip` en BD local
3. Si `addr` es `-` o vacío, guarda `null`

---

#### `getCallForwarding(Request $request)`
**Ruta:** `GET /extension/forwarding`  
**Respuesta:** JSON  
**Descripción:** Obtiene la configuración de desvíos de llamadas de una extensión.

**Retorna:**
- Estado de presencia actual (`available`, `away`, `dnd`, etc.)
- Configuración de forwarding: CFB (busy), CFN (no answer), CFU (unconditional)
- Lista de colas disponibles

---

#### `updateCallForwarding(Request $request)`
**Ruta:** `POST /extension/forwarding`  
**Permiso:** `canEditExtensions()`  
**Respuesta:** JSON  
**Descripción:** Actualiza desvíos de llamadas. **IMPORTANTE:** Cada desvío se envía por separado con `applyChanges` posterior para que la PBX auto-detecte el tipo de destino.

---

## 3. `PbxConnectionController` (app/Http/Controllers/PbxConnectionController.php)

**Propósito:** Gestión completa de centrales PBX: CRUD, selección, sincronización.

**Trait usado:** `GrandstreamTrait`

### Métodos Públicos

#### `index(): View`
**Ruta:** `GET /pbx`  
**Descripción:** Lista centrales PBX. **Filtra por acceso del usuario:**
- **Admin:** Ve todas las centrales
- **No admin:** Solo ve centrales con estado `ready` que tenga asignadas en la tabla pivot `pbx_connection_user`

---

#### `store(Request $request): RedirectResponse`
**Ruta:** `POST /pbx` (Solo admin)  
**Descripción:** Crea nueva central con estado `pending`, redirige a setup.

---

#### `update(Request $request, PbxConnection $pbx): RedirectResponse`
**Ruta:** `PUT /pbx/{pbx}` (Solo admin)  
**Descripción:** Actualiza datos de una central. Si no se envía password, no se modifica.

---

#### `destroy(PbxConnection $pbx): RedirectResponse`
**Ruta:** `DELETE /pbx/{pbx}` (Solo admin)  
**Descripción:** Elimina central y TODOS sus datos relacionados (calls y extensions) usando `withoutGlobalScope`.

---

#### `select(PbxConnection $pbx): RedirectResponse`
**Ruta:** `GET /pbx/select/{pbx}`  
**Descripción:** Selecciona una central para trabajar. **Verifica autorización:** los usuarios no-admin deben tener la central asignada en la tabla pivot, o se retorna error 403. Lógica de redirección:
- Si `ready` → Dashboard
- Si `syncing` y admin → Setup
- Si `pending` y admin → Setup
- Si no `ready` y no admin → Error

---

#### `setup(PbxConnection $pbx): View`
**Ruta:** `GET /pbx/setup/{pbx}` (Solo admin)  
**Vista:** `pbx.setup`  
**Descripción:** Página de configuración/sincronización inicial. Muestra conteo de extensiones y llamadas.

---

#### `checkSyncStatus(PbxConnection $pbx): JsonResponse`
**Ruta:** `GET /pbx/sync-status/{pbx}`  
**Descripción:** Endpoint AJAX para verificar progreso de sincronización.

---

#### `syncExtensions(PbxConnection $pbx): JsonResponse`
**Ruta:** `POST /pbx/sync-extensions/{pbx}` (Solo admin)  
**Descripción:** Sincroniza TODAS las extensiones desde la central. Proceso:
1. Marca central como `syncing`
2. Llama a `listUser` para obtener lista
3. Para cada usuario, obtiene detalles SIP con `getSIPAccount`
4. Parsea permisos y guarda con `updateOrCreate`

---

#### `syncCalls(Request $request, PbxConnection $pbx): JsonResponse`
**Ruta:** `POST /pbx/sync-calls/{pbx}` (Solo admin)  
**Descripción:** Sincroniza llamadas por mes (recibe `year` y `month`). Usa `cdrapi` y procesa CDRs.

---

#### `finishSync(PbxConnection $pbx): JsonResponse`
**Ruta:** `POST /pbx/finish-sync/{pbx}` (Solo admin)  
**Descripción:** Marca la sincronización como completada (status → `ready`).

---

#### `disconnect(): RedirectResponse`
**Ruta:** `POST /pbx/disconnect` (Solo admin)  
**Descripción:** Limpia la sesión de la central activa.

---

### Métodos Privados

| Método | Descripción |
|---|---|
| `validatePbx(Request, bool)` | Valida datos de formulario PBX |
| `setActivePbx(PbxConnection)` | Guarda ID y nombre de la central en sesión |
| `setPbxConnection(PbxConnection)` | Configura la sesión para el trait |
| `collectCdrSegments(array)` | Recolecta segmentos CDR recursivamente |
| `consolidateCallData(array)` | Consolida múltiples segmentos en un registro |
| `esAnexo(string)` | ¿Es extensión interna? (3-4 dígitos) |
| `esExterno(string)` | ¿Es número externo? (>4 dígitos o empieza con + o 9) |
| `parseExtensionPermission(string)` | Traduce formato API → formato local |
| `determineCallType(string)` | Clasifica tipo: Interna, Celular, Nacional, Internacional |

---

## 4. `StatsController` (app/Http/Controllers/StatsController.php)

**Propósito:** Estadísticas y KPIs de colas de llamadas.

### Métodos Públicos

#### `index(Request $request)`
**Ruta:** `GET /stats/kpi-turnos`  
**Vista:** `stats.kpi-turnos`  
**Descripción:** Muestra KPIs completos de colas (EXCLUSIVO PARA QUEUE).

**Datos enviados a la vista:**
- `kpisPorHora`: KPIs por franja horaria (08:00-20:00)
- `kpisPorCola`: KPIs agrupados por número de cola
- `totales`: Totales globales
- `rendimientoAgentes`: Métricas por agente
- `agentesPorCola`: Agentes detallados por cola
- `colasDisponibles`: Lista de colas para filtrar
- `ultimaSincronizacion`: Fecha de última sincronización

---

#### `apiKpis(Request $request)`
**Ruta:** `GET /stats/kpi-turnos/api`  
**Respuesta:** JSON  
**Descripción:** Endpoint API para obtener KPIs (útil para gráficos AJAX).

---

#### `sincronizarColas(Request $request)`
**Ruta:** `POST /stats/kpi-turnos/sync`  
**Permiso:** Solo admin  
**Descripción:** Ejecuta el comando `sync:queue-stats` via `Artisan::call()`.

---

### Métodos Privados

| Método | Descripción |
|---|---|
| `calcularKpisPorHora(fechaInicio, fechaFin, ?cola)` | Calcula volumen, atendidas, abandonadas, % abandono, ASA por franja horaria. **Fuente: `queue_call_details`** |
| `obtenerRendimientoAgentes(fechaInicio, fechaFin, ?cola)` | Métricas por agente: llamadas atendidas, tiempo promedio, tasa de atención. **Fuente: `queue_call_details`** |
| `calcularTotales(kpisPorHora)` | Suma global de todos los KPIs por hora |
| `calcularKpisPorCola(fechaInicio, fechaFin)` | KPIs agrupados por cola: volumen, atendidas, abandonadas, ASA |
| `obtenerColasDisponibles()` | Obtiene colas únicas combinando `calls.action_type` y `queue_call_details.queue` |
| `obtenerAgentesPorCola(fechaInicio, fechaFin)` | Estadísticas detalladas de agentes por cola |
| `extraerAgente(?dstChannel, ?dstanswer, ?channel, ?source)` | Extrae número de agente de canales SIP (PJSIP/2000-xxx) |
| `extraerCola(?actionType)` | Extrae número de cola de action_type (QUEUE[6500] → 6500) |

---

## 5. `UserController` (app/Http/Controllers/UserController.php)

**Propósito:** CRUD completo de usuarios del sistema + endpoints API para el modal en la página de centrales PBX.

### Métodos de Vistas (CRUD Estándar)

| Método | Ruta | Descripción |
|---|---|---|
| `index()` | `GET /usuarios` | Lista usuarios paginados (15) |
| `create()` | `GET /usuarios/crear` | Formulario de creación |
| `store(Request)` | `POST /usuarios` | Crea usuario. Si rol=admin, otorga todos los permisos. Hashea password |
| `edit(User)` | `GET /usuarios/{user}/editar` | Formulario de edición. **No permite editarse a sí mismo** |
| `update(Request, User)` | `PUT /usuarios/{user}` | Actualiza usuario. Password opcional. Si rol=admin, otorga todos los permisos |
| `destroy(User)` | `DELETE /usuarios/{user}` | Elimina usuario. **No permite eliminar último admin ni a sí mismo** |

### Métodos API (para modal en PBX index)

| Método | Ruta | Descripción |
|---|---|---|
| `apiIndex()` | `GET /api/usuarios` | Retorna JSON con lista de usuarios (incluyendo `allowed_pbx_ids` por usuario) y lista de centrales PBX disponibles (`pbxConnections` con id, name, ip) |
| `apiStore(Request)` | `POST /api/usuarios` | Crea usuario via JSON. Valida `allowed_pbx_ids` (array de IDs de pbx_connections). Sincroniza tabla pivot `pbx_connection_user` |
| `apiUpdate(Request, User)` | `PUT /api/usuarios/{user}` | Actualiza usuario via JSON. Sincroniza centrales permitidas en tabla pivot |
| `apiDestroy(User)` | `DELETE /api/usuarios/{user}` | Elimina usuario via JSON. No permite eliminar último admin ni a sí mismo |

---

## 6. `SettingController` (app/Http/Controllers/SettingController.php)

**Propósito:** Gestión de tarifas de llamadas.

| Método | Ruta | Permiso | Descripción |
|---|---|---|---|
| `index()` | `GET /tarifas` | - | Muestra página de configuración de tarifas |
| `update(Request)` | `POST /tarifas` | `canEditRates()` | Actualiza valores de tarifas |

---

## 7. `AuthController` (app/Http/Controllers/AuthController.php)

**Propósito:** Autenticación personalizada (login/logout).

| Método | Ruta | Descripción |
|---|---|---|
| `showLogin()` | `GET /login` | Muestra formulario de login |
| `login(Request)` | `POST /login` | Autentica usuario por `name` + `password`. **Easter egg:** si name=doom y password=doom → redirige a ruta `doom` |
| `logout(Request)` | `POST /logout` | Cierra sesión, invalida y regenera token |

---

## 8. `EstadoCentral` (app/Http/Controllers/EstadoCentral.php)

**Propósito:** Muestra estado del sistema/uptime de la central.

**Trait usado:** `GrandstreamTrait`

| Método | Descripción |
|---|---|
| `index()` | Renderiza vista `welcome` con datos del sistema |
| `getSystemData(): array` | Obtiene uptime de la central vía `getSystemStatus`. Retorna `['uptime' => string]` |

---

## 9. `IPController` (app/Http/Controllers/IPController.php)

**Propósito:** Monitor de extensiones en tiempo real (IPs y estados).

**Trait usado:** `GrandstreamTrait`

| Método | Descripción |
|---|---|
| `index()` | Obtiene extensiones locales + datos en vivo de la API (`listAccount`). Renderiza vista `monitor.index` |
| `fetchLiveAccounts(): array` (privado) | Llama a `listAccount` con opciones `extension,status,addr,fullname` |
| `parseAddress(?string): string` (privado) | Parsea IP del campo addr. Si es `-` o vacío, retorna `---` |

---

## 10. `ProfileController` (app/Http/Controllers/ProfileController.php)

**Propósito:** Gestión del perfil del usuario autenticado (de Laravel Breeze).

| Método | Descripción |
|---|---|
| `edit(Request)` | Muestra formulario de edición del perfil |
| `update(ProfileUpdateRequest)` | Actualiza nombre/email. Si cambia email, resetea verificación |
| `destroy(Request)` | Elimina la cuenta del usuario (requiere contraseña actual) |

---

## 11. `ProcessesCdr` Trait (app/Http/Controllers/Concerns/ProcessesCdr.php)

**Propósito:** Trait compartido para procesar registros CDR de Grandstream.

| Método | Descripción |
|---|---|
| `collectCdrSegments(array $node): array` | Recolecta recursivamente todos los segmentos de un paquete CDR. Busca nodos con campo `start` y sub_cdr anidados |
| `consolidateCdrSegments(array $segments): array` | Consolida múltiples segmentos en un solo registro de llamada. Determina tipo (entrante/saliente), captura tiempos, origen/destino, disposition |
| `isExtension(string): bool` | ¿Es extensión interna? (regex: `^\d{3,4}$`) |
| `isExternalNumber(string): bool` | ¿Es número externo? (regex: `^(\+|\d{5,})`) |
