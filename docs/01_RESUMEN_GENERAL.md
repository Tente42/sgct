# Gestion UCM — Resumen General del Proyecto

## 1. Visión del Producto

**Panel de Llamadas** es una plataforma empresarial de gestión y análisis de telefonía IP desarrollada para organizaciones que operan centrales PBX **Grandstream UCM6xxx**. El sistema resuelve la necesidad de visibilidad, control de costos y monitoreo operativo de las comunicaciones telefónicas corporativas.

### Problema que Resuelve

Las centrales Grandstream ofrecen una API REST pero carecen de herramientas analíticas robustas para:
- **Control de costos**: No calculan tarifas por tipo de destino chileno (celular, fijo, internacional).
- **Visibilidad operativa**: Los reportes CDR nativos son limitados y no ofrecen KPIs de colas en tiempo real.
- **Gestión multi-central**: Empresas con varias sedes necesitan administrar múltiples PBX desde un solo panel.
- **Gobierno de acceso**: No existe control granular sobre qué usuarios pueden ver o modificar configuraciones.

### Propuesta de Valor

| Capacidad | Detalle |
|---|---|
| Facturación automática | Clasifica llamadas por tipo de destino chileno (celular, fijo RM, regiones, 600/800, internacional) y calcula costos con tarifas configurables |
| Multi-tenant | Soporte nativo para N centrales PBX, con aislamiento completo de datos por central y control de acceso por usuario |
| KPIs de Contact Center | Dashboard de colas con métricas de abandono, ASA, rendimiento por agente y alertas automáticas por umbrales |
| Gestión remota | Edición de extensiones, desvíos de llamada y contraseñas SIP directamente desde la web, sin acceder a la central |
| Exportación profesional | Reportes en PDF (DomPDF) y Excel (Maatwebsite) con filtros avanzados |

---

## 2. Stack Tecnológico

### Backend

| Componente | Tecnología | Versión | Notas |
|---|---|---|---|
| Framework | Laravel | ^11.0 | Long-term support, arquitectura MVC |
| Lenguaje | PHP | ^8.2 | Tipado estricto, enums, fibers |
| Base de Datos | MySQL | 8.x | Índices optimizados en CDR |
| Autenticación | Laravel Breeze | ^2.3 | Scaffolding auth con login personalizado |
| PDF | barryvdh/laravel-dompdf | ^3.1 | Generación server-side |
| Excel | maatwebsite/excel | ^3.1 | Export con streaming `FromQuery` |
| Colas | Laravel Queue (database) | Built-in | Jobs asíncronos para sincronización |
| Logs | Laravel Pail | ^1.2.2 | Visor de logs en dev |

### Frontend

| Componente | Tecnología | Versión | Notas |
|---|---|---|---|
| Plantillas | Laravel Blade | Built-in | Server-Side Rendering |
| Reactividad | Alpine.js | ^3.4.2 | Componentes ligeros sin build step |
| Estilos | Tailwind CSS | ^3.1.0 | Utility-first + plugin `@tailwindcss/forms` |
| Gráficos | Chart.js | 4.4.1 | CDN, pie/line/bar charts |
| HTTP Client | Axios | ^1.11.0 | AJAX con CSRF automático |
| Iconos | Font Awesome | 6.5.2 | CDN |
| Bundler | Vite | ^7.0.7 | HMR en desarrollo, build optimizado |

---

## 3. Arquitectura del Sistema

### 3.1 Patrón Multi-Tenant por Sesión

El sistema implementa **aislamiento de datos por central PBX** usando Eloquent Global Scopes:

```
┌──────────────────────────────────────────────────────────────┐
│                        SESIÓN DE USUARIO                     │
│  ┌──────────────┐  ┌───────────────┐  ┌───────────────────┐  │
│  │ active_pbx_id│  │active_pbx_name│  │ Credenciales Auth │  │
│  └──────┬───────┘  └───────────────┘  └───────────────────┘  │
│         │                                                    │
│         ▼                                                    │
│  ┌──────────────────────────────────────────────────────┐    │
│  │           GLOBAL SCOPES (automáticos)                │    │
│  │  Call::query()     → WHERE pbx_connection_id = X     │    │
│  │  Extension::query()→ WHERE pbx_connection_id = X     │    │
│  │  QueueCallDetail:: → WHERE pbx_connection_id = X     │    │
│  └──────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────┘
```

**Garantías de aislamiento:**
- Todas las consultas SELECT agregan filtro automáticamente
- Los INSERT auto-asignan `pbx_connection_id` desde la sesión
- Las operaciones administrativas usan `::withoutGlobalScope()` explícitamente
- El middleware `CheckPbxSelected` impide acceso sin central seleccionada

### 3.2 Integración con API Grandstream

```
┌─────────────┐     Challenge/Login       ┌──────────────────┐
│  Panel de   │ ────────────────────────► │  Grandstream UCM │
│  Llamadas   │ ◄──────────────────────── │  (API REST)      │
│             │     Cookie de sesión      │                  │
│  Laravel    │ ────────────────────────► │  Endpoints:      │
│  App        │     API calls + cookie    │  - cdrapi        │
│             │ ◄──────────────────────── │  - listUser      │
│             │     JSON responses        │  - queueapi      │
│             │                           │  - getSIPAccount │
│             │     Auto-reconnect        │  - updateUser    │
│             │     si cookie expira      │  - applyChanges  │
└─────────────┘                           └──────────────────┘
```

**Protocolo de autenticación challenge/login:**
1. `POST {action: challenge, user: X}` → Recibe string `challenge`
2. `POST {action: login, token: MD5(challenge + password)}` → Recibe `cookie`
3. Todas las llamadas subsiguientes incluyen la `cookie`
4. Si status `-6` (cookie expirada) → re-autenticación automática

### 3.3 Máquina de Estados de PBX

```
                    ┌─────────┐
           store()  │ PENDING │  Central creada, sin datos
                    └────┬────┘
                         │ syncExtensions()
                         ▼
                    ┌──────────┐
                    │ SYNCING  │  Importando extensiones y llamadas
                    └────┬─────┘
                    ╱         ╲
              éxito             error
               ╱                   ╲
           ▼                         ▼
      ┌─────────┐              ┌─────────┐
      │  READY  │              │  ERROR  │
      │         │              │         │
      └─────────┘              └────┬────┘
      Operativa                     │ Reintentar
      y seleccionable               ▼
                             ┌──────────┐
                             │ SYNCING  │
                             └──────────┘
```

---

## 4. Módulos Funcionales

### 4.1 Gestión de Centrales PBX
- **CRUD completo** de conexiones a centrales Grandstream UCM
- **Asistente de sincronización** paso a paso con barra de progreso visua
- Sincronización de extensiones (modo rápido o completo con detalles SIP)
- Sincronización de llamadas mes por mes con polling de progreso
- **Credenciales seguras**: passwords encriptados en BD (`encrypted` cast)
- **SSL configurable** por conexión (`verify_ssl`)
- Máquina de estados: `pending` → `syncing` → `ready` / `error`

### 4.2 Dashboard de Llamadas (CDR)
- **Listado paginado** (50 registros/página) con ordenamiento por columnas
- **Filtros avanzados**: rango de fechas, anexo/origen, tipo de llamada (salientes/entrantes/todas)
- **3 tarjetas KPI**: Total llamadas, Minutos facturables, Total a cobrar (CLP)
- **Facturación chilena automática**: Clasificación por destino con regex específicos del mercado chileno
  - Celular (9XXXXXXXX) — tarifa móvil
  - Fijo Santiago (2XXXXXXXX) — tarifa nacional
  - Fijo Regiones (3-8XXXXXXXX) — tarifa nacional
  - 600/800 — gratuito o tarifa reducida
  - Internacional (+XX / 00XX) — tarifa internacional
- **Regla de gracia**: Llamadas ≤ 3 segundos no se cobran
- **Redondeo al minuto**: `ceil(billsec / 60) * tarifa`
- Sincronización incremental desde última llamada conocida (-1 hora de overlap)
- **Exportación PDF**: Máximo 500 registros, formato carta, incluye resumen de costos
- **Exportación Excel**: Streaming desde cursor DB (`FromQuery`), sin límite práctico de registros

### 4.3 Gestión de Extensiones
- **Tabla interactiva** con todos los campos SIP: nombre, permisos, DND, max contactos, IP
- **Edición bidireccional**: Cambios se aplican tanto en la central Grandstream (vía API) como en la BD local
- **Flujo de actualización** en 6 fases: Validación → Conexión → Obtener ID → Preparar datos → Enviar a API → Verificar y guardar
- **Actualización masiva de IPs**: Consulta `listAccount` y actualiza todas las extensiones
- **Desvíos de llamada (Call Forwarding)**: CFU (incondicional), CFB (ocupado), CFN (no responde)
  - Cada desvío se envía individualmente con `applyChanges` + pausa 500ms (requerido por la PBX)
  - Destinos: extensión, cola o número personalizado
  - Soporta 5 perfiles horarios: siempre, oficina, fuera de oficina, feriados, fines de semana
- **Permisos por nivel**: Internal → Local → National → International

### 4.4 Estadísticas de Colas (Queue KPIs)
- **Dashboard de Contact Center** con 5 tarjetas KPI principales:
  - Volumen total, Atendidas (%), Tasa de Abandono (%), Espera Promedio, Agentes Activos
- **KPIs por franja horaria** (08:00-20:00): ideal para dimensionamiento de turnos
- **KPIs por cola**: Métricas independientes para cada número de cola
- **Rendimiento por agente**: llamadas atendidas, tasa de atención, tiempo promedio
- **3 gráficos interactivos** (Chart.js): Volumen por hora (línea), Atendidas vs Abandonadas (barras), Tiempo de espera (área con umbrales de color)
- **Sistema de alertas automáticas** basado en umbrales:
  - Abandono > 20% → alerta roja (danger)
  - Abandono > 15% → alerta amarilla (warning)
  - Espera > 60s → alerta roja
  - Espera > 30s → alerta amarilla
- **Sincronización desde `queueapi`** con deduplicación inteligente (~22% de duplicados en la API)
- Filtros por cola específica y rango de fechas

### 4.5 Gestión de Usuarios y Permisos
- **3 roles jerárquicos**: Admin > Supervisor > Usuario
- **8 permisos booleanos granulares**:

| Permiso | Capacidad |
|---|---|
| `can_sync_calls` | Sincronizar CDRs desde la central |
| `can_edit_extensions` | Modificar extensiones y desvíos |
| `can_update_ips` | Actualizar IPs de extensiones |
| `can_edit_rates` | Modificar tarifas de facturación |
| `can_manage_pbx` | Gestionar conexiones PBX |
| `can_export_pdf` | Descargar reportes PDF |
| `can_export_excel` | Descargar reportes Excel |
| `can_view_charts` | Acceder a gráficos y KPIs de colas |

- **Bypass administrativo**: Los admin siempre tienen todos los permisos (`hasPermission()` → `true`)
- **Control de acceso por central**: Tabla pivot `pbx_connection_user` define qué centrales ve cada usuario
- **Protección contra auto-eliminación**: No se puede eliminar el último admin ni a sí mismo
- **Gestión dual**: CRUD tradicional + API REST para modal interactivo en la página de centrales

### 4.6 Configuración de Tarifas
- **3 tarifas editables** almacenadas en tabla `settings` (clave-valor):
  - `price_mobile`: Precio/minuto celular (default: $80 CLP)
  - `price_national`: Precio/minuto fijo nacional (default: $40 CLP)
  - `price_international`: Precio/minuto internacional (default: $500 CLP)
- **Cache estática** en el modelo `Call` para evitar consultas repetidas a la BD
- Permisos: Solo usuarios con `can_edit_rates` pueden modificar

---

## 5. Estructura de Archivos del Proyecto

```
app/
├── Console/
│   └── Commands/
│       ├── Concerns/
│       │   └── ConfiguresPbx.php        → Trait para configurar PBX en comandos CLI
│       ├── ImportarExtensiones.php       → Artisan: extensions:import
│       ├── SyncCalls.php                → Artisan: calls:sync
│       ├── SyncQueueStats.php           → Artisan: sync:queue-stats
│       └── TestApiCommands.php          → Artisan: api:test (debugging, ~4200 líneas)
├── Exports/
│   └── CallsExport.php                 → Export Excel (FromQuery + streaming)
├── Http/
│   ├── Controllers/
│   │   ├── Concerns/
│   │   │   └── ProcessesCdr.php         → Trait: consolidación de segmentos CDR
│   │   ├── AuthController.php           → Login/logout personalizado
│   │   ├── CdrController.php            → Dashboard principal, sync, export
│   │   ├── EstadoCentral.php            → Estado/uptime de la central
│   │   ├── ExtensionController.php      → CRUD extensiones + call forwarding
│   │   ├── IPController.php             → Vista de IPs en tiempo real
│   │   ├── PbxConnectionController.php  → CRUD centrales + sincronización
│   │   ├── ProfileController.php        → Perfil de usuario (Breeze)
│   │   ├── SettingController.php        → Gestión de tarifas
│   │   ├── StatsController.php          → KPIs de colas
│   │   └── UserController.php           → CRUD usuarios + API JSON
│   ├── Middleware/
│   │   ├── AdminMiddleware.php          → Bloquea no-admin (alias: admin)
│   │   └── CheckPbxSelected.php         → Requiere central seleccionada (alias: pbx.selected)
│   └── Requests/
│       └── ProfileUpdateRequest.php     → Validación de perfil
├── Jobs/
│   └── SyncPbxDataJob.php              → Job background (1h timeout, 1 intento)
├── Models/
│   ├── Call.php                         → CDR con cálculo de costos chilenos
│   ├── Extension.php                    → Extensiones/anexos PBX
│   ├── PbxConnection.php               → Centrales PBX (modelo central multi-tenant)
│   ├── QueueCallDetail.php              → Detalles de llamadas de colas
│   ├── Setting.php                      → Configuración clave-valor (tarifas)
│   └── User.php                         → Usuarios con roles y 8 permisos booleanos
├── Providers/
│   └── AppServiceProvider.php           → Binding dinámico de GrandstreamService
├── Services/
│   ├── CallBillingAnalyzer.php          → Clasificador de llamadas facturables (5 criterios)
│   └── GrandstreamService.php           → Cliente API Grandstream (challenge/login/cookie)
├── Traits/
│   └── GrandstreamTrait.php             → Wrapper del servicio para controladores
└── View/Components/
    ├── AppLayout.php                    → Layout autenticado
    └── GuestLayout.php                  → Layout invitados
```

---

## 6. Flujo de Usuario Principal

```
┌──────────┐     ┌───────────────┐     ┌──────────────────────┐
│  LOGIN   │────►│  SELECCIÓN    │────►│  DASHBOARD CDR       │
│          │     │  DE CENTRAL   │     │  (Vista principal)   │
│ name +   │     │  PBX          │     │  - KPIs              │
│ password │     │               │     │  - Tabla de llamadas │
└──────────┘     │  Grid de      │     │  - Filtros           │
                 │  cards con    │     │  - Sync / Export     │
                 │  estado       │     └──────────┬───────────┘
                 └───────────────┘                │
                                          ┌───────┼───────┐
                                          ▼       ▼       ▼
                                     Gráficos  Anexos  Colas/KPIs
                                     (Chart.js)(Config)(Contact Center)
```

### Secuencia de autenticación:
1. `POST /login` → `AuthController@login` (rate limited: 10/min)
2. Redirige a `GET /pbx` → Lista centrales disponibles según permisos del usuario
3. `GET /pbx/select/{id}` → Verifica acceso, guarda en sesión, redirige según estado
4. Middleware `pbx.selected` permite acceso al resto de la app
5. Global Scopes filtran automáticamente todos los datos por `pbx_connection_id`

---

## 7. Modelo de Datos Relacional

```
                        ┌─────────────────┐
                        │  PbxConnection  │
                        │─────────────────│
                        │ id              │
                        │ name            │
                        │ ip              │
                        │ port            │
                        │ username        │
                        │ password (enc)  │
                        │ status          │
                        │ last_sync_at    │
                        └───────┬─────────┘
                           ╱    │    ╲
                     1:N  ╱   1:N     ╲  N:M
                         ╱      │      ╲
               ┌─────────┐  ┌───┴───┐  ┌─────────────────┐
               │  Call   │  │Exten- │  │pbx_connection_  │
               │──────── │  │sion   │  │user (pivot)     │
               │unique_id│  │───────│  │─────────────────│
               │source   │  │exten  │  │user_id    (FK)  │
               │dest     │  │name   │  │pbx_conn_id(FK)  │
               │billsec  │  │IP     │  └────────┬────────┘
               │cost(*)  │  │perms  │           │
               └─────────┘  │DND    │      ┌────┴────┐
                            └────── ┘      │  User   │
                                           │──────── │
               ┌───────────────┐           │ name    │
               │QueueCallDetail│           │ role    │
               │───────────────│           │ can_*   │
               │queue          │           └─────────┘
               │agent          │
               │wait_time      │     ┌──────────┐
               │talk_time      │     │ Setting  │
               │connected      │     │──────────│
               └───────────────┘     │ key      │
                                     │ value    │
                                     └──────────┘

(*) cost es un Accessor calculado, no columna en BD
```

---

## 8. Seguridad

| Capa | Mecanismo | Detalle |
|---|---|---|
| Autenticación | Rate limiting | `throttle:10,1` — 10 intentos/minuto |
| Credenciales PBX | Encriptación at-rest | Cast `encrypted` en `PbxConnection.password` |
| Secretos SIP | Ocultación | `$hidden` en serialización JSON |
| Multi-tenant | Global Scopes | Filtro automático `WHERE pbx_connection_id = X` |
| Autorización | Middleware + permisos | `AdminMiddleware` + `hasPermission()` en controladores |
| Acceso a centrales | Tabla pivot | `pbx_connection_user` define acceso; admin bypasses |
| CSRF | Token meta tag | Incluido en layout, headers Axios y Fetch |
| SSL | Configurable | `verify_ssl` por conexión PBX |
| Sesión | Regeneración | Token regenerado en login/logout |

---

## 9. Optimizaciones de Rendimiento

| Área | Técnica | Impacto |
|---|---|---|
| Costos CDR | Cache estática `Call::$cachedPrices` | Evita queries repetidas a `settings` |
| Totales Dashboard | Agregación SQL con `CASE WHEN` | Calcula costos en BD, no en PHP |
| KPIs de Colas | `DB::raw()` + `GROUP BY` | Agregaciones en SQL, no loops PHP |
| Export Excel | `FromQuery` streaming | Cursor DB sin cargar todo en memoria |
| Import Extensiones | Chunks de 50 + `gc_collect_cycles()` | Controla consumo de memoria |
| Sync CDR | `updateOrCreate` idempotente + overlap 1h | Evita duplicados sin perder datos |
| API Grandstream | Reutilización de cookie + auto-reconnect | Minimiza autenticaciones |
| PDF | Límite 500 registros + memory_limit 1024M | Previene exhaustión de memoria |
| Paginación | 50 registros/página consistente | Balance UX vs rendimiento |
| Deduplicación Queue | Clave compuesta in-batch + DB exists check | Filtra ~22% duplicados de API |
