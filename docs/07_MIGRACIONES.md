# Migraciones de Base de Datos - Documentación Detallada

---

## Orden de Migraciones

### Migraciones Base (Laravel)

| Migración | Tabla | Descripción |
|---|---|---|
| `0001_01_01_000000` | `users`, `password_reset_tokens`, `sessions` | Tablas de usuarios, reset de contraseña y sesiones |
| `0001_01_01_000001` | `cache`, `cache_locks` | Tablas de cache |
| `0001_01_01_000002` | `jobs`, `job_batches`, `failed_jobs` | Tablas de colas de trabajos |

### Migraciones del Proyecto

| Migración | Tabla/Acción | Descripción |
|---|---|---|
| `2026_01_08_134241` | `calls` | **Crea tabla de llamadas.** Campos principales: unique_id, start_time, answer_time, end_time, source, destination, caller_name, duration, billsec, disposition, recording_file |
| `2026_01_08_153130` | `extensions` | **Crea tabla de extensiones.** Campos: extension, fullname (nullable), timestamps |
| `2026_01_19_095049` | `extensions` (alter) | **Añade campos** a extensions: first_name, last_name, email, phone, permission (default 'Internal'), do_not_disturb (default false) |
| `2026_01_19_103617` | `extensions` (alter) | **Añade campo** `max_contacts` (integer, default 1) |
| `2026_01_20_174758` | `calls` (alter) | **Añade índices** a calls: source, destination, start_time, disposition para mejorar rendimiento de consultas |
| `2026_01_22_111944` | `settings` | **Crea tabla settings.** Campos: key (unique), label, value |
| `2026_01_26_103631` | `extensions` (alter) | **Añade campo** `secret` (nullable) para contraseña SIP |
| `2026_01_28_000001` | `pbx_connections` | **Crea tabla de conexiones PBX.** Campos: name, ip, port, username, password, verify_ssl (default false), timestamps |
| `2026_01_28_000002` | `calls`, `extensions` (alter) | **Añade** `pbx_connection_id` (foreignId, nullable) a ambas tablas con FK a pbx_connections |
| `2026_01_29_103320` | `extensions` (alter) | **Añade campo** `ip` (nullable) para dirección IP del dispositivo |
| `2026_01_30_000001` | `calls` (alter) | **Añade campo** `userfield` (nullable) para clasificación UCM (Inbound/Outbound/Internal) |
| `2026_02_02_104533` | `users` (alter) | **Añade campo** `role` (default 'user') |
| `2026_02_02_113038` | `users` (alter) | **Añade permisos booleanos:** can_sync_calls, can_edit_extensions, can_update_ips, can_edit_rates, can_manage_pbx, can_export_pdf, can_export_excel (todos default false) |
| `2026_02_02_115525` | `users` (alter) | **Añade campo** `can_view_charts` (default false). Nota: el nombre de la migración menciona "supervisor_role" pero solo añade este permiso |
| `2026_02_02_121824` | `pbx_connections` (alter) | **Añade campos:** status (default 'pending'), sync_message (nullable), last_sync_at (nullable) |
| `2026_02_02_170000` | `calls` (alter) | **Añade campos detallados:** dstanswer, action_type, lastapp, channel, dst_channel, src_trunk_name (todos nullable) |
| `2026_02_03_165548` | `queue_call_details` | **Crea tabla de detalles de colas.** Campos: pbx_connection_id (FK), queue, caller, agent, call_time (datetime con índice), wait_time, talk_time, connected (boolean), timestamps |
| `2026_02_10_113840` | `pbx_connection_user` | **Crea tabla pivot** para control de acceso usuario-central. Campos: user_id (FK→users, cascade delete), pbx_connection_id (FK→pbx_connections, cascade delete). Constraint unique en par (user_id, pbx_connection_id) |

---

## Esquema Final de Tablas

### `calls`
```
id, pbx_connection_id(FK), unique_id, start_time, answer_time, end_time,
source, destination, dstanswer, caller_name, duration, billsec, disposition,
action_type, lastapp, channel, dst_channel, src_trunk_name, userfield,
recording_file, timestamps
Índices: source, destination, start_time, disposition
```

### `extensions`
```
id, pbx_connection_id(FK), extension, fullname, first_name, last_name,
email, phone, ip, permission, do_not_disturb, max_contacts, secret, timestamps
```

### `pbx_connections`
```
id, name, ip, port, username, password(encrypted), verify_ssl,
status, sync_message, last_sync_at, timestamps
```

### `queue_call_details`
```
id, pbx_connection_id(FK), queue, caller, agent, call_time(indexed),
wait_time, talk_time, connected, timestamps
```

### `settings`
```
id, key(unique), label, value, timestamps
```

### `users`
```
id, name, email, email_verified_at, password, role,
can_sync_calls, can_edit_extensions, can_update_ips, can_edit_rates,
can_manage_pbx, can_export_pdf, can_export_excel, can_view_charts,
remember_token, timestamps
```

### `pbx_connection_user` (tabla pivot)
```
id, user_id(FK→users), pbx_connection_id(FK→pbx_connections), timestamps
Restricción UNIQUE en (user_id, pbx_connection_id)
Cascade delete en ambas FK
```

> Esta tabla controla qué centrales PBX puede ver cada usuario. Los administradores ven todas las centrales automáticamente sin necesitar registros en esta tabla.
