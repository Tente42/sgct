# Servicios, Traits y Providers - Documentación Detallada

---

## 1. `GrandstreamService` (app/Services/GrandstreamService.php)

**Propósito:** Servicio centralizado para comunicación con la API de centrales PBX Grandstream UCM. Implementa autenticación challenge/login/cookie.

### Propiedades Privadas

| Propiedad | Tipo | Descripción |
|---|---|---|
| `$host` | ?string | IP de la central |
| `$port` | ?int | Puerto de la API |
| `$username` | ?string | Usuario API |
| `$password` | ?string | Contraseña API |
| `$verifySsl` | bool | ¿Verificar SSL? (default: false) |
| `$apiCookie` | ?string | Cookie de autenticación activa |
| `$pbxConnectionId` | ?int | ID de la conexión PBX |
| `$isConfigured` | bool | ¿Está configurado? |

### Constructor

```php
public function __construct(?PbxConnection $connection = null)
```
Opcionalmente recibe un modelo `PbxConnection` para auto-configurarse.

### Métodos Públicos

#### `setConnectionFromModel(PbxConnection $connection): self`
Configura el servicio desde un modelo `PbxConnection`. Resetea la cookie API anterior.

---

#### `getPbxConnectionId(): ?int`
Retorna el ID de la conexión configurada.

---

#### `isConfigured(): bool`
Verifica si el servicio tiene una central configurada.

---

#### `connectApi(string $action, array $params = [], int $timeout = 30): array`
**Método principal** para comunicarse con la API Grandstream.

**Flujo:**
1. Verifica que esté configurado
2. Si no hay cookie y la acción no es login/challenge → auto-login
3. Envía POST a `https://{host}:{port}/api` con estructura:
   ```json
   { "request": { "action": "...", "cookie": "...", ...params } }
   ```
4. Si la cookie expiró (status -6) → re-autenticación automática
5. Retorna el JSON de respuesta

**Errores retornados:**
- `status: -99` → Fallo de login
- `status: -500` → Error de conexión o respuesta vacía

---

#### `testConnection(): bool`
Verifica la conexión llamando a `getSystemStatus`. Retorna `true` si status == 0.

---

### Métodos Privados

#### `authenticate(): ?string`
Implementa la autenticación **challenge/login** de Grandstream:

1. **Challenge:** POST con `action: challenge`, `user: username` → Obtiene string `challenge`
2. **Login:** POST con `action: login`, `token: md5(challenge + password)` → Obtiene `cookie`

Retorna la cookie de sesión o `null` si falla.

---

## 2. `CallBillingAnalyzer` (app/Services/CallBillingAnalyzer.php)

**Propósito:** Analiza registros CDR para determinar qué llamadas son facturables. Implementa lógica detallada de clasificación de llamadas.

### Propiedades Configurables

| Propiedad | Default | Descripción |
|---|---|---|
| `$maxInternalExtensionLength` | 4 | Máximo de dígitos para considerar extensión interna |
| `$minExternalNumberLength` | 7 | Mínimo de dígitos para considerar número externo |
| `$trunkPatterns` | ['trunk', 'movistar', 'claro', 'entel', 'sip'] | Patrones que identifican trunks |

### Métodos Públicos

#### `getRealExtension(string $channel): ?string`
Extrae el número/identificador real del canal SIP.

**Formatos soportados:**
- `PJSIP/4444-000000a1` → `4444`
- `SIP/1001-0000b2` → `1001`
- `PJSIP/trunk_2-00006d19` → `trunk_2`
- `IAX2/1200-xxx` → `1200`

---

#### `isInternalExtension(?string $extension): bool`
Determina si un identificador es un anexo interno:
- Debe ser puramente numérico (sin letras)
- ≤ 4 dígitos

---

#### `isExternalDestination(string $destination): bool`
Determina si un número destino es externo:
- Limpia caracteres no numéricos
- Si tiene ≥ 7 dígitos, es externo

---

#### `isInboundFromTrunk(array $callData): bool`
Detecta si una llamada viene desde afuera:
1. `userfield === 'inbound'`
2. Existe `src_trunk_name`
3. El channel contiene un identificador de trunk

---

#### `isOutboundToTrunk(array $callData): bool`
Detecta si una llamada sale hacia afuera:
1. `userfield === 'outbound'`
2. Existe `dst_trunk_name`
3. El dstchannel contiene un identificador de trunk

---

#### `isTrunkIdentifier(string $identifier): bool`
Verifica si un string corresponde a un trunk comparando contra `$trunkPatterns`.

---

#### `isInternalCall(array $callData): bool`
Detecta llamada interna:
1. `userfield === 'internal'`
2. Ambos extremos son extensiones internas
3. No hay trunks involucrados

---

#### `isBillable($callData): bool`
**Método principal.** Determina si una llamada debe ser cobrada.

**Criterios (TODOS deben cumplirse):**
1. `disposition === 'ANSWERED'`
2. NO es Inbound (entrante)
3. NO es Internal (interna)
4. SÍ es Outbound (saliente por trunk)
5. El origen es un anexo interno (no un trunk)

---

#### `analyze(array $callData): array`
Analiza una llamada y retorna información detallada completa (canal, tipo, clasificación, si es facturable, etc.).

---

## 3. `GrandstreamTrait` (app/Traits/GrandstreamTrait.php)

**Propósito:** Wrapper del `GrandstreamService` para uso en controladores y comandos. Gestiona la instancia cacheada del servicio.

### Propiedades

| Propiedad | Tipo | Descripción |
|---|---|---|
| `$grandstreamService` | ?GrandstreamService | Instancia cacheada del servicio |

### Métodos Protegidos

#### `getGrandstreamService(): GrandstreamService`
Obtiene la instancia del servicio (lazy loading desde el container de Laravel).

---

#### `connectApi(string $action, array $params = [], int $timeout = 30): array`
Proxy a `GrandstreamService::connectApi()`. Método más usado en controladores.

**NOTA sobre cdrapi:** El formato de fecha debe ser `YYYY-MM-DDTHH:MM:SS` (la 'T' es obligatoria).

---

#### `testConnection(): bool`
Proxy a `GrandstreamService::testConnection()`.

---

#### `isPbxConfigured(): bool`
Verifica si hay una central configurada.

---

#### `getActivePbxId(): ?int`
Obtiene el ID de la PBX activa.

---

#### `configurePbx(int $pbxId): bool`
Configura el servicio con una central específica por ID. Útil para comandos de consola donde no hay sesión.

**Lógica:**
1. Busca `PbxConnection` por ID
2. Resetea instancia cacheada
3. Llama a `setConnectionFromModel()` en el servicio

---

## 4. `AppServiceProvider` (app/Providers/AppServiceProvider.php)

**Propósito:** Configura el binding del `GrandstreamService` en el service container de Laravel.

### `register(): void`

Registra un **binding dinámico** para `GrandstreamService`:
1. Crea nueva instancia de `GrandstreamService`
2. Lee `active_pbx_id` de la sesión
3. Si existe, busca el `PbxConnection` correspondiente
4. Configura el servicio con los datos de la central (el password se desencripta automáticamente por el cast `encrypted` del modelo)

**Esto permite** que cualquier controlador/comando que use el trait `GrandstreamTrait` obtenga automáticamente una instancia configurada con la central activa.

---

## 5. `ProfileUpdateRequest` (app/Http/Requests/ProfileUpdateRequest.php)

**Propósito:** Form Request para validación de actualización de perfil.

### Reglas de Validación

| Campo | Reglas |
|---|---|
| `name` | required, string, max:255 |
| `email` | required, string, lowercase, email, max:255, unique (ignora usuario actual) |
