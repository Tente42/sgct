# Comandos Artisan, Jobs y Exports — Documentación Detallada

> Documenta los procesos batch de sincronización con la PBX Grandstream, la ejecución asíncrona en background y la exportación de datos a Excel. Todos los comandos comparten el trait `ConfiguresPbx` para resolución de central.

---

## 1. `SyncCalls` (app/Console/Commands/SyncCalls.php)

**Comando:** `php artisan calls:sync`  
**Propósito:** Sincroniza registros CDR (Call Detail Records) desde la central Grandstream hacia las tablas locales. Descarga mes por mes para controlar memoria y timeout de API.

**Trait usado:** `ConfiguresPbx`

### Firma

```
calls:sync 
    {--year=2026 : Año desde el cual sincronizar}
    {--pbx= : ID de la central PBX a usar}
```

### `handle(): int`

```
1. ini_set('memory_limit', '1024M')
2. setupPbxConnection() ──Falla──► return 1
3. verifyConnection()   ──Falla──► return 1
4. syncCallsByMonth(year)
5. Mensaje de éxito → return 0
```

### Métodos Privados

#### `syncCallsByMonth(int $year): void`

Itera desde enero del año indicado hasta el momento actual, procesando un mes completo por iteración:

```
$startDate = Carbon::createFromDate($year, 1, 1)->startOfDay()
$now = Carbon::now()

while ($startDate <= $now):
    $start = startOfMonth()
    $end   = endOfMonth()->min($now)  ← trunca al presente si el mes no ha terminado
    processMonth($start, $end)
    $startDate->addMonth()
    sleep(1)  ← cortesía para no saturar la PBX
```

> **Estrategia sin overlap:** Cada iteración recalcula `startOfMonth()`, así que al avanzar con `addMonth()` no se producen duplicados temporales. El último mes se trunca al instante actual con `->min($now)`.

---

#### `processMonth(Carbon $start, Carbon $end): void`

Solicita CDRs a la API con un **timeout de 120 segundos** (meses con alto volumen):

```php
$this->connectApi('cdrapi', [
    'format'    => 'json',
    'startTime' => $start->format('Y-m-d\TH:i:s'),
    'endTime'   => $end->format('Y-m-d\TH:i:s'),
    'minDur'    => 0
], 120);
```

Lee `$data['cdr_root']` (array de paquetes CDR). Si tiene registros → `processCdrPackets()`.

> **La "T" literal** en el formato de fecha es obligatoria para la API Grandstream. Sin ella, retorna vacío sin error.

---

#### `processCdrPackets(array $calls): void`

Procesa cada paquete CDR con barra de progreso:

```
Por cada $cdrPacket:
    1. collectSegments($cdrPacket)     ← extracción recursiva
    2. Filtrar segmentos sin disposition
    3. consolidateCall($segments)      ← fusión en un registro
    4. Verificar existencia: Call::withoutGlobalScope('current_pbx')
         ->where('unique_id', ...)
         ->where('pbx_connection_id', $this->pbxId)
    5. updateOrCreate(['unique_id', 'pbx_connection_id'], $consolidated)
```

> **`withoutGlobalScope('current_pbx')`**: Necesario porque el Global Scope filtra por la PBX de sesión, pero aquí se opera sobre una PBX específica que puede no coincidir con la sesión.

---

#### `collectSegments(array $node): array`

Recorrido **recursivo** del árbol CDR de Grandstream. La PBX estructura las llamadas en paquetes jerárquicos:

```
cdr_packet
├── main_cdr         ← segmento principal
│   ├── start, src, dst, duration...
│   └── sub_cdr_1    ← sub-llamada (transferencia, cola)
│       └── sub_cdr_2
└── sub_cdr_3        ← otro segmento
```

**Lógica:**
- Si el nodo tiene campo `start` no vacío → es un segmento válido, se agrega
- Para cada clave del nodo: si es array y el key empieza con `sub_cdr` o equals `main_cdr` → recurrir

---

#### `consolidateCall(array $segments): array`

Fusiona múltiples segmentos de una llamada en un solo registro para la BD. Esta es la lógica más compleja del comando.

**Inicialización de campos:**

| Campo | Default | Estrategia de merge |
|---|---|---|
| `unique_id` | null | Primer `acctid` ?? `uniqueid` encontrado; fallback: `md5(start_time + source + destination)` |
| `start_time` | null | El **más temprano** entre todos los segmentos (comparación string) |
| `answer_time` | null | Primer valor no-null que no sea `'0000-00-00 00:00:00'` |
| `source` | null | Depende del tipo de llamada (ver abajo) |
| `destination` | null | Depende del tipo de llamada (ver abajo) |
| `duration` | 0 | **Suma** acumulada de todos los segmentos |
| `billsec` | 0 | **Suma** acumulada de todos los segmentos |
| `disposition` | 'NO ANSWER' | 'ANSWERED' si cualquier `billsec > 0`; sino BUSY > FAILED |
| `caller_name` | null | Primer no-null (`??=`) |
| `recording_file` | null | De `$seg['recordfiles']`, primer no-null |
| `action_type`, `lastapp`, `channel`, `dst_channel`, `src_trunk_name`, `userfield` | null | Primer no-null (`??=`) |

**Determinación entrante/saliente:**

```php
$firstSrc = $segments[0]['src'] ?? '';
$firstDst = $segments[0]['dst'] ?? '';
$esEntrante = $this->esExterno($firstSrc) && $this->esAnexo($firstDst);
```

**Asignación de source/destination según tipo:**

| Tipo | `source` → | `destination` → |
|---|---|---|
| Entrante | Primer `$dst` que `esAnexo()` | Primer `$src` que `esExterno()` |
| Saliente | Primer `$src` que `esAnexo()` | Primer `$dst` |

**Determinación de disposition (post-loop, si no es ANSWERED):**
1. Escanea todos los segmentos
2. Si alguno contiene `'BUSY'` → `'BUSY'` (break inmediato)
3. Si alguno contiene `'FAILED'` → `'FAILED'` (puede ser sobreescrito por BUSY)
4. Default: `'NO ANSWER'`

---

#### Helpers de clasificación

| Método | Regex/Lógica | Ejemplos |
|---|---|---|
| `esAnexo(string $num): bool` | `/^\d{3,4}$/` | `1001` ✓, `4444` ✓, `56912345678` ✗ |
| `esExterno(string $num): bool` | `strlen > 4 \|\| str_starts_with('+') \|\| str_starts_with('9')` | `912345678` ✓, `+56...` ✓, `1001` ✗ |

---

## 2. `ImportarExtensiones` (app/Console/Commands/ImportarExtensiones.php)

**Comando:** `php artisan extensions:import`  
**Propósito:** Sincroniza extensiones (anexos) desde la central Grandstream hacia la BD local. Soporta dos modos: rápido (solo datos básicos) y completo (incluye configuración SIP detallada).

**Trait usado:** `ConfiguresPbx`

### Firma

```
extensions:import 
    {target? : Extensión específica a sincronizar (ej: 1001)}
    {--pbx= : ID de la central PBX a usar}
    {--quick : Modo rápido sin detalles SIP}
```

### `handle(): int`

```
1. ini_set('memory_limit', '1028M')  ← nota: 1028, no 1024
2. setupPbxConnection() → $this->pbxId
3. $this->quickMode = $this->option('quick')
4. Muestra modo: "RÁPIDO" o "COMPLETO"
5. verifyConnection()
6. Si target → syncSingle($target)
7. Si no → syncAll()
```

### Modos de operación

| Aspecto | Modo Rápido (`--quick`) | Modo Completo (default) |
|---|---|---|
| API calls por extensión | 0 adicionales | 1 (`getSIPAccount`) + 10ms pause |
| Campos sincronizados | fullname, email, first/last name, phone | + dnd, max_contacts, secret, permission |
| Uso de memoria | Bajo | Alto (requiere gc_collect_cycles) |
| Velocidad | ~50 ext/seg | ~5 ext/seg (limitado por API) |

### Métodos Privados

#### `syncAll(): int`

```
1. fetchUserList() → array de usuarios
2. Inicializar stats: {sin_cambios: 0, actualizados: 0, nuevos: 0}
3. array_chunk($users, 50)
   │
   Por cada chunk de 50:
   ├── Por cada usuario: processExtension() → incrementa stat
   ├── Mostrar progreso
   └── gc_collect_cycles()  ← libera memoria de objetos Eloquent
4. Mostrar tabla resumen
```

> **`gc_collect_cycles()`**: Necesario porque PHP no recolecta automáticamente objetos con referencias circulares (común en modelos Eloquent con relaciones). Sin esto, la memoria crece linealmente con centrales de 200+ extensiones.

---

#### `fetchUserList(): array`

```php
$response = $this->connectApi('listUser', [], 60); // timeout 60s
```

Lee `$response['response']['user']`. **Fallback para formatos API inconsistentes**: si `user` es vacío, itera los elementos de `$responseBlock` buscando cualquier array cuyo primer item tenga un campo `user_name`.

> **Nota:** La API Grandstream UCM a veces cambia la estructura de respuesta entre versiones de firmware, de ahí el fallback robusto.

---

#### `processExtension(array $userData): string`

```
1. $extension = $userData['user_name'] → null? → 'sin_cambios'
2. buildExtensionData($userData) → $data
3. Buscar existente: Extension::withoutGlobalScope('current_pbx')
     ->where('extension', $ext)->where('pbx_connection_id', $this->pbxId)
4. ¿Existe + hasChanges()? → update() → 'actualizados'
5. ¿Existe sin cambios?   → 'sin_cambios'
6. ¿No existe?            → create() → 'nuevos'
```

---

#### `buildExtensionData(array $userData): array`

**Campos base (siempre se extraen):**

| Campo BD | Fuente API | Default |
|---|---|---|
| `fullname` | `$userData['fullname']` | `$extension` |
| `email` | `$userData['email']` | null |
| `first_name` | `$userData['first_name']` | null |
| `last_name` | `$userData['last_name']` | null |
| `phone` | `$userData['phone_number']` | null |
| `do_not_disturb` | — | `false` |
| `permission` | — | `'Internal'` |
| `max_contacts` | — | `1` |

**Campos adicionales en modo completo** (cuando `!$this->quickMode`):

```php
$sipData = $this->connectApi('getSIPAccount', ['extension' => $extension], 10);
usleep(10000); // 10ms de cortesía entre peticiones SIP
```

La respuesta SIP tiene múltiples formatos posibles (varía por firmware UCM):
```php
$details = $sipData['response']['extension']
        ?? $sipData['response']['sip_account'][0]
        ?? $sipData['response']['sip_account']
        ?? [];
```

| Campo BD | Fuente SIP | Transformación |
|---|---|---|
| `do_not_disturb` | `$details['dnd']` | `=== 'yes'` → boolean |
| `max_contacts` | `$details['max_contacts']` | `(int)`, default 1 |
| `secret` | `$details['secret']` | String directo |
| `permission` | `$details['permission']` | Parseada (ver abajo) |

---

#### `parsePermission(string $raw): string`

Traduce el formato del ACL del UCM al formato local:

| Valor API (contiene) | Resultado BD | Acceso |
|---|---|---|
| `'international'` | `'International'` | Todo destino |
| `'national'` | `'National'` | Nacional + local + interno |
| `'local'` | `'Local'` | Local + interno |
| *(cualquier otro)* | `'Internal'` | Solo entre extensiones |

> Se usa `str_contains()` (case-insensitive implícito por los datos de la API) porque los valores del UCM pueden venir como `*international*`, `internal`, `national_calls`, etc.

---

#### `hasChanges(Extension $existing, array $new): bool`

Compara campos con operador `!=` (loose):

| Campos siempre comparados | Campos solo en modo completo |
|---|---|
| `fullname`, `email`, `permission`, `do_not_disturb`, `max_contacts` | `secret` (si `isset($new['secret'])`) |

---

#### `syncSingle(string $target): int`

Diferencias clave respecto a `syncAll()`:

| Aspecto | `syncAll()` | `syncSingle()` |
|---|---|---|
| API endpoint | `listUser` (todas) | `getUser` (una) |
| Modo | Respeta `--quick` | **Fuerza modo completo** |
| Método de guardado | `hasChanges` → `update()`/`create()` | `updateOrCreate()` directo |
| Memoria | Chunks + gc_collect_cycles | No necesita |
| Formato respuesta | `$response['response']['user'][]` | `$response['response']['user_name']` ?? `$response['response'][$target]` ?? `$response['response']` |

---

## 3. `SyncQueueStats` (app/Console/Commands/SyncQueueStats.php)

**Comando:** `php artisan sync:queue-stats`  
**Propósito:** Sincroniza estadísticas de colas de atención desde el endpoint `queueapi` de Grandstream. Almacena el detalle por llamada (caller, agente, tiempos de espera/conversación).

**Trait usado:** `ConfiguresPbx`

### Firma

```
sync:queue-stats 
    {--pbx=1 : ID de la central PBX (OBLIGATORIO, default: 1)}
    {--queue= : Cola específica a sincronizar (ej: 6500)}
    {--days=7 : Días hacia atrás (default 7)}
    {--start-date= : Fecha inicio (YYYY-MM-DD, sobreescribe --days)}
    {--end-date= : Fecha fin (YYYY-MM-DD, default: hoy)}
    {--force : Forzar resincronización (elimina datos existentes del período)}
```

### `handle(): int`

**Cálculo de rango de fechas:**
```
$endDate   = --end-date (parseada)  ?: Carbon::now()
$startDate = --start-date (parseada) ?: $endDate->subDays(--days)
```

**Flujo:**
```
1. Calcular fechas
2. $pbxId = (int)option('pbx') ──0?──► error, return 1
3. PbxConnection::where('id', $pbxId)->get() ──vacío?──► error, return 1
4. syncPbx($pbx, $startDate, $endDate) por cada conexión
5. Resumen: $totalInserted, $totalSkipped, $totalErrors
```

### Métodos Privados

#### `syncPbx(PbxConnection $pbx, Carbon $start, Carbon $end): void`

```
1. session(['active_pbx_id' => $pbx->id])  ← necesario para Global Scopes
2. verifyConnection() ──Falla──► $totalErrors++, return
3. getQueues() → lista de extensiones de colas
4. Filtrar por --queue si se especificó
5. ¿--force?
   │
   ├── Sí: DELETE FROM queue_call_details
   │       WHERE pbx_connection_id = $pbx->id
   │       AND queue IN ($queues)
   │       AND call_time BETWEEN $start 00:00:00 AND $end 23:59:59
   │       (usa withoutGlobalScopes)
   │
   └── No: Los duplicados se manejan en syncQueue()
   
6. syncQueue() por cada cola
```

---

#### `getQueues(): array`

```php
$response = $this->connectApi('listQueue', [
    'options' => 'extension',
    'sidx'    => 'extension',
    'sord'    => 'asc'
]);
return array_column($response['response']['queue'], 'extension');
// Resultado: ['6500', '6501', '6502', ...]
```

---

#### `syncQueue(PbxConnection $pbx, string $queue, Carbon $start, Carbon $end): void`

**Request a la API (timeout 120s):**
```php
$this->connectApi('queueapi', [
    'format'         => 'json',
    'queue'          => $queue,     // ej: '6500'
    'agent'          => '*',        // todos los agentes
    'startTime'      => $start->format('Y-m-d'),
    'endTime'        => $end->format('Y-m-d'),
    'statisticsType' => 'calldetail'
], 120);
```

**Estructura de respuesta de la API (ver ESTRUCTURA_QUEUEAPI.md):**
```json
{
  "queue_statistics": [
    {
      "agent": {
        "callernum": "912345678",
        "start_time": "2026-02-10 09:15:32",
        "agent": "1001",
        "extension": "6500",
        "wait_time": "15",
        "talk_time": "180",
        "connect": "yes"
      }
    }
  ]
}
```

> **Nota:** Cada `queue_statistics[]` contiene un **solo** registro de llamada bajo la key `agent` (no un array de agentes). El nombre `agent` es confuso pero es la estructura real del UCM.

**Deduplicación en 3 capas:**

```
Capa 1 — Batch (en memoria):
    $seenInBatch = []
    $uniqueKey = "{$queue}|{$caller}|{$agent}|{$callTime->format('Y-m-d H:i:s')}"
    Si key ya existe → skip ($apiDuplicates++)

Capa 2 — Base de datos:
    QueueCallDetail::withoutGlobalScopes()
        ->where(pbx_connection_id, queue, caller, agent, call_time)
        ->exists()
    Si existe → skip ($skipped++)

Capa 3 — Constraint violation (catch):
    En caso de race condition o error inesperado
    El catch en create() incrementa $skipped
```

**Campos insertados en `QueueCallDetail`:**

| Campo BD | Fuente API | Transformación |
|---|---|---|
| `pbx_connection_id` | — | Del modelo PBX |
| `queue` | `$call['extension']` | Número de cola |
| `caller` | `$call['callernum']` | Default: `'unknown'` |
| `agent` | `$call['agent']` | Default: `'NONE'` |
| `call_time` | `$call['start_time']` | Parseado con Carbon |
| `wait_time` | `$call['wait_time']` | `(int)`, default 0 |
| `talk_time` | `$call['talk_time']` | `(int)`, default 0 |
| `connected` | `$call['connect']` | `=== 'yes'` → boolean |

---

## 4. `TestApiCommands` (app/Console/Commands/TestApiCommands.php)

**Comando:** `php artisan api:test`  
**Propósito:** Herramienta de exploración y debugging de la API Grandstream. Contiene **~4220 líneas** con 16+ subcomandos para inspeccionar todos los endpoints de la PBX. No es un comando de producción — es una navaja suiza para desarrollo.

**Trait usado:** `ConfiguresPbx`

### Firma (opciones principales)

```
api:test 
    {--pbx= : ID central}
    {--action= : Acción a ejecutar (ver tabla)}
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
    {--stats-type=overview : Tipo de estadísticas (overview|calldetail)}
    {--today : Solo fecha de hoy}
    {--presence= : Estado de presencia}
    {--cfb= : Call Forward Busy}
    {--cfn= : Call Forward No Answer}
    {--cfu= : Call Forward Unconditional}
```

### Acciones Disponibles

| Acción | Método | Endpoint API | Descripción |
|---|---|---|---|
| `listExtensionGroup` | `testListExtensionGroup()` | listExtensionGroup | Lista grupos de extensiones |
| `listQueue` | `testListQueue()` | listQueue | Lista colas con agentes y estrategia |
| `listOutboundRoute` | `testListOutboundRoute()` | listOutboundRoute | Lista rutas salientes |
| `listInboundRoute` | `testListInboundRoute()` | listInboundRoute | Lista rutas entrantes (obtiene trunks primero) |
| `listDepartment` | `testListDepartment()` | listDepartment | Lista departamentos |
| `listBridgedChannels` | `testListBridgedChannels()` | listBridgedChannels | Lista canales activos (llamadas en curso) |
| `getInboundRoute` | `testGetInboundRoute()` | getInboundRoute | Detalle de ruta entrante por ID |
| `getOutboundRoute` | `testGetOutboundRoute()` | getOutboundRoute | Detalle de ruta saliente por ID |
| `getSIPAccount` | `testGetSIPAccount()` | getSIPAccount | Detalle completo de extensión SIP |
| `updateSIPAccount` | `testUpdateSIPAccount()` | updateSIPAccount | Actualiza SIP (modo interactivo) |
| `queueapi` | `testQueueApi()` | queueapi | Consulta estadísticas de colas |
| `cdrapi` | `testCdrApi()` | cdrapi | Busca CDRs con filtros avanzados |
| `cdr` | `interactiveCdrApi()` | cdrapi | Cuestionario interactivo CDR |
| `kpi-turnos` | `testKpiTurnos()` | cdrapi | KPIs de turnos por hora |
| `explore-action-types` | `exploreActionTypes()` | cdrapi | Explora action_type únicos |
| `analyze-billing` | `analyzeBilling()` | cdrapi | Analiza facturabilidad con `CallBillingAnalyzer` |

> **Uso típico:** `php artisan api:test --pbx=1 --action=cdrapi --days=7 --today`

---

## 5. `ConfiguresPbx` Trait (app/Console/Commands/Concerns/ConfiguresPbx.php)

**Propósito:** Trait compartido por todos los comandos de consola para resolver la central PBX. Encapsula la lógica de selección y validación de conexión.

**Usa internamente:** `GrandstreamTrait`

### `setupPbxConnection(): ?int`

```
¿Se pasó --pbx=ID?
├── Sí: configurePbx($pbxId) ──Falla──► error, return null
│       PbxConnection::find($pbxId) → mostrar nombre/IP
│       return $pbxId
│
└── No: ¿isPbxConfigured()? (del service container/sesión)
        ├── Sí: return getActivePbxId()
        └── No: showAvailablePbxConnections() → return null
```

### `showAvailablePbxConnections(): void`
Muestra tabla con todas las centrales registradas:
```
+----+------------------+--------------+
| ID | Nombre           | IP           |
+----+------------------+--------------+
| 1  | Central Santiago | 192.168.1.10 |
| 2  | Sucursal         | 10.0.0.50    |
+----+------------------+--------------+
Uso: php artisan {comando} --pbx=1
```

### `verifyConnection(): bool`
Llama a `testConnection()` → invoca `getSystemStatus` en la PBX. Muestra error descriptivo si falla.

---

## 6. `SyncPbxDataJob` (app/Jobs/SyncPbxDataJob.php)

**Propósito:** Job encolable (queue) para sincronización en background. Permite al usuario iniciar la sincronización desde la interfaz web sin bloquear la UI.

**Implementa:** `ShouldQueue`, usa `Queueable`

### Configuración del Job

| Propiedad | Valor | Razón |
|---|---|---|
| `$timeout` | 3600 (1 hora) | Centrales grandes pueden tener 100K+ CDRs |
| `$tries` | 1 | No reintentar — la sincronización es idempotente via `updateOrCreate` |

### Constructor

```php
public function __construct(
    int $pbxId,
    bool $syncExtensions,
    bool $syncCalls,
    int $year,
    string $userName = 'Sistema'
)
```

### Sistema de Lock y Progreso (Cache)

El job usa Cache de Laravel para comunicar progreso a la UI (consultado por polling AJAX desde el frontend):

| Cache Key | Propósito | TTL | Contenido |
|---|---|---|---|
| `pbx_sync_lock_{$pbxId}` | Lock de sincronización | 3600s | `['user' => $userName, 'started_at' => now(), 'type' => 'background']` |
| `pbx_sync_progress_{$pbxId}` | Mensaje de progreso | 3600s (error: 300s) | String con estado actual |

### `handle(): void`

```
1. Cache::put(lock, metadata, 3600)
2. Cargar PbxConnection::find($pbxId) → $pbxName
│
3. ¿$syncExtensions?
│  ├── Progress: "Sincronizando extensiones de {$pbxName}..."
│  ├── Artisan::call('extensions:import', ['--pbx' => $pbxId, '--quick' => true])
│  └── Progress: "✓ Extensiones completadas. Preparando llamadas..."
│
4. ¿$syncCalls?
│  ├── Progress: "Sincronizando llamadas desde {$year}... (puede tardar minutos)"
│  ├── Artisan::call('calls:sync', ['--pbx' => $pbxId, '--year' => $year])
│  └── Progress: "✓ Sincronización completada!"
│
5. sleep(3)  ← pausa para que el usuario vea el mensaje de éxito en la UI
│
finally:
    Cache::forget(lockKey)
    Cache::forget(progressKey)
```

> **Observación:** Las extensiones siempre se sincronizan en modo `--quick` desde el job, porque el modo completo hace una API call por extensión (lento) y en background no se necesita la configuración SIP detallada.

### `failed(?\Throwable $exception): void`

Manejador de errores del job:

```
1. Cache::put(progressKey, "Error: {$exception->getMessage()}", 300)
   ← TTL de 5 minutos para que la UI muestre el error antes de limpiarse
2. sleep(5)  ← mantener el lock brevemente para evitar re-ejecuciones rápidas
3. Cache::forget(lockKey)
4. Cache::forget(progressKey)
```

---

## 7. `CallsExport` (app/Exports/CallsExport.php)

**Propósito:** Exportación de llamadas a formato Excel (.xlsx) usando Maatwebsite/Excel.

**Implementa:**
- `FromQuery` — genera Excel desde una query Eloquent (streaming, no carga todo en memoria)
- `WithHeadings` — primera fila con encabezados
- `WithMapping` — transforma cada modelo a fila
- `ShouldAutoSize` — auto-ajusta ancho de columnas
- `WithStyles` — estilos visuales

### Constructor

```php
public function __construct(protected $filtros)
```
Recibe array de filtros desde `CdrController::exportarExcel()`.

### `query(): Builder`

Construye query con filtros progresivos:

```php
$query = Call::query();

// 1. Rango de fecha
->whereDate('start_time', '>=', $filtros['fecha_inicio'])
->whereDate('start_time', '<=', $filtros['fecha_fin'])

// 2. Anexo específico (match exacto por source)
->where('source', $filtros['anexo'])

// 3. Tipo de llamada (misma lógica que CdrController)
switch ($filtros['tipo_llamada'] ?? 'all'):
    'internal': whereIn('userfield', ['Internal', 'Outbound'])
                OR (userfield null/vacío AND source REGEXP '^[0-9]{3,4}$')
    
    'external': where('userfield', 'Inbound')
                OR (userfield null/vacío AND source NOT REGEXP '^[0-9]{3,4}$')
    
    'all': sin filtro

// Orden: más recientes primero
->orderBy('start_time', 'desc')
```

> **Nota sobre `FromQuery`**: Maatwebsite/Excel ejecuta la query en chunks internamente usando `cursor()`, lo que mantiene el uso de memoria constante incluso con 100K+ registros.

### Columnas del Excel

| # | Encabezado | Valor | Fuente |
|---|---|---|---|
| 1 | Fecha y Hora | `2026-02-10 09:15:32` | `$call->start_time` |
| 2 | Origen | `1760` | `$call->source` |
| 3 | Destino | `912345678` | `$call->destination` |
| 4 | Tipo | `Celular` | `$call->call_type` (accessor) |
| 5 | Duración (seg) | `180` | `$call->billsec` |
| 6 | Costo ($) | `$45` | `$call->cost` (accessor) |
| 7 | Estado | `ANSWERED` | `$call->disposition` |

**Clasificación de tipo (accessor `call_type` del modelo Call):**

| Patrón regex destino | Tipo asignado |
|---|---|
| `/^\d{3,4}$/` | Interna |
| `/^800\d+$/` | Local (toll-free) |
| `/^600\d+$/` | Nacional (costo compartido) |
| `/^9\d{8}$/` | Celular |
| `/^(\+?56)9\d{8}$/` | Celular (con prefijo Chile) |
| `/^(\+?56)?2\d{8}$/` | Nacional (Santiago) |
| `/^(\+?56)?[3-8]\d{8}$/` | Nacional (regiones) |
| `/^(\+\|00)/` y NO `/^(\+?56)/` | Internacional |
| *(default)* | Nacional |

### Estilos

```php
public function styles(Worksheet $sheet)
{
    return [1 => ['font' => ['bold' => true]]]; // Fila 1 en negrita (encabezados)
}
```
