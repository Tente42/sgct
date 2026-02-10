# Comandos Artisan - Documentación Detallada

---

## 1. `SyncCalls` (app/Console/Commands/SyncCalls.php)

**Comando:** `php artisan calls:sync`  
**Propósito:** Sincroniza llamadas (CDR) desde la central Grandstream hacia la BD local.

**Trait usado:** `ConfiguresPbx`

### Firma

```
calls:sync 
    {--year=2026 : Año desde el cual sincronizar}
    {--pbx= : ID de la central PBX a usar}
```

### `handle(): int`
Método principal:
1. Aumenta memory_limit a 1024M
2. Configura central PBX con `setupPbxConnection()`
3. Verifica conexión
4. Llama a `syncCallsByMonth()` con el año especificado

---

### Métodos Privados

#### `syncCallsByMonth(int $year): void`
Itera mes por mes desde enero del año indicado hasta el mes actual. Llama a `processMonth()` para cada mes con 1 segundo de pausa entre cada uno.

---

#### `processMonth(Carbon $start, Carbon $end): void`
1. Llama a `cdrapi` con rango del mes completo
2. Procesa los paquetes CDR recibidos

---

#### `processCdrPackets(array $calls): void`
Para cada paquete CDR:
1. `collectSegments()` → extrae todos los segmentos recursivamente
2. Filtra segmentos sin disposition
3. `consolidateCall()` → consolida en un registro
4. `updateOrCreate` en la tabla `calls` usando `unique_id` + `pbx_connection_id`
5. Muestra barra de progreso

---

#### `collectSegments(array $node): array`
Recorrido recursivo de la estructura CDR. Busca nodos con campo `start` y sub-CDRs.

---

#### `consolidateCall(array $segments): array`
Consolida múltiples segmentos en un solo registro:
- Determina si es entrante (src externo + dst anexo) o saliente
- Captura: unique_id, start_time, answer_time, caller_name, userfield
- Suma: duration, billsec
- Asigna origen/destino según tipo
- Determina disposition: ANSWERED (si billsec>0), BUSY, FAILED, NO ANSWER

---

#### `esAnexo(string $num): bool`
Regex: `^\d{3,4}$`

#### `esExterno(string $num): bool`
Longitud > 4 O empieza con `+` O empieza con `9`

---

## 2. `ImportarExtensiones` (app/Console/Commands/ImportarExtensiones.php)

**Comando:** `php artisan extensions:import`  
**Propósito:** Sincroniza extensiones desde la central Grandstream.

**Trait usado:** `ConfiguresPbx`

### Firma

```
extensions:import 
    {target? : Extensión específica a sincronizar}
    {--pbx= : ID de la central PBX a usar}
    {--quick : Modo rápido sin detalles SIP}
```

### `handle(): int`
1. Configura central
2. Modo rápido o completo
3. Si hay `target`, sincroniza una sola extensión
4. Si no, sincroniza todas

---

### Métodos Privados

#### `syncAll(): int`
1. Obtiene lista de usuarios con `fetchUserList()`
2. Procesa en chunks de 50 (para gestión de memoria)
3. Muestra tabla resumen: sin cambios, actualizados, nuevos

---

#### `fetchUserList(): array`
Llama a `listUser` en la API. Maneja diferentes formatos de respuesta de la API.

---

#### `processExtension(array $userData): string`
Para cada extensión:
1. Construye datos con `buildExtensionData()`
2. Busca extensión existente en BD
3. Si existe y tiene cambios → actualiza → retorna `'actualizados'`
4. Si existe sin cambios → retorna `'sin_cambios'`
5. Si no existe → crea nueva → retorna `'nuevos'`

---

#### `buildExtensionData(array $userData): array`
Construye array de datos:
- Datos básicos: fullname, email, first_name, last_name, phone
- En modo completo (`--quick` no): llama a `getSIPAccount` para obtener:
  - `do_not_disturb` (dnd yes/no → boolean)
  - `max_contacts`
  - `secret`
  - `permission` (parseada con `parsePermission()`)
- Pausa de 10ms entre peticiones SIP (cortesía)

---

#### `parsePermission(string $raw): string`
Traduce formato API a formato local:
- `*international*` → `International`
- `*national*` → `National`
- `*local*` → `Local`
- Default → `Internal`

---

#### `hasChanges(Extension $existing, array $new): bool`
Compara campos: fullname, email, permission, do_not_disturb, max_contacts. En modo completo también compara secret.

---

#### `syncSingle(string $target): int`
Sincroniza una sola extensión:
1. Llama a `getUser` para obtener datos básicos
2. Fuerza modo completo (siempre obtiene detalles SIP)
3. `updateOrCreate` en la BD

---

## 3. `SyncQueueStats` (app/Console/Commands/SyncQueueStats.php)

**Comando:** `php artisan sync:queue-stats`  
**Propósito:** Sincroniza estadísticas de colas desde `queueapi` de Grandstream.

**Trait usado:** `ConfiguresPbx`

### Firma

```
sync:queue-stats 
    {--pbx=1 : ID de la central PBX (OBLIGATORIO)}
    {--queue= : Cola específica a sincronizar}
    {--days=7 : Días hacia atrás (default 7)}
    {--start-date= : Fecha inicio (YYYY-MM-DD)}
    {--end-date= : Fecha fin (YYYY-MM-DD)}
    {--force : Forzar resincronización (elimina datos existentes)}
```

### `handle(): int`
1. Determina fechas (end-date default: hoy, start-date default: hoy - days)
2. Busca central PBX por ID
3. Llama a `syncPbx()` para cada central
4. Muestra resumen: insertados, omitidos, errores

---

### Métodos Privados

#### `syncPbx(PbxConnection $pbx, Carbon $start, Carbon $end): void`
1. Configura sesión para la central
2. Verifica conexión
3. Obtiene lista de colas con `getQueues()`
4. Si `--force`: elimina datos existentes del período
5. Sincroniza cada cola con `syncQueue()`

---

#### `getQueues(): array`
Llama a `listQueue` en la API. Retorna array de extensiones de colas.

---

#### `syncQueue(PbxConnection $pbx, string $queue, Carbon $start, Carbon $end): void`
1. Llama a `queueapi` con `statisticsType=calldetail`
2. Extrae detalles de `queue_statistics[].agent`
3. Para cada llamada:
   - Parsea: callernum, start_time, agent, extension (cola), wait_time, talk_time, connect
   - Detecta duplicados dentro del mismo batch (por clave única compuesta)
   - Verifica si ya existe en BD (evita duplicados)
   - Inserta con `QueueCallDetail::create()`
4. Muestra: insertados, omitidos, duplicados API

---

## 4. `TestApiCommands` (app/Console/Commands/TestApiCommands.php)

**Comando:** `php artisan api:test`  
**Propósito:** Comando de testeo/exploración de la API Grandstream. Contiene múltiples subcomandos para diferentes endpoints.

**Trait usado:** `ConfiguresPbx`

### Firma (opciones principales)

```
api:test 
    {--pbx= : ID central}
    {--action= : Acción a ejecutar}
    {--caller= : Número de origen para cdrapi}
    {--uniqueid= : Unique ID específico}
    {--extension= : Extensión para getSIPAccount}
    {--queue= : Número de cola para queueapi}
    {--start-time= : Fecha inicial}
    {--end-time= : Fecha final}
    {--days=30 : Días hacia atrás}
    {--call-type=* : Filtrar por tipo}
    {--status=* : Filtrar por estado}
    {--agent= : Agente para queueapi}
    {--stats-type=overview : Tipo de estadísticas}
    {--today : Solo fecha de hoy}
    {--presence= : Estado de presencia}
    {--cfb= : Call Forward Busy}
    {--cfn= : Call Forward No Answer}
    {--cfu= : Call Forward Unconditional}
```

### Acciones Disponibles (switch en `handle()`)

| Acción | Método | Descripción |
|---|---|---|
| `listExtensionGroup` | `testListExtensionGroup()` | Lista grupos de extensiones |
| `listQueue` | `testListQueue()` | Lista colas de llamadas con agentes y estrategia |
| `listOutboundRoute` | `testListOutboundRoute()` | Lista rutas salientes |
| `listInboundRoute` | `testListInboundRoute()` | Lista rutas entrantes (obtiene trunks primero) |
| `listDepartment` | `testListDepartment()` | Lista departamentos |
| `listBridgedChannels` | `testListBridgedChannels()` | Lista canales activos |
| `getInboundRoute` | `testGetInboundRoute()` | Detalle de ruta entrante por ID |
| `getOutboundRoute` | `testGetOutboundRoute()` | Detalle de ruta saliente por ID |
| `getSIPAccount` | `testGetSIPAccount()` | Detalle completo de una extensión SIP |
| `updateSIPAccount` | `testUpdateSIPAccount()` | Actualiza cuenta SIP (modo interactivo) |
| `queueapi` | `testQueueApi()` | Consulta estadísticas de colas |
| `cdrapi` | `testCdrApi()` | Busca CDRs con filtros avanzados |
| `cdr` | `interactiveCdrApi()` | Cuestionario interactivo CDR |
| `kpi-turnos` | `testKpiTurnos()` | KPIs de turnos por hora |
| `explore-action-types` | `exploreActionTypes()` | Explora los action_type únicos |
| `analyze-billing` | `analyzeBilling()` | Analiza qué llamadas son cobrables |

**NOTA:** Este archivo tiene ~4220 líneas y contiene implementaciones extensas de cada subcomando con formateo de tablas, filtros avanzados y herramientas de debugging.

---

## 5. `ConfiguresPbx` Trait (app/Console/Commands/Concerns/ConfiguresPbx.php)

**Propósito:** Trait compartido por todos los comandos para configurar la central PBX.

**Usa:** `GrandstreamTrait`

### Métodos

#### `setupPbxConnection(): ?int`
1. Si se pasó `--pbx=ID` → configura esa central
2. Si no → verifica si hay configuración activa
3. Si no hay nada → muestra tabla con centrales disponibles

---

#### `showAvailablePbxConnections(): void`
Muestra tabla con todas las centrales (ID, Nombre, IP) y ejemplo de uso.

---

#### `verifyConnection(): bool`
Llama a `testConnection()` y muestra error si falla.

---

## 6. `SyncPbxDataJob` (app/Jobs/SyncPbxDataJob.php)

**Propósito:** Job para sincronización en background usando colas de Laravel.

**Implementa:** `ShouldQueue`

### Propiedades

| Propiedad | Valor | Descripción |
|---|---|---|
| `$timeout` | 3600 | 1 hora máximo |
| `$tries` | 1 | Solo un intento |
| `$pbxId` | int | ID de la central |
| `$syncExtensions` | bool | ¿Sincronizar extensiones? |
| `$syncCalls` | bool | ¿Sincronizar llamadas? |
| `$year` | int | Año desde el cual sincronizar |
| `$userName` | string | Usuario que inició la sincronización |

### `handle(): void`
1. Establece lock en Cache
2. Si `syncExtensions` → ejecuta `extensions:import --quick`
3. Si `syncCalls` → ejecuta `calls:sync`
4. Actualiza progreso en Cache
5. Limpia locks en finally

### `failed(?Throwable $exception): void`
Maneja errores: guarda mensaje de error en Cache, limpia locks.

---

## 7. `CallsExport` (app/Exports/CallsExport.php)

**Propósito:** Exportación de llamadas a Excel usando Maatwebsite.

**Implementa:** `FromQuery`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`, `WithStyles`

### Métodos

#### `query()`
Construye query con filtros:
1. Fecha inicio/fin
2. Anexo (comparación exacta por `source`)
3. Tipo de llamada (internal/external) con misma lógica que CdrController

---

#### `headings(): array`
Columnas: Fecha y Hora, Origen, Destino, Tipo, Duración (seg), Costo ($), Estado

---

#### `map($call): array`
Mapea cada llamada a fila: start_time, source, destination, call_type (accessor), billsec, cost (accessor), disposition

---

#### `styles(Worksheet $sheet)`
Primera fila en negrita.
