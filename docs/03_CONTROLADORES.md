# Controladores — Documentación Detallada

> Este documento describe los 10 controladores del sistema, sus flujos de negocio, reglas de autorización y patrones de integración con la API Grandstream.

---

## 1. `CdrController` (app/Http/Controllers/CdrController.php)

**Propósito:** Controlador principal del dashboard. Gestiona la visualización de CDRs, sincronización con la central, cálculo de KPIs financieros y exportación de reportes. Es el controlador más usado del sistema.

**Traits usados:** `GrandstreamTrait`, `ProcessesCdr`

### Métodos Públicos

#### `index(Request $request)` — Dashboard Principal
**Ruta:** `GET /dashboard`  
**Vista:** `reporte`  
**Acceso:** Cualquier usuario autenticado con central seleccionada

**Parámetros del Request:**

| Parámetro | Default | Descripción |
|---|---|---|
| `fecha_inicio` | Hoy | Fecha inicio del filtro (Y-m-d) |
| `fecha_fin` | Hoy | Fecha fin del filtro (Y-m-d) |
| `anexo` | null | Filtrar por extensión origen |
| `titulo` | 'Reporte de Llamadas' | Título para exportación PDF |
| `tipo_llamada` | `all` | `all` / `internal` (salientes) / `external` (entrantes) |
| `sort` | `start_time` | Campo de ordenamiento |
| `dir` | `desc` | Dirección: `asc` / `desc` |

**Flujo de procesamiento:**
1. Construye query con `buildCallQuery()` aplicando todos los filtros
2. **Cálculo de costos en SQL** (no PHP) usando `CASE WHEN` que replica la lógica del accessor `getCostAttribute()`:
   ```sql
   SUM(CASE WHEN billsec <= 3 THEN 0
            WHEN userfield != 'Outbound' THEN 0
            WHEN destination REGEXP '^[0-9]{3,4}$' THEN 0
            WHEN destination REGEXP '^800' THEN 0
            WHEN destination REGEXP '^9[0-9]{8}$' THEN CEIL(billsec/60) * $mobile_rate
            ... END) as total_cost
   ```
3. Genera datos para gráfico de líneas (llamadas por día)
4. Calcula totales: total llamadas, segundos, minutos facturables, costo total
5. Aplica ordenamiento y paginación (50 registros/página)

> **Optimización de rendimiento:** Los totales financieros se calculan con agregación SQL directa, evitando cargar todos los registros en memoria PHP. Esto permite manejar miles de CDRs eficientemente.

---

#### `syncCDRs()` — Sincronización Incremental de CDRs
**Ruta:** `POST /sync`  
**Permiso:** `canSyncCalls()`

**Estrategia de sincronización incremental:**
1. Busca la última llamada en BD (`max(start_time)`)
2. Si existe → sincroniza desde `start_time - 1 hora` (overlap de seguridad para no perder llamadas en curso durante la última sync)
3. Si no hay llamadas → sincroniza los últimos 30 días
4. Llama a `cdrapi` con formato de fecha obligatorio `YYYY-MM-DDTHH:MM:SS` (la "T" es requerida por la API)
5. Procesa respuesta con `processCdrPackets()` (trait `ProcessesCdr`)
6. Usa `updateOrCreate` con clave `{pbx_connection_id, unique_id}` → **idempotente**

**Retorna flash message:** "Se encontraron X nuevas llamadas y Y actualizadas"

---

#### `descargarPDF(Request $request)` — Exportación PDF
**Ruta:** `GET /export-pdf`  
**Permiso:** `canExportPdf()`

**Configuración de recursos:**
- `memory_limit` → 1024M (PDFs con muchos registros)
- `max_execution_time` → 300s (5 minutos)
- Máximo **500 registros** por PDF (previene exhaustión de memoria)
- Solo llamadas con disposition `ANSWERED`

**Cálculo eficiente de costos:** Usa chunks para calcular el costo total sin cargar todos los registros en memoria simultáneamente.

**Formato:** Carta vertical, DomPDF. Nombre: `Reporte_Llamadas_DDMMYYYY_HHiiss.pdf`

---

#### `exportarExcel(Request $request)` — Exportación Excel
**Ruta:** `GET /exportar-excel`  
**Permiso:** `canExportExcel()`

Delega a `CallsExport` (Maatwebsite). Usa `FromQuery` → **streaming desde cursor DB**, sin límite práctico de registros.

---

#### `showCharts(Request $request)` — Gráficos Analíticos
**Ruta:** `GET /graficos`  
**Permiso:** `canViewCharts()`  
**Vista:** `graficos`

**Datos generados:**
- **Gráfico de torta:** Distribución por disposition (ANSWERED verde, BUSY amarillo, NO ANSWER rojo, FAILED gris)
- **Gráfico de línea:** Tendencia diaria de volumen de llamadas

---

### Métodos Privados — Lógica de Negocio Interna

#### `buildCallQuery(string $fechaInicio, string $fechaFin, ?string $anexo, ?string $tipoLlamada)`
Construye el query base para llamadas con filtros. Implementa la clasificación interna/externa con doble estrategia:

**Filtrado por tipo de llamada:**
- `internal` (salientes): Usa campo `userfield IN ('Internal', 'Outbound')` como criterio primario. Incluye fallback con regex `REGEXP '^[0-9]{3,4}$'` para registros sin `userfield` (datos migrados antes de agregar el campo).
- `external` (entrantes): Usa `userfield = 'Inbound'` + fallback regex para destinos con más de 4 dígitos.
- `all`: Sin filtro de tipo.

> **Decisión de diseño:** El doble criterio (userfield + regex fallback) se mantiene por retrocompatibilidad con CDRs sincronizados antes de la migración `2026_01_30_000001` que agregó el campo `userfield`.

---

#### `validateSort(string $sort): string`
Whitelist de campos permitidos: `start_time`, `billsec`, `tipo`, `costo`. Retorna `start_time` si el campo no es válido. **Previene SQL injection en ORDER BY.**

---

#### `applySorting($query, string $sortBy, string $sortDir)`
Para campos calculados (`tipo`, `costo`) usa `orderByRaw` con SQL CASE:
- `tipo` → ordena por prioridad: Interna(1) < Local(2) < Celular(3) < Nacional(4) < Internacional(5)
- `costo` → replica la fórmula de costo completa en SQL

---

#### `processCdrPackets(array $packets): array`
Procesa la respuesta cruda de `cdrapi`: extrae segmentos recursivamente (los CDR de Grandstream tienen estructura jerárquica con `main_cdr` y `sub_cdr`), consolida múltiples segmentos en un registro y ejecuta `updateOrCreate`. Retorna `['nuevas' => N, 'actualizadas' => N]`.

---

## 2. `ExtensionController` (app/Http/Controllers/ExtensionController.php)

**Propósito:** Gestiona extensiones con edición bidireccional: los cambios se aplican simultáneamente en la **central Grandstream** (vía API) y en la **BD local**. Implementa también la configuración de desvíos de llamadas (Call Forwarding), una funcionalidad crítica de telefonía.

**Trait usado:** `GrandstreamTrait`

### Métodos Públicos

#### `syncExtensions()` — Sincronización de Extensiones (AJAX)
**Ruta:** `POST /extension/sync`  
**Permiso:** `canSyncExtensions()`  
**Respuesta:** JSON

Sincroniza todas las extensiones desde la Central Telefónica PBX via petición AJAX. El proceso se ejecuta directamente en la petición HTTP con timeout extendido (`set_time_limit(600)`).

**Proceso:**
1. Verifica permiso `canSyncExtensions()` y que haya una central PBX seleccionada en sesión
2. Verifica si ya hay una sincronización en curso (via Cache `extension_sync_{pbxId}`)
3. Verifica conexión con `testConnection()`
4. Llama a `listUser` para obtener lista completa de usuarios
5. Para cada usuario, llama a `getSIPAccount` para obtener detalles SIP
6. Actualiza progreso en Cache en cada extensión procesada (para polling del frontend)
7. Usa `updateOrCreate` para guardar/actualizar cada extensión en BD local
8. Retorna JSON con resumen: total procesados, nuevos y actualizados

> El patrón es el mismo usado por `PbxConnectionController::syncExtensions()` — AJAX directo con timeout extendido, sin procesos en background ni colas.

**Indicadores visuales durante la sincronización:**
- **Sidebar:** El enlace "Anexos" muestra un ícono de spinner y un mensaje amarillo "Sincronizando anexos, espere..."
- **Página de Anexos:** Banner amarillo con progreso animado, botón "Sincronizar Ahora" deshabilitado
- **Al completar:** Banner verde con enlace para recargar la página y ver los cambios

**Multi-usuario:** Si otro usuario ya inició una sincronización, se retorna un error 409 (Conflict). Los indicadores del sidebar se muestran a todos los usuarios gracias al polling global.

---

#### `checkSyncStatus()` — Estado de Sincronización (AJAX)
**Ruta:** `GET /extension/sync-status`  
**Respuesta:** JSON

Endpoint de polling usado por el sidebar y la página de configuración para consultar el estado de la sincronización de extensiones.

| Campo | Tipo | Valores posibles |
|---|---|---|
| `status` | string | `idle`, `syncing`, `completed`, `error` |
| `message` | string | Mensaje descriptivo del estado actual |

**Helper:** `parsePermissionFromApi()` — Convierte formato API (`internal-local-national-international`) al formato BD (`International`).

---

#### `index(Request $request)` — Listado de Extensiones
**Ruta:** `GET /configuracion`  
**Vista:** `configuracion`  
**Permiso:** `canViewExtensions()`

Lista todas las extensiones paginadas (50/página) con filtro por número de extensión. La vista incluye un componente Alpine.js complejo (`extensionEditor()`) con modal multi-paso.

---

#### `update(Request $request)` — Actualización Bidireccional
**Ruta:** `POST /extension/update`  
**Permiso:** `canEditExtensions()`

**Proceso completo en 6 fases:**

```
Fase 1          Fase 2          Fase 3          Fase 4          Fase 5          Fase 6
VALIDAR    →    CONECTAR    →   OBTENER ID  →   PREPARAR    →   ENVIAR A    →   VERIFICAR
Request         testConnection   getUser         Mapear datos    API PBX         Y GUARDAR
                                 (user_id)       PBX format      2 peticiones    BD local
```

| Fase | Descripción | Endpoint API | Error handling |
|---|---|---|---|
| 1. Validación | Valida: extension, first_name, last_name, email, phone, permission, max_contacts, secret | — | ValidationException |
| 2. Conexión | Verifica conectividad con la central | `testConnection()` | Redirect con error |
| 3. Obtener ID | Consulta `getUser` para obtener el `user_id` interno de la central (distinto al ID de BD) | `getUser` | Redirect con error si no existe |
| 4. Preparar | Traduce permisos locales a formato API + convierte DND boolean → `yes`/`no` | — | — |
| 5. Enviar | **Dos peticiones separadas** a la API: `updateUser` (datos identity) + `updateSIPAccount` (config SIP) + `applyChanges` para persistir en la PBX | `updateUser`, `updateSIPAccount`, `applyChanges` | Redirect parcial si solo una falla |
| 6. Guardar | Si ambas API calls exitosas → actualiza modelo Extension en BD local | — | — |

**Mapeo de permisos (local → API):**

| Formato Local | Formato API Grandstream |
|---|---|
| `Internal` | `internal` |
| `Local` | `internal-local` |
| `National` | `internal-local-national` |
| `International` | `internal-local-national-international` |

---

#### `updateName(Request $request)` — Edición de Nombre Local
Solo actualiza el campo `fullname` (alias) en la BD local, **sin tocar la central PBX**. Usado desde el dashboard para etiquetar extensiones rápidamente.

---

#### `updateIps()` — Actualización Masiva de IPs
**Ruta:** `POST /extension/update-ips`  
**Permiso:** `canUpdateIps()`

Llama a `listAccount` con opciones `extension,addr`. Itera todas las cuentas y actualiza el campo `ip` en BD local. Si `addr` es `-` o vacío → guarda `null` (dispositivo offline).

---

#### `getCallForwarding(Request $request)` — Consulta de Desvíos
**Ruta:** `GET /extension/forwarding`  
**Permiso:** `canEditExtensions()`  
**Respuesta:** JSON

Consulta la configuración actual de call forwarding de una extensión:

| Dato | Descripción |
|---|---|
| `timetype` | Perfil horario activo: 0=Siempre, 1=Oficina, 2=Fuera oficina, 3=Feriados, 4=Fines semana |
| `presence_status` | Estado de presencia actual del agente: `available`, `away`, `chat`, `dnd`, etc. |
| `cfu` | Call Forward Unconditional: `{dest_type, destination}` |
| `cfb` | Call Forward Busy: `{dest_type, destination}` |
| `cfn` | Call Forward No Answer: `{dest_type, destination}` |
| `queues` | Lista de colas disponibles para usar como destino de desvío |

---

#### `updateCallForwarding(Request $request)` — Actualización de Desvíos
**Ruta:** `POST /extension/forwarding`  
**Permiso:** `canEditExtensions()`

> **DETALLE CRÍTICO DE IMPLEMENTACIÓN:** Cada desvío (CFU, CFB, CFN) se envía por separado a la API con un `applyChanges` posterior y una **pausa de 500ms** entre cada uno. Esto es obligatorio porque la PBX Grandstream auto-detecta el tipo de destino (extensión vs cola vs número externo) y enviar todos juntos confunde el mecanismo de detección.

**Flujo por cada tipo de desvío:**
1. Envía `updateSIPAccount` con el desvío específico (ej: `cfb_destination=6500`)
2. Llama a `applyChanges` para que la PBX procese y detecte el tipo
3. Espera 500ms
4. Repite para el siguiente desvío

---

## 3. `PbxConnectionController` (app/Http/Controllers/PbxConnectionController.php)

**Propósito:** Gestión completa del ciclo de vida de centrales PBX: CRUD, selección con control de acceso multi-tenant, y proceso de sincronización inicial paso a paso. Es el controlador más complejo en términos de seguridad y control de acceso.

**Trait usado:** `GrandstreamTrait`

### Métodos Públicos

#### `index(): View` — Selector de Centrales con Control de Acceso
**Ruta:** `GET /pbx`

**Filtrado por rol — regla de negocio clave:**
- **Admin:** Ve **todas** las centrales del sistema, independientemente de su estado
- **No admin:** Solo ve centrales con estado `ready` que tenga asignadas en la tabla pivot `pbx_connection_user`. Nunca ve centrales en `pending`, `syncing` o `error`

> Esta diferenciación garantiza que usuarios regulares nunca vean centrales en estado no operativo, mientras los admin pueden administrar el ciclo completo.

---

#### `store(Request $request): RedirectResponse` — Crear Central
**Ruta:** `POST /pbx`  
**Middleware:** `admin`

Crea nueva central con estado `pending` y redirige al asistente de setup. La contraseña se encripta automáticamente por el cast `encrypted` del modelo.

---

#### `update(Request $request, PbxConnection $pbx): RedirectResponse` — Editar Central
**Ruta:** `PUT /pbx/{pbx}`  
**Middleware:** `admin`

Si no se envía password en el formulario → no se modifica la contraseña existente (patrón common de "dejar vacío para mantener").

---

#### `destroy(PbxConnection $pbx): RedirectResponse` — Eliminar Central
**Ruta:** `DELETE /pbx/{pbx}`  
**Middleware:** `admin`

**Eliminación en cascada manual:**
1. Elimina todas las `calls` de la central usando `Call::withoutGlobalScope('current_pbx')` → necesario porque los Global Scopes filtrarían por la central activa, no por la que se elimina
2. Elimina todas las `extensions` de la misma forma
3. Elimina la central → la tabla pivot `pbx_connection_user` se limpia por `CASCADE DELETE` en FK

> **¿Por qué no usar `onDelete('cascade')` en las FK de calls/extensions?** Porque las FK son nullable (para migración gradual) y los Global Scopes interferirían con la eliminación automática.

---

#### `select(PbxConnection $pbx): RedirectResponse` — Selección con Autorización
**Ruta:** `GET /pbx/select/{pbx}`

**Flujo de autorización multi-tenant:**

```
¿Es admin? ──Sí──► Acceso permitido
     │
    No
     │
     ▼
¿Tiene central    ──No──► 403 Forbidden
 asignada en           ("No tienes acceso a esta central")
 tabla pivot?
     │
    Sí
     │
     ▼
¿Estado ready? ──No──► Redirect con error
     │                 ("La central no está lista")
    Sí
     │
     ▼
Guardar en sesión → Redirect a Dashboard
```

**Lógica de redirección según estado:**
| Estado | Admin | No-Admin |
|---|---|---|
| `ready` | → Dashboard | → Dashboard |
| `syncing` | → Setup (ver progreso) | → Error |
| `pending` | → Setup (configurar) | → Error |
| `error` | → Setup (reintentar) | → Error |

---

#### `setup(PbxConnection $pbx): View` — Asistente de Sincronización
**Ruta:** `GET /pbx/setup/{pbx}`  
**Middleware:** `admin`  
**Vista:** `pbx.setup`

Muestra la página de sincronización inicial con conteo actual de extensiones y llamadas. El frontend implementa un componente Alpine.js (`syncManager()`) que orquesta el proceso paso a paso.

---

#### `syncExtensions(PbxConnection $pbx): JsonResponse` — Sync de Extensiones
**Ruta:** `POST /pbx/sync-extensions/{pbx}`  
**Middleware:** `admin`

**Proceso detallado:**
1. Marca central como `syncing`
2. Llama a `listUser` → obtiene lista de todas las extensiones
3. Para cada usuario:
   - Obtiene detalles SIP con `getSIPAccount` (permisos, DND, max_contacts)
   - Parsea permisos API → formato local con `parseExtensionPermission()`
   - Ejecuta `updateOrCreate` por `{extension, pbx_connection_id}`
4. Si error en cualquier paso → marca como `error` con `sync_message`

---

#### `syncCalls(Request $request, PbxConnection $pbx): JsonResponse` — Sync de Llamadas
**Ruta:** `POST /pbx/sync-calls/{pbx}`  
**Middleware:** `admin`

Sincroniza llamadas de un **mes específico** (recibe `year` y `month`). El frontend itera los 12 meses secuencialmente, llamando a este endpoint una vez por mes.

**Proceso por mes:**
1. Calcula rango del mes completo (1ro al último día)
2. Llama a `cdrapi` con formato fecha `YYYY-MM-DDTHH:MM:SS`
3. Procesa CDRs: `collectCdrSegments()` → `consolidateCallData()` → `updateOrCreate`

---

#### `finishSync(PbxConnection $pbx): JsonResponse` — Finalizar Sincronización
**Ruta:** `POST /pbx/finish-sync/{pbx}`  
**Middleware:** `admin`

Marca `status = ready`, registra `last_sync_at = now()`, limpia `sync_message`.

---

#### `checkSyncStatus(PbxConnection $pbx): JsonResponse` — Polling de Estado
**Ruta:** `GET /pbx/sync-status/{pbx}`

Endpoint AJAX para verificar progreso de sincronización. Retorna `{status, sync_message, extension_count, call_count}`. El frontend hace polling cada 2 segundos.

---

#### `disconnect(): RedirectResponse` — Desconectar Central
**Ruta:** `POST /pbx/disconnect`

Limpia `active_pbx_id` y `active_pbx_name` de la sesión. Redirige al selector de centrales.

---

### Métodos Privados — Procesamiento de Datos

| Método | Descripción | Detalle |
|---|---|---|
| `validatePbx(Request, bool)` | Valida formulario de central | Reglas: name required, ip required+ip, port integer 1-65535, username/password required (pass no required en update) |
| `setActivePbx(PbxConnection)` | Persiste central en sesión | Guarda `active_pbx_id` y `active_pbx_name` en `session()` |
| `setPbxConnection(PbxConnection)` | Configura trait para API calls | Inyecta el modelo en `GrandstreamService` |
| `collectCdrSegments(array)` | Extrae segmentos CDR recursivamente | Recorre estructura jerárquica de `cdrapi` (main_cdr → sub_cdr) |
| `consolidateCallData(array)` | Fusiona múltiples segmentos en 1 registro | Determina entrante/saliente, suma durations, asigna disposition final |
| `esAnexo(string)` | ¿Es extensión interna? | Regex: `^\d{3,4}$` |
| `esExterno(string)` | ¿Es número externo? | >4 dígitos O empieza con `+` O empieza con `9` |
| `parseExtensionPermission(string)` | Traduce formato API → local | `*international*` → `International`, etc. |
| `determineCallType(string)` | Clasifica tipo de llamada | Usa mismos patrones regex que `Call::getCallTypeAttribute()` |

---

## 4. `StatsController` (app/Http/Controllers/StatsController.php)

**Propósito:** Dashboard de Contact Center con KPIs operativos de colas de llamadas. Proporciona visibilidad sobre el rendimiento del equipo de atención, tiempos de espera y tasas de abandono. Diseñado para supervisores y gerentes de operaciones.

### Métodos Públicos

#### `index(Request $request)` — Dashboard KPI Completo
**Ruta:** `GET /stats/kpi-turnos`  
**Permiso:** `canViewCharts()`  
**Vista:** `stats.kpi-turnos`

**Datos enviados a la vista (7 datasets):**

| Variable | Tipo | Descripción | Fuente SQL |
|---|---|---|---|
| `kpisPorHora` | array[hora → metrics] | KPIs por franja horaria (08:00-20:00) | `GROUP BY HOUR(call_time)` |
| `kpisPorCola` | array[cola → metrics] | KPIs agrupados por número de cola | `GROUP BY queue` |
| `totales` | array | Totales globales de todas las franjas | Sumatoria PHP de kpisPorHora |
| `rendimientoAgentes` | array[agente → metrics] | Métricas por agente individual | `GROUP BY agent` |
| `agentesPorCola` | array[cola → agentes[]] | Detalle de agentes por cada cola | `GROUP BY queue, agent` |
| `colasDisponibles` | array | Lista de colas para el select de filtro | DISTINCT de `calls.action_type` + `queue_call_details.queue` |
| `ultimaSincronizacion` | Carbon\|null | Fecha de última sincronización de datos | `MAX(created_at)` |

**Fórmulas KPI por franja horaria:**

| KPI | Fórmula | Descripción |
|---|---|---|
| Volumen | `COUNT(*)` | Total de intentos de llamada en la cola |
| Atendidas | `SUM(connected = 1)` | Llamadas efectivamente contestadas |
| Abandonadas | `SUM(connected = 0)` | Llamadas que no conectaron con agente |
| % Abandono | $(abandonadas / volumen) \times 100$ | Tasa de abandono — KPI crítico de servicio |
| Espera Promedio | $\frac{\sum wait\_time_{connected}}{count_{connected}}$ | Tiempo medio de espera de llamadas atendidas |
| ASA | $\frac{\sum talk\_time_{connected}}{count_{connected}}$ | Average Speed of Answer (tiempo promedio de conversación) |
| Agentes | `DISTINCT agent` | Lista de agentes únicos activos en la franja |

**Rendimiento por agente:**

| Métrica | Fórmula |
|---|---|
| Llamadas totales | `COUNT(*)` por agente |
| Llamadas atendidas | `SUM(connected = 1)` |
| Tasa de atención | $(atendidas / totales) \times 100$ |
| Tiempo total | `SUM(talk_time)` por agente |
| Tiempo promedio | `AVG(talk_time WHERE connected = 1)` |
| Espera promedio | `AVG(wait_time)` por agente |

> **Optimización:** Todas las agregaciones se realizan con `DB::raw()` y `GROUP BY` en SQL, evitando cargar registros individuales en PHP.

---

#### `apiKpis(Request $request)` — API JSON para Gráficos
**Ruta:** `GET /stats/kpi-turnos/api`  
**Permiso:** `canViewCharts()`

Mismo cálculo que `index()` pero retorna JSON puro. Útil para actualizaciones AJAX o futuros dashboards SPA.

---

#### `sincronizarColas(Request $request)` — Ejecutar Sincronización
**Ruta:** `POST /stats/kpi-turnos/sync`  
**Permiso:** `canSyncQueues()`

Ejecuta `Artisan::call('sync:queue-stats', ['--days' => $days, '--pbx' => $activePbxId])`. El parámetro `days` viene del formulario (opciones: 1, 7, 15, 30 días).

---

### Métodos Privados — Motor de Cálculo KPI

| Método | Descripción | Detalle técnico |
|---|---|---|
| `calcularKpisPorHora(fechaInicio, fechaFin, ?cola)` | Calcula todos los KPIs por franja horaria (08:00-20:00) | `HOUR(call_time)` como agrupador. Fuente: `queue_call_details`. Filtra por cola opcional |
| `obtenerRendimientoAgentes(fechaInicio, fechaFin, ?cola)` | Métricas individuales por agente | `GROUP BY agent WHERE agent != 'NONE'`. Excluye intentos sin agente |
| `calcularTotales(kpisPorHora)` | Suma global de todos los KPIs por hora | Sumatoria PHP de los arrays ya calculados (evita segunda query) |
| `calcularKpisPorCola(fechaInicio, fechaFin)` | KPIs independientes por cola | `GROUP BY queue`. Permite comparar rendimiento entre colas |
| `obtenerColasDisponibles()` | Lista de colas únicas para filtro | Combina `calls.action_type` (regex `QUEUE\[\d+\]`) + `queue_call_details.queue` |
| `obtenerAgentesPorCola(fechaInicio, fechaFin)` | Estadísticas de agentes por cola | `GROUP BY queue, agent`. Incluye efectividad por agente |
| `extraerAgente(?dstChannel, ?dstanswer, ?channel, ?source)` | Extrae extensión de agente de canales SIP | Regex `PJSIP/(\d+)-` sobre strings como `PJSIP/2000-00006d19` → `2000` |
| `extraerCola(?actionType)` | Extrae número de cola de action_type | Regex `QUEUE\[(\d+)\]` sobre strings como `QUEUE[6500]` → `6500` |

---

## 5. `UserController` (app/Http/Controllers/UserController.php)

**Propósito:** Gestión completa de usuarios con dos interfaces: CRUD tradicional (vistas Blade) + API REST JSON (para modal interactivo en la página de centrales PBX). Implementa control de acceso a centrales vía tabla pivot `pbx_connection_user`.

### Métodos de Vistas (CRUD Estándar)

| Método | Ruta | Descripción | Reglas de negocio |
|---|---|---|---|
| `index()` | `GET /usuarios` | Lista usuarios paginados (15/página) | Muestra badges de permisos y rol por usuario |
| `create()` | `GET /usuarios/crear` | Formulario de creación con selector de permisos | Alpine.js auto-activa permisos si rol=admin |
| `store(Request)` | `POST /usuarios` | Crea usuario | Si rol=admin → activa todos los `can_*` a true. Password hasheado con bcrypt |
| `edit(User)` | `GET /usuarios/{user}/editar` | Formulario de edición | **No permite editarse a sí mismo** — redirige con error |
| `update(Request, User)` | `PUT /usuarios/{user}` | Actualiza usuario | Password opcional. Si rol=admin → fuerza permisos true |
| `destroy(User)` | `DELETE /usuarios/{user}` | Elimina usuario | **Protecciones:** No permite eliminar último admin NI a sí mismo |

### Métodos API (Modal de Gestión en PBX Index)

Estos endpoints sirven al componente Alpine.js `userManager()` embebido en `pbx/index.blade.php`, permitiendo gestionar usuarios directamente desde la página de centrales.

| Método | Ruta | Response | Descripción |
|---|---|---|---|
| `apiIndex()` | `GET /api/usuarios` | JSON | Retorna: lista de usuarios con `allowed_pbx_ids` (array de IDs de centrales asignadas) + lista de `pbxConnections` disponibles (id, name, ip) |
| `apiStore(Request)` | `POST /api/usuarios` | JSON | Crea usuario. Acepta `allowed_pbx_ids[]` para sincronizar tabla pivot |
| `apiUpdate(Request, User)` | `PUT /api/usuarios/{user}` | JSON | Actualiza usuario + `sync()` de centrales permitidas en pivot |
| `apiDestroy(User)` | `DELETE /api/usuarios/{user}` | JSON | Elimina usuario. Mismas protecciones que `destroy()`. La tabla pivot se limpia por CASCADE |

**Campo `allowed_pbx_ids` en la API:**
- Tipo: `array` de integers
- Validación: `exists:pbx_connections,id`
- Se sincroniza con `$user->pbxConnections()->sync($ids)`
- Si un admin se crea sin `allowed_pbx_ids`, no importa — los admin no necesitan asignación pivot

---

## 6. `SettingController` (app/Http/Controllers/SettingController.php)

**Propósito:** Gestión de tarifas de facturación de llamadas. Interfaz simple con impacto financiero directo en los costos calculados en el dashboard CDR.

| Método | Ruta | Permiso | Descripción | Efecto |
|---|---|---|---|---|
| `index()` | `GET /tarifas` | `canViewRates()` | Muestra las 3 tarifas en cards editables | — |
| `update(Request)` | `POST /tarifas` | `canEditRates()` | Actualiza valores de tarifas con `Setting::updateOrCreate()` | Llama `Call::clearPricesCache()` para invalidar la cache estática del modelo Call |

> **Impacto:** Cambiar una tarifa afecta **inmediatamente** el cálculo de costos en todo el dashboard. Los costos no se almacenan en la BD — se calculan dinámicamente por el accessor `getCostAttribute()` del modelo `Call`.

---

## 7. `AuthController` (app/Http/Controllers/AuthController.php)

**Propósito:** Autenticación personalizada independiente de Laravel Breeze. Usa `name` (no `email`) como campo de login.

| Método | Ruta | Descripción |
|---|---|---|
| `showLogin()` | `GET /login` | Vista de login standalone (sin layout Breeze). Card minimalista centrada |
| `login(Request)` | `POST /login` | Autentica por `name` + `password`. Rate limited: `throttle:10,1` (10 intentos/minuto). Easter egg: si name=doom y password=doom → redirige a ruta `doom` (juego DOOM en navegador) |
| `logout(Request)` | `POST /logout` | Cierra sesión + invalida session + regenera CSRF token |

---

## 8. `EstadoCentral` (app/Http/Controllers/EstadoCentral.php)

**Propósito:** Muestra información de estado y uptime de la central PBX conectada.

**Trait usado:** `GrandstreamTrait`

| Método | Descripción |
|---|---|
| `index()` | Renderiza vista `welcome` con datos del sistema obtenidos de la API |
| `getSystemData(): array` | Llama a `getSystemStatus` en la API Grandstream. Retorna `['uptime' => string]` con el tiempo activo de la central |

---

## 9. `IPController` (app/Http/Controllers/IPController.php)

**Propósito:** Vista de monitoreo de IPs de extensiones en tiempo real.

| Método | Descripción |
|---|---|
| `index()` | Renderiza vista con lista de extensiones y sus IPs actuales |
| `fetchLiveAccounts(): array` | Obtiene cuentas activas desde la API `listAccount` |
| `parseAddress(string): string` | Extrae IP de la cadena de dirección retornada por la API |

---

## 10. `ProfileController` (app/Http/Controllers/ProfileController.php)

**Propósito:** Gestión del perfil del usuario autenticado. Provisto por Laravel Breeze con modificaciones mínimas.

| Método | Ruta | Descripción |
|---|---|---|
| `edit()` | `GET /profile` | Formulario de edición de perfil (nombre + email) |
| `update()` | `PATCH /profile` | Actualiza perfil. Usa `ProfileUpdateRequest` para validación |
| `destroy()` | `DELETE /profile` | Elimina cuenta propia (solicita confirmación de contraseña) |

---

## Trait Compartido: `ProcessesCdr` (app/Http/Controllers/Concerns/ProcessesCdr.php)

**Usado por:** `CdrController`  
**Propósito:** Lógica reutilizable para procesar la estructura jerárquica de CDRs que retorna la API de Grandstream.

| Método | Visibilidad | Descripción |
|---|---|---|
| `collectCdrSegments(array)` | protected | Recorrido recursivo de la estructura CDR. Los CDRs de Grandstream llegan como árboles con `main_cdr` y `sub_cdr` anidados. Este método aplana la estructura en un array lineal de segmentos |
| `consolidateCdrSegments(array)` | protected | Fusiona N segmentos en 1 registro de llamada: usa earliest `start_time`, suma `duration`/`billsec`, determina si es entrante o saliente, asigna disposition final (`ANSWERED` si algún segmento tiene `billsec > 0`) |
| `isExtension(string)` | protected | Regex `^\d{3,4}$` — ¿es extensión interna? |
| `isExternalNumber(string)` | protected | Longitud > 4 o empieza con `+` — ¿es número externo? |

**Regla de consolidación de calls entrantes:**
Cuando se detecta una llamada entrante (source externo + destination interna), el sistema **invierte** source y destination para que el registro final muestre la extensión interna como `source` y el número externo como `destination`. Esto mantiene consistencia con la perspectiva del usuario del panel.

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
