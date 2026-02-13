# Servicios, Traits y Providers — Documentación Detallada

> Este documento describe la capa de servicios que encapsula la comunicación con la API Grandstream, el análisis de facturación de llamadas y el patrón de inyección de dependencias del sistema.

---

## 1. `GrandstreamService` (app/Services/GrandstreamService.php)

**Propósito:** Cliente API centralizado para comunicación con centrales PBX Grandstream UCM6xxx. Implementa el protocolo de autenticación challenge/login/cookie propietario de Grandstream y gestiona la reconexión automática.

### Diseño del Servicio

El servicio sigue el patrón **Stateful Client** — mantiene una cookie de sesión activa y la reutiliza para múltiples llamadas a la API. Esto minimiza las autenticaciones (que requieren 2 requests: challenge + login).

### Propiedades

| Propiedad | Tipo | Descripción | Seguridad |
|---|---|---|---|
| `$host` | ?string | IP de la central | — |
| `$port` | ?int | Puerto de la API REST (ej: 7110) | — |
| `$username` | ?string | Usuario para autenticación | — |
| `$password` | ?string | Contraseña API (desencriptada del modelo) | No se persiste en logs |
| `$verifySsl` | bool | ¿Verificar certificado SSL? (default: false) | Configurable por central |
| `$apiCookie` | ?string | Cookie de sesión autenticada activa | Se resetea en cada nueva conexión |
| `$pbxConnectionId` | ?int | ID de la conexión PBX configurada | — |
| `$isConfigured` | bool | ¿Tiene central configurada? | — |

### Constructor

```php
public function __construct(?PbxConnection $connection = null)
```
Opcionalmente recibe un modelo `PbxConnection` para auto-configurarse. Esto permite tanto la inyección via container (AppServiceProvider) como la configuración manual en comandos CLI.

### Métodos Públicos

#### `setConnectionFromModel(PbxConnection $connection): self`
Configura el servicio desde un modelo `PbxConnection`. **Resetea la cookie API anterior** — importante al cambiar de central dentro de la misma sesión PHP.

---

#### `connectApi(string $action, array $params = [], int $timeout = 30): array`
**Método principal** — Gateway único para toda comunicación con la API Grandstream.

**Flujo de ejecución detallado:**

```
connectApi("cdrapi", [...])
     │
     ▼
¿Configurado? ──No──► throw RuntimeException
     │
    Sí
     │
     ▼
¿Tiene cookie? ──No──► authenticate() ──Falla──► return {status: -99}
     │                       │
    Sí                      Sí
     │                       │
     └───────┬───────────────┘
             │
             ▼
     POST https://{host}:{port}/api
     Body: { request: { action, cookie, ...params } }
             │
             ▼
     ¿Status -6?  ──Sí──► Cookie expirada
     (expired)            │
             │            ▼
            No       Re-authenticate()
             │            │
             │       Retry request
             │            │
             ▼            ▼
         Return JSON response
```

**Estructura del request HTTP:**
```json
{
  "request": {
    "action": "cdrapi",
    "cookie": "sid_abc123xyz",
    "format": "json",
    "startTime": "2026-02-01T00:00:00",
    "endTime": "2026-02-28T23:59:59"
  }
}
```

**Códigos de error manejados:**

| Status | Significado | Acción |
|---|---|---|
| `0` | Éxito | Retorna respuesta |
| `-6` | Cookie expirada | Re-autenticación automática + retry |
| `-99` | Fallo de autenticación | Error irrecuperable |
| `-500` | Error de conexión/timeout | Captura excepción HTTP |

**Configuración HTTP (Guzzle):**
- Timeout configurable (default 30s)
- `verify` según `$verifySsl`
- `Content-Type: application/json`

---

#### `testConnection(): bool`
Verifica la conectividad llamando a `getSystemStatus` y evaluando si `status === 0`. Usado como "health check" antes de operaciones críticas.

---

### Método Privado: `authenticate(): ?string`

Implementa la autenticación **challenge/login** propietaria de Grandstream:

**Paso 1 — Challenge:**
```json
POST /api
{ "request": { "action": "challenge", "user": "cdrapi", "version": "1.0" } }

Response: { "status": 0, "response": { "challenge": "a1b2c3d4e5f6..." } }
```

**Paso 2 — Login:**
```php
$token = md5($challenge . $password);
```
```json
POST /api
{ "request": { "action": "login", "user": "cdrapi", "token": "md5hash..." } }

Response: { "status": 0, "response": { "cookie": "sid_abc123xyz" } }
```

**Retorna** la cookie de sesión (string) o `null` si cualquier paso falla. La cookie se almacena en `$this->apiCookie` para reutilización.

> **Seguridad:** El password nunca se envía en texto plano — solo se usa localmente para calcular el hash MD5(challenge + password). El challenge es un valor aleatorio generado por la PBX que cambia en cada solicitud.

---

## 2. `CallBillingAnalyzer` (app/Services/CallBillingAnalyzer.php)

**Propósito:** Servicio de análisis detallado de CDR para determinar qué llamadas son facturables. Implementa reglas de clasificación más sofisticadas que el accessor `getCostAttribute()` del modelo Call, ya que analiza canales SIP, trunks y patrones de llamada complejos.

### Propiedades Configurables

| Propiedad | Default | Descripción | Personalización |
|---|---|---|---|
| `$maxInternalExtensionLength` | 4 | Máximo dígitos para extensión interna | Aumentar si la PBX usa extensiones de 5+ dígitos |
| `$minExternalNumberLength` | 7 | Mínimo dígitos para número externo | Ajustar según plan de numeración nacional |
| `$trunkPatterns` | `['trunk', 'movistar', 'claro', 'entel', 'sip']` | Patrones que identifican canales trunk | Agregar nombres de trunk específicos del cliente |

### Método Principal: `isBillable($callData): bool`

**Test de 5 criterios (TODOS deben cumplirse):**

```
┌─────────────────────────────────────────────────┐
│              ¿ES FACTURABLE?                    │
│                                                 │
│  1. ¿disposition === 'ANSWERED'?    ──No──► $0  │
│  2. ¿NO es Inbound (entrante)?      ──No──► $0  │
│  3. ¿NO es Internal (entre anexos)?  ──No──► $0 │
│  4. ¿SÍ es Outbound (sale por trunk)?──No──► $0 │
│  5. ¿Origen es anexo interno?        ──No──► $0 │
│                                                 │
│  Si TODOS son Sí → FACTURABLE ✓                 │
└─────────────────────────────────────────────────┘
```

> **Razón del criterio 5:** Evita cobrar llamadas originadas por trunks (ej: rutas de rebote SIP) que no representan uso real de extensiones.

### Métodos de Clasificación

#### `getRealExtension(string $channel): ?string`
Extrae el número/identificador real del canal SIP usando regex:

| Formato Canal | Regex | Resultado |
|---|---|---|
| `PJSIP/4444-000000a1` | `/^(?:PJSIP|SIP|IAX2)\/(.+)-[a-f0-9]+$/` | `4444` |
| `SIP/1001-0000b2` | Misma regex | `1001` |
| `PJSIP/trunk_2-00006d19` | Misma regex | `trunk_2` (no numérico → se identifica como trunk) |
| `IAX2/1200-xxx` | Misma regex | `1200` |

---

#### `isInternalExtension(?string $extension): bool`
- Debe ser puramente numérico (sin letras — filtra `trunk_1`)
- ≤ `$maxInternalExtensionLength` dígitos (default: 4)

#### `isExternalDestination(string $destination): bool`
- Limpia caracteres no numéricos (strip `+`, `-`, espacios)
- Si tiene ≥ `$minExternalNumberLength` dígitos → es externo

#### `isInboundFromTrunk(array $callData): bool`
Detecta llamada entrante usando 3 estrategias (fallback chain):
1. `userfield === 'inbound'` (más confiable — dato del UCM)
2. Existe `src_trunk_name` no vacío
3. El `channel` contiene un identificador de trunk (`$trunkPatterns`)

#### `isOutboundToTrunk(array $callData): bool`
Misma lógica pero para salientes:
1. `userfield === 'outbound'`
2. Existe `dst_trunk_name`
3. El `dstchannel` contiene trunk pattern

#### `isInternalCall(array $callData): bool`
1. `userfield === 'internal'`
2. Ambos extremos son extensiones internas
3. No hay trunks involucrados en ninguno de los canales

---

#### `analyze(array $callData): array`
Retorna análisis completo de una llamada:

```php
[
    'source_channel' => 'PJSIP/1760-00006d6f',
    'source_extension' => '1760',
    'source_is_internal' => true,
    'dest_channel' => 'PJSIP/trunk_movistar-00006d70',
    'dest_extension' => 'trunk_movistar',
    'dest_is_internal' => false,
    'is_inbound' => false,
    'is_outbound' => true,
    'is_internal' => false,
    'is_billable' => true,
    'disposition' => 'ANSWERED',
    'userfield' => 'Outbound'
]
```

---

## 3. `GrandstreamTrait` (app/Traits/GrandstreamTrait.php)

**Propósito:** Wrapper ergonómico del `GrandstreamService` para uso en controladores y comandos. Abstrae la obtención del servicio desde el container y proporciona acceso directo a los métodos más usados.

### Patrón Lazy Loading

```php
protected ?GrandstreamService $grandstreamService = null;

protected function getGrandstreamService(): GrandstreamService
{
    if (!$this->grandstreamService) {
        $this->grandstreamService = app(GrandstreamService::class);
    }
    return $this->grandstreamService;
}
```

La instancia se obtiene una sola vez del container y se cachea en la propiedad. En controladores, el container la inyecta ya configurada con la central activa (gracias al `AppServiceProvider`).

### Métodos Proxy

| Método | Delega a | Descripción |
|---|---|---|
| `connectApi(action, params, timeout)` | `GrandstreamService::connectApi()` | Método más usado — gateway a la API |
| `testConnection()` | `GrandstreamService::testConnection()` | Health check |
| `isPbxConfigured()` | `GrandstreamService::isConfigured()` | ¿Hay central configurada? |
| `getActivePbxId()` | `GrandstreamService::getPbxConnectionId()` | ID de la PBX activa |

#### `configurePbx(int $pbxId): bool`
Configura el servicio con una central específica por ID. **Esencial para comandos CLI** donde no hay sesión HTTP:

```php
1. Busca PbxConnection::find($pbxId)
2. Resetea $grandstreamService = null (fuerza nueva instancia)
3. Crea nueva instancia y llama setConnectionFromModel()
```

> **Nota sobre `cdrapi`:** El formato de fecha obligatorio es `YYYY-MM-DDTHH:MM:SS` — la "T" literalmente es requerida por la API de Grandstream. Sin ella, la API retorna resultados vacíos sin error.

---

## 4. `AppServiceProvider` (app/Providers/AppServiceProvider.php)

**Propósito:** Configura el binding dinámico del `GrandstreamService` en el service container de Laravel. Este binding es lo que hace posible la transparencia multi-tenant.

### `register(): void`

```php
$this->app->bind(GrandstreamService::class, function ($app) {
    $service = new GrandstreamService();
    $activePbxId = session('active_pbx_id');
    if ($activePbxId) {
        $pbx = PbxConnection::find($activePbxId);
        if ($pbx) {
            $service->setConnectionFromModel($pbx);
        }
    }
    return $service;
});
```

**Flujo de resolución:**

```
Controller usa GrandstreamTrait
        │
        ▼
getGrandstreamService()
        │
        ▼
app(GrandstreamService::class)
        │
        ▼
AppServiceProvider::register()
        │
        ▼
new GrandstreamService()
        │
        ▼
Lee session('active_pbx_id')
        │
        ├── Existe → PbxConnection::find() → setConnectionFromModel()
        │              → password se desencripta automáticamente (cast encrypted)
        │              → Servicio listo para API calls
        │
        └── No existe → Servicio sin configurar (isPbxConfigured() = false)
```

**Resultado:** Cualquier controlador que use el `GrandstreamTrait` obtiene automáticamente un servicio configurado con la central activa del usuario — sin necesidad de pasar IDs o credenciales manualmente.

---

## 5. `ProfileUpdateRequest` (app/Http/Requests/ProfileUpdateRequest.php)

**Propósito:** Form Request de Laravel Breeze para validación de actualización de perfil.

### Reglas de Validación

| Campo | Reglas | Notas |
|---|---|---|
| `name` | required, string, max:255 | Nombre visible del usuario |
| `email` | required, string, lowercase, email, max:255, unique (ignora usuario actual) | `Rule::unique('users')->ignore($this->user()->id)` evita conflicto consigo mismo |
