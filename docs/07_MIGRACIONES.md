# Migraciones de Base de Datos — Documentación Detallada

> Documenta la evolución del esquema de base de datos del sistema, desde las tablas base de Laravel hasta las customizaciones multi-tenant del Panel de Llamadas.

---

## Línea Temporal de Evolución del Esquema

```
Ene 08 ──► calls (CDR básico)
         ──► extensions (nombre + extensión)
                    │
Ene 19 ──────────── ├── + campos SIP (permission, dnd)
Ene 19 ──────────── ├── + max_contacts
Ene 20 ──► calls + índices (performance)
Ene 22 ──► settings (tarifas)
Ene 26 ──────────── ├── + secret (contraseña SIP)
                    │
Ene 28 ──► pbx_connections (multi-tenant)
         ──► calls/extensions + pbx_connection_id (FK)
Ene 29 ──────────── └── + ip (dispositivo)
                    │
Ene 30 ──► calls + userfield (clasificación UCM)
                    │
Feb 02 ──► users + role, 8 permisos booleanos
         ──► pbx_connections + status/sync_message/last_sync_at
Feb 02 ──► calls + campos detallados (channels, trunks)
Feb 03 ──► queue_call_details (estadísticas de colas)
Feb 10 ──► pbx_connection_user (pivot multi-tenant)
```

> **Patrón de evolución:** El esquema creció incrementalmente: primero funcionalidad core (CDR), luego extensiones SIP, después multi-tenancy (PBX connections + pivot), y finalmente permisos granulares.

---

## Orden de Migraciones

### Migraciones Base (Laravel)

| Migración | Tabla | Descripción |
|---|---|---|
| `0001_01_01_000000` | `users`, `password_reset_tokens`, `sessions` | Autenticación + sesiones basadas en BD |
| `0001_01_01_000001` | `cache`, `cache_locks` | Cache + locks para SyncPbxDataJob |
| `0001_01_01_000002` | `jobs`, `job_batches`, `failed_jobs` | Infraestructura de colas (SyncPbxDataJob) |

### Migraciones del Proyecto

| # | Migración | Tabla | Acción | Descripción |
|---|---|---|---|---|
| 1 | `2026_01_08_134241` | `calls` | CREATE | Tabla principal de CDR: unique_id, timestamps, source/destination, duration/billsec, disposition |
| 2 | `2026_01_08_153130` | `extensions` | CREATE | Anexos del UCM: extension (número), fullname |
| 3 | `2026_01_19_095049` | `extensions` | ALTER | + first_name, last_name, email, phone, permission (default 'Internal'), do_not_disturb (default false) |
| 4 | `2026_01_19_103617` | `extensions` | ALTER | + max_contacts (int, default 1) — límite de dispositivos SIP simultáneos |
| 5 | `2026_01_20_174758` | `calls` | ALTER | + **4 índices**: source, destination, start_time, disposition — critical para dashboards |
| 6 | `2026_01_22_111944` | `settings` | CREATE | Key-value para tarifas: key (unique), label, value |
| 7 | `2026_01_26_103631` | `extensions` | ALTER | + secret (nullable) — contraseña SIP para verificación |
| 8 | `2026_01_28_000001` | `pbx_connections` | CREATE | Centrales PBX: name, ip, port, username, password (encrypted), verify_ssl |
| 9 | `2026_01_28_000002` | `calls`, `extensions` | ALTER | + pbx_connection_id (FK nullable → pbx_connections) en ambas tablas |
| 10 | `2026_01_29_103320` | `extensions` | ALTER | + ip (nullable) — IP del dispositivo SIP registrado |
| 11 | `2026_01_30_000001` | `calls` | ALTER | + userfield (nullable) — clasificación UCM: Inbound/Outbound/Internal |
| 12 | `2026_02_02_104533` | `users` | ALTER | + role (default 'user') — valores: 'admin', 'user' |
| 13 | `2026_02_02_113038` | `users` | ALTER | + 7 permisos booleanos: can_sync_calls, can_edit_extensions, can_update_ips, can_edit_rates, can_manage_pbx, can_export_pdf, can_export_excel |
| 14 | `2026_02_02_115525` | `users` | ALTER | + can_view_charts (bool, default false) — 8º permiso |
| 15 | `2026_02_02_121824` | `pbx_connections` | ALTER | + status (default 'pending'), sync_message, last_sync_at — máquina de estados |
| 16 | `2026_02_02_170000` | `calls` | ALTER | + dstanswer, action_type, lastapp, channel, dst_channel, src_trunk_name — datos CDR extendidos |
| 17 | `2026_02_03_165548` | `queue_call_details` | CREATE | Detalle de llamadas en cola: pbx_connection_id (FK), queue, caller, agent, call_time (indexed), wait/talk_time, connected |
| 18 | `2026_02_10_113840` | `pbx_connection_user` | CREATE | Tabla pivot M:N users↔pbx_connections. UNIQUE(user_id, pbx_connection_id). CASCADE DELETE en ambas FK |

> **Migración #5 (índices)** es crítica para performance: el dashboard ejecuta queries con `WHERE source = ? AND start_time BETWEEN ? AND ?` sobre tablas de 100K+ registros.

> **Migración #9** marca el punto de inflexión multi-tenant: a partir de aquí, todas las queries deben incluir `pbx_connection_id` para aislar datos entre centrales.

---

## Esquema Final de Tablas

### `calls` — Registros CDR

```sql
CREATE TABLE calls (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pbx_connection_id BIGINT UNSIGNED NULL REFERENCES pbx_connections(id),
    unique_id       VARCHAR(255),           -- ID único del CDR en la PBX
    start_time      DATETIME,               -- Inicio de la llamada
    answer_time     DATETIME NULL,           -- Momento de contestar
    end_time        DATETIME NULL,           -- Fin de la llamada
    source          VARCHAR(255),            -- Origen (extensión o número externo)
    destination     VARCHAR(255),            -- Destino
    dstanswer       VARCHAR(255) NULL,       -- Respuesta del destino
    caller_name     VARCHAR(255) NULL,       -- Nombre del caller ID
    duration        INT DEFAULT 0,           -- Duración total (segundos)
    billsec         INT DEFAULT 0,           -- Segundos facturables
    disposition     VARCHAR(50),             -- ANSWERED|NO ANSWER|BUSY|FAILED
    action_type     VARCHAR(255) NULL,       -- Tipo de acción PBX
    lastapp         VARCHAR(255) NULL,       -- Última aplicación Asterisk
    channel         VARCHAR(255) NULL,       -- Canal SIP origen
    dst_channel     VARCHAR(255) NULL,       -- Canal SIP destino
    src_trunk_name  VARCHAR(255) NULL,       -- Trunk de origen
    userfield       VARCHAR(255) NULL,       -- Inbound|Outbound|Internal
    recording_file  VARCHAR(255) NULL,       -- Path del archivo de grabación
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    
    INDEX idx_source (source),
    INDEX idx_destination (destination),
    INDEX idx_start_time (start_time),
    INDEX idx_disposition (disposition)
);
```

**Volumen estimado:** 5K-50K registros/mes por central.

---

### `extensions` — Extensiones SIP

```sql
CREATE TABLE extensions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pbx_connection_id BIGINT UNSIGNED NULL REFERENCES pbx_connections(id),
    extension       VARCHAR(255),            -- Número de extensión (ej: 1001)
    fullname        VARCHAR(255) NULL,       -- Nombre completo
    first_name      VARCHAR(255) NULL,
    last_name       VARCHAR(255) NULL,
    email           VARCHAR(255) NULL,
    phone           VARCHAR(255) NULL,       -- Teléfono directo
    ip              VARCHAR(255) NULL,       -- IP del dispositivo SIP
    permission      VARCHAR(50) DEFAULT 'Internal',  -- Internal|Local|National|International
    do_not_disturb  BOOLEAN DEFAULT FALSE,   -- No molestar activo
    max_contacts    INT DEFAULT 1,           -- Dispositivos simultáneos
    secret          VARCHAR(255) NULL,       -- Contraseña SIP
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

**Volumen:** 20-500 registros por central.

---

### `pbx_connections` — Centrales PBX

```sql
CREATE TABLE pbx_connections (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255),            -- Nombre descriptivo
    ip              VARCHAR(255),            -- IP/hostname de la central
    port            INT,                     -- Puerto API REST
    username        VARCHAR(255),            -- Usuario API
    password        TEXT,                    -- Contraseña (encrypted at-rest)
    verify_ssl      BOOLEAN DEFAULT FALSE,   -- Verificar certificado SSL
    status          VARCHAR(50) DEFAULT 'pending',  -- pending|syncing|ready|error
    sync_message    TEXT NULL,               -- Mensaje de última sincronización
    last_sync_at    DATETIME NULL,           -- Timestamp de última sync
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

**Volumen:** 1-10 registros típicamente.

---

### `queue_call_details` — Detalle de Llamadas en Cola

```sql
CREATE TABLE queue_call_details (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pbx_connection_id BIGINT UNSIGNED REFERENCES pbx_connections(id),
    queue           VARCHAR(255),            -- Número de cola (ej: 6500)
    caller          VARCHAR(255),            -- Número del llamante
    agent           VARCHAR(255),            -- Extensión del agente
    call_time       DATETIME,               -- Momento de la llamada
    wait_time       INT DEFAULT 0,           -- Segundos en espera
    talk_time       INT DEFAULT 0,           -- Segundos de conversación
    connected       BOOLEAN DEFAULT FALSE,   -- ¿Se conectó con agente?
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    
    INDEX idx_call_time (call_time)
);
```

**Volumen:** 1K-20K registros/mes por central.

---

### `settings` — Configuración (Tarifas)

```sql
CREATE TABLE settings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key             VARCHAR(255) UNIQUE,     -- price_mobile, price_national, price_international
    label           VARCHAR(255),            -- Nombre descriptivo
    value           TEXT,                    -- Valor de la tarifa
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

**Volumen:** 3 registros fijos.

---

### `users` — Usuarios del Sistema

```sql
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255),
    email           VARCHAR(255) UNIQUE,
    email_verified_at DATETIME NULL,
    password        VARCHAR(255),
    role            VARCHAR(50) DEFAULT 'user',   -- admin | user
    can_sync_calls      BOOLEAN DEFAULT FALSE,
    can_edit_extensions  BOOLEAN DEFAULT FALSE,
    can_update_ips       BOOLEAN DEFAULT FALSE,
    can_edit_rates       BOOLEAN DEFAULT FALSE,
    can_manage_pbx       BOOLEAN DEFAULT FALSE,
    can_export_pdf       BOOLEAN DEFAULT FALSE,
    can_export_excel     BOOLEAN DEFAULT FALSE,
    can_view_charts      BOOLEAN DEFAULT FALSE,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

> **Administradores** ignoran los permisos booleanos — siempre tienen acceso completo. Los permisos solo aplican a usuarios con `role = 'user'`.

---

### `pbx_connection_user` — Pivot Multi-Tenant

```sql
CREATE TABLE pbx_connection_user (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED REFERENCES users(id) ON DELETE CASCADE,
    pbx_connection_id BIGINT UNSIGNED REFERENCES pbx_connections(id) ON DELETE CASCADE,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    
    UNIQUE INDEX (user_id, pbx_connection_id)
);
```

> **Cascade delete:** Si se elimina un usuario, sus asignaciones de centrales se borran automáticamente. Si se elimina una central, se desvinculan todos sus usuarios (pero los usuarios no se eliminan).

---

## Diagrama de Relaciones (ER)

```
┌──────────┐     M:N       ┌──────────────────┐
│  users   │◄────────────► │ pbx_connections  │
│          │  pivot table  │                  │
│ role     │               │ password(enc)    │
│ 8 perms  │               │ status           │
└──────────┘               └────────┬─────────┘
                                    │ 1
                           ┌────────┼────────────┐
                           │        │            │
                           ▼ N      ▼ N          ▼ N
                    ┌──────────┐ ┌──────┐ ┌───────────────┐
                    │extensions│ │calls │ │queue_call_    │
                    │          │ │      │ │details        │
                    │permission│ │cost* │ │wait_time      │
                    │ip        │ │type* │ │talk_time      │
                    └──────────┘ └──────┘ └───────────────┘
                                 * = accessor

              ┌──────────┐
              │ settings │  (independiente, sin FK)
              │ key=value│
              └──────────┘
```
