# Índice de Funciones por Archivo

> Referencia rápida de todas las funciones/métodos del proyecto organizadas por archivo. Incluye visibilidad, tipo de retorno y descripción funcional. **~160+ funciones documentadas.**

---

## Modelos

### app/Models/Call.php — Motor de CDR y Facturación
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `booted()` | protected static | void | Registra Global Scope `current_pbx` (filtra por `session('active_pbx_id')`) + evento `creating` (auto-asigna `pbx_connection_id`) |
| `pbxConnection()` | public | BelongsTo | FK → `pbx_connections` |
| `getPrices()` | protected static | array | Cache estática de tarifas: `['price_mobile' => 80, ...]`. Lee de BD una sola vez por request |
| `clearPricesCache()` | public static | void | Invalida cache de tarifas. Llamado por `SettingController@update` |
| `getCostAttribute()` | public | int | **Accessor `$call->cost`**: clasifica destino por regex chileno → aplica tarifa → `ceil(billsec/60) × tarifa`. Retorna 0 si `disposition ≠ ANSWERED` |
| `getCallTypeAttribute()` | public | string | **Accessor `$call->call_type`**: Interna/Local/Celular/Nacional/Internacional según regex sobre `$this->destination` |

### app/Models/Extension.php — Extensiones SIP
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `booted()` | protected static | void | Global Scope `current_pbx` + evento `creating` (auto pbx_connection_id) |
| `pbxConnection()` | public | BelongsTo | FK → `pbx_connections` |

### app/Models/PbxConnection.php — Centrales PBX
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `isReady()` | public | bool | `status === 'ready'` |
| `isSyncing()` | public | bool | `status === 'syncing'` |
| `isPending()` | public | bool | `status === 'pending'` |
| `getStatusDisplayName()` | public | string | Mapa: pending→Pendiente, syncing→Sincronizando, ready→Listo, error→Error |
| `calls()` | public | HasMany | Todas las llamadas de esta central |
| `extensions()` | public | HasMany | Todas las extensiones de esta central |
| `users()` | public | BelongsToMany | Usuarios con acceso → pivot `pbx_connection_user` |

### app/Models/QueueCallDetail.php — Estadísticas de Colas
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `pbxConnection()` | public | BelongsTo | FK → `pbx_connections` |
| `booted()` | protected static | void | Global Scope + creating event |
| `scopeForQueue($q, $queue)` | public | Builder | Filtra por número de cola |
| `scopeConnected($q)` | public | Builder | Solo llamadas conectadas (`connected = true`) |
| `scopeForAgent($q, $agent)` | public | Builder | Filtra por extensión del agente |
| `scopeBetweenDates($q, $from, $to)` | public | Builder | Filtra por rango de `call_time` |

### app/Models/Setting.php — Configuración Key-Value
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `get($key, $default)` | public static | mixed | Busca por key, retorna value o default |
| `allAsArray()` | public static | array | Retorna `[key => value, ...]` de todas las settings |

### app/Models/User.php — Usuarios y Permisos
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `isAdmin()` | public | bool | `role === 'admin'` |
| `isUser()` | public | bool | `role === 'user'` |
| `hasPermission($perm)` | public | bool | Admin → siempre true. User → evalúa `$this->$perm` |
| `canSyncCalls()` | public | bool | `hasPermission('can_sync_calls')` |
| `canSyncExtensions()` | public | bool | `hasPermission('can_sync_extensions')` |
| `canSyncQueues()` | public | bool | `hasPermission('can_sync_queues')` |
| `canEditExtensions()` | public | bool | `hasPermission('can_edit_extensions')` |
| `canUpdateIps()` | public | bool | `hasPermission('can_update_ips')` |
| `canEditRates()` | public | bool | `hasPermission('can_edit_rates')` |
| `canManagePbx()` | public | bool | `hasPermission('can_manage_pbx')` |
| `canExportPdf()` | public | bool | `hasPermission('can_export_pdf')` |
| `canExportExcel()` | public | bool | `hasPermission('can_export_excel')` |
| `canViewCharts()` | public | bool | `hasPermission('can_view_charts')` |
| `canViewExtensions()` | public | bool | `hasPermission('can_view_extensions')` |
| `canViewRates()` | public | bool | `hasPermission('can_view_rates')` |
| `getRoleDisplayName()` | public | string | admin→Administrador, user→Usuario |
| `pbxConnections()` | public | BelongsToMany | Centrales asignadas → pivot `pbx_connection_user` |
| `casts()` | protected | array | password→hashed, 12 permisos→boolean |

---

## Controladores

### app/Http/Controllers/CdrController.php — Dashboard y CDR
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Dashboard: query paginada con filtros (fecha, anexo, tipo), stats de costos |
| `syncCDRs()` | public | Redirect | Sincronización incremental CDR (último mes). Requiere `canSyncCalls` |
| `descargarPDF()` | public | Download | Genera PDF con DomPDF. Requiere `canExportPdf` |
| `exportarExcel()` | public | Download | Genera Excel via CallsExport. Requiere `canExportExcel` |
| `showCharts()` | public | View | Vista de gráficos Chart.js. Requiere `canViewCharts` |
| `buildCallQuery($request)` | private | Builder | Construye query con filtros: fecha, anexo, tipo_llamada, ordenamiento |
| `validateSort($sort)` | private | string | Whitelist: start_time, source, destination, billsec, disposition, type, cost |
| `applySorting($query, $sort, $dir)` | private | Builder | Aplica ORDER BY, con SQL custom para type y cost |
| `getTypeSortSql()` | private | string | CASE WHEN con regex REGEXP para ordenar por tipo de llamada |
| `getCostSortSql()` | private | string | CASE WHEN con regex REGEXP para ordenar por costo calculado |
| `processCdrPackets($calls)` | private | array | Procesa paquetes CDR de la API → consolida segmentos → updateOrCreate |

### app/Http/Controllers/ExtensionController.php — Gestión de Extensiones
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `syncExtensions()` | public | JsonResponse | Sincroniza extensiones desde PBX via AJAX con timeout extendido. Requiere `canSyncCalls` |
| `checkSyncStatus()` | public | JsonResponse | Endpoint AJAX para polling del estado de sincronización de extensiones |
| `index()` | public | View | Listado de extensiones con paginación |
| `update($request)` | public | Redirect | Actualización bidireccional BD↔PBX en 6 fases. Requiere `canEditExtensions` |
| `updateName($request)` | public | Redirect | Actualiza solo fullname en BD (sin API) |
| `updateIps($request)` | public | Redirect | Actualiza IPs de extensiones. Requiere `canUpdateIps` |
| `getCallForwarding($request)` | public | JsonResponse | GET config de desvío desde PBX via `getSIPAccount` |
| `updateCallForwarding($request)` | public | JsonResponse | POST desvío → `updateSIPAccount` + `applyChanges` (500ms pause) |
| `parsePermissionFromApi($raw)` | private | string | Convierte permiso API → formato BD (Internal/Local/National/International) |

### app/Http/Controllers/PbxConnectionController.php — CRUD Centrales
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Lista centrales. Admin→todas, User→filtrado por pivot |
| `store($request)` | public | Redirect | Crea central + test conexión + estado pending/ready |
| `update($request, $pbx)` | public | Redirect | Edita central. Solo admin + dueño |
| `destroy($pbx)` | public | Redirect | Elimina central + CASCADE (calls, extensions, queue_details) |
| `select($pbx)` | public | Redirect | Selecciona central activa → `session(['active_pbx_id'])` |
| `setup($pbx)` | public | View | Wizard de configuración post-creación |
| `checkSyncStatus($pbx)` | public | JsonResponse | Polling: lee Cache de progreso de SyncPbxDataJob |
| `syncExtensions($pbx)` | public | JsonResponse | Despacha SyncPbxDataJob (solo extensiones) |
| `syncCalls($pbx)` | public | JsonResponse | Despacha SyncPbxDataJob (solo llamadas) |
| `finishSync($pbx)` | public | JsonResponse | Marca status→ready, limpia Cache |
| `disconnect()` | public | Redirect | Limpia `session('active_pbx_id')` |
| `parseExtensionPermission($raw)` | private | string | Traduce ACL: *international*→International, etc. |
| `collectCdrSegments($node)` | private | array | Extracción recursiva de segmentos CDR |
| `consolidateCallData($segs)` | private | array | Merge de segmentos en un registro |
| `esAnexo($num)` | private | bool | Regex `/^\d{3,4}$/` |
| `esExterno($num)` | private | bool | `strlen>4 \|\| +prefix \|\| 9prefix` |
| `validatePbx($data)` | private | array | Validación de campos de central |
| `setActivePbx($pbx)` | private | void | Establece central en sesión |
| `setPbxConnection($pbx)` | private | void | Configura GrandstreamService con central |
| `determineCallType($call)` | private | string | Tipo de llamada por regex de destino |

### app/Http/Controllers/StatsController.php — KPIs de Colas
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Vista de KPIs con filtros de fecha/cola |
| `apiKpis($request)` | public | JsonResponse | Retorna 7 datasets: kpis_hora, agentes, colas, totales, agentes_cola, colas_disp, intervalo |
| `sincronizarColas()` | public | JsonResponse | Ejecuta `Artisan::call('sync:queue-stats')` |
| `obtenerRendimientoAgentes($q)` | private | array | Agrupa por agente: total, conectadas, tasa, avg_espera, avg_conversación |
| `obtenerColasDisponibles($q)` | private | array | Lista colas únicas del período |
| `calcularKpisPorHora($q)` | private | array | Agrupa por hora (0-23): total, conectadas, tasa, avg_espera |
| `extraerAgente($agent)` | private | ?string | Extrae número de `Local/XXXX@...` via regex |
| `calcularTotales($q)` | private | array | Sumarios: total, conectadas, %tasa, avg_espera, avg_conversación |
| `calcularKpisPorCola($q)` | private | array | Agrupa por cola: mismo desglose que agentes |
| `extraerCola($queue)` | private | ?string | Normaliza número de cola |
| `obtenerAgentesPorCola($q)` | private | array | Matriz cola×agente con contadores |

### app/Http/Controllers/UserController.php — CRUD Usuarios
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Listado paginado de usuarios |
| `create()` | public | View | Formulario de creación con centrales disponibles |
| `store($request)` | public | Redirect | Crea usuario + sync pivot de centrales |
| `edit($user)` | public | View/Redirect | Formulario de edición. Protege admin contra auto-degradación |
| `update($request, $user)` | public | Redirect | Actualiza usuario + sync pivot |
| `destroy($user)` | public | Redirect | Elimina. Protege último admin y auto-eliminación |
| `apiIndex()` | public | JsonResponse | Lista JSON con `pbxConnections` eager-loaded |
| `apiStore($request)` | public | JsonResponse | Crea + attach centrales (para modal Alpine.js) |
| `apiUpdate($request, $user)` | public | JsonResponse | Actualiza + sync pivot |
| `apiDestroy($user)` | public | JsonResponse | Elimina con mismas protecciones que web |

### app/Http/Controllers/SettingController.php — Tarifas
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Muestra tarifas actuales |
| `update($request)` | public | Redirect | Guarda tarifas + `Call::clearPricesCache()` |

### app/Http/Controllers/AuthController.php — Autenticación Custom
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `showLogin()` | public | View | Formulario login (redirige si ya autenticado) |
| `login($request)` | public | Redirect | Auth::attempt + regenerate session |
| `logout($request)` | public | Redirect | Auth::logout + invalidate + regenerate token |

### app/Http/Controllers/EstadoCentral.php — Estado de la PBX
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Vista del estado de la central |
| `getSystemData()` | public | array | Llama `getSystemStatus` API → retorna datos del sistema |

### app/Http/Controllers/IPController.php — IPs de Dispositivos SIP
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `index()` | public | View | Lista extensiones con IPs de registro SIP |
| `fetchLiveAccounts()` | private | array | Llama `listAccount` API → obtiene registros SIP activos |
| `parseAddress($contact)` | private | string | Extrae IP de string de contacto SIP |

### app/Http/Controllers/ProfileController.php — Perfil de Usuario (Breeze)
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `edit($request)` | public | View | Formulario de perfil |
| `update(ProfileUpdateRequest)` | public | Redirect | Actualiza nombre/email. Si email cambió → resetea verificación |
| `destroy($request)` | public | Redirect | Auto-elimina cuenta previa confirmación de password |

### app/Http/Controllers/Concerns/ProcessesCdr.php — Trait CDR Compartido
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `collectCdrSegments($node)` | protected | array | Extracción recursiva de segmentos CDR (reuso entre controllers) |
| `consolidateCdrSegments($segs)` | protected | array | Merge de segmentos en registro único |
| `isExtension($num)` | protected | bool | ¿Es extensión interna? (3-4 dígitos) |
| `isExternalNumber($num)` | protected | bool | ¿Es número externo? (>4 dígitos, +, 9) |

---

## Servicios

### app/Services/GrandstreamService.php — Cliente API PBX
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `__construct($connection?)` | public | — | Opcionalmente recibe PbxConnection para auto-configurar |
| `setConnectionFromModel($conn)` | public | self | Configura servicio desde modelo. Resetea cookie anterior |
| `getPbxConnectionId()` | public | ?int | ID de la central configurada |
| `isConfigured()` | public | bool | ¿Tiene central configurada con host/port/user/pass? |
| `connectApi($action, $params, $timeout)` | public | array | **Gateway principal** a API PBX. Auto-login + retry en cookie expirada |
| `testConnection()` | public | bool | Health check via `getSystemStatus` |
| `authenticate()` | private | ?string | Challenge/login → retorna cookie de sesión o null |

### app/Services/CallBillingAnalyzer.php — Análisis de Facturación
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `getRealExtension($channel)` | public | ?string | Extrae extensión de canal SIP: `PJSIP/1001-xxx` → `1001` |
| `isInternalExtension($ext)` | public | bool | Numérico + ≤4 dígitos |
| `isExternalDestination($dest)` | public | bool | ≥7 dígitos (limpiados) |
| `isInboundFromTrunk($data)` | public | bool | Detecta entrante: userfield, src_trunk, trunk patterns |
| `isOutboundToTrunk($data)` | public | bool | Detecta saliente: userfield, dst_trunk, trunk patterns |
| `isTrunkIdentifier($id)` | public | bool | Compara contra `$trunkPatterns` |
| `isInternalCall($data)` | public | bool | Ambos extremos internos, sin trunks |
| `isBillable($data)` | public | bool | **Test de 5 criterios**: ANSWERED + NO inbound + NO internal + SÍ outbound + origen interno |
| `analyze($data)` | public | array | Análisis completo: canales, extensiones, tipo, facturabilidad |

---

## Traits

### app/Traits/GrandstreamTrait.php — Wrapper del Servicio
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `getGrandstreamService()` | protected | GrandstreamService | Lazy loading desde container. Cachea instancia |
| `connectApi($action, $params, $timeout)` | protected | array | Proxy → `GrandstreamService::connectApi()` |
| `testConnection()` | protected | bool | Proxy → `GrandstreamService::testConnection()` |
| `isPbxConfigured()` | protected | bool | Proxy → `GrandstreamService::isConfigured()` |
| `getActivePbxId()` | protected | ?int | Proxy → `GrandstreamService::getPbxConnectionId()` |
| `configurePbx($pbxId)` | protected | bool | Configura servicio con central por ID. Resetea instancia |

---

## Comandos Artisan

### app/Console/Commands/SyncCalls.php — `calls:sync`
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `handle()` | public | int | Entry point: memory 1024M → setup PBX → syncCallsByMonth |
| `syncCallsByMonth($year)` | private | void | Itera ene→ahora, 1s pausa entre meses |
| `processMonth($start, $end)` | private | void | API call `cdrapi` (timeout 120s) → processCdrPackets |
| `processCdrPackets($calls)` | private | void | Collect → filter → consolidate → updateOrCreate |
| `collectSegments($node)` | private | array | Recorrido recursivo del árbol CDR (sub_cdr/main_cdr) |
| `consolidateCall($segments)` | private | array | Merge: earliest start_time, sum duration/billsec, disposition logic |
| `esAnexo($num)` | private | bool | `/^\d{3,4}$/` |
| `esExterno($num)` | private | bool | `strlen>4 \|\| + \|\| 9` |

### app/Console/Commands/ImportarExtensiones.php — `extensions:import`
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `handle()` | public | int | Entry point: memory 1028M → setup → quick/complete mode |
| `syncAll()` | private | int | Chunks de 50 + gc_collect_cycles. Stats: nuevos/actualizados/sin_cambios |
| `fetchUserList()` | private | array | API `listUser` (60s). Fallback para formatos API variables |
| `processExtension($userData)` | private | string | Build → find → hasChanges? → update/create → retorna categoría |
| `buildExtensionData($userData)` | private | array | Campos base + SIP details en modo completo (`getSIPAccount`, 10ms pause) |
| `parsePermission($raw)` | private | string | international→International, national→National, local→Local, *→Internal |
| `hasChanges($existing, $new)` | private | bool | Compara 5 campos + secret en modo completo |
| `syncSingle($target)` | private | int | API `getUser` → fuerza modo completo → updateOrCreate directo |

### app/Console/Commands/SyncQueueStats.php — `sync:queue-stats`
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `handle()` | public | int | Calcula fechas → carga PBX → syncPbx → resumen |
| `syncPbx($pbx, $start, $end)` | private | void | Session → verify → getQueues → optional force delete → syncQueue |
| `getQueues()` | private | array | API `listQueue` → extrae extensiones de cola |
| `syncQueue($pbx, $queue, $start, $end)` | private | void | API `queueapi` (120s) → dedup 3 capas → insert |

### app/Console/Commands/TestApiCommands.php — `api:test` (~4220 líneas)
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `handle()` | public | int | Switch por `--action` → delega a método específico |
| `testListExtensionGroup()` | private | int | Tabla de grupos de extensiones |
| `testListQueue()` | private | int | Tabla de colas con agentes y estrategia |
| `testListOutboundRoute()` | private | int | Tabla de rutas salientes |
| `testListInboundRoute()` | private | int | Tabla de rutas entrantes (resuelve trunks) |
| `testCdrApi()` | private | int | CDRs con filtros avanzados: caller, dates, type, status |
| `testQueueApi()` | private | int | Estadísticas de colas: overview/calldetail |
| `testGetSIPAccount()` | private | int | Detalle completo de extensión SIP |
| `testUpdateSIPAccount()` | private | int | Actualiza SIP interactivamente (cfb, cfn, cfu, presence) |
| `testListDepartment()` | private | int | Tabla de departamentos |
| `testListBridgedChannels()` | private | int | Canales activos (llamadas en curso) |
| `testGetInboundRoute()` | private | int | Detalle de ruta entrante por ID |
| `testGetOutboundRoute()` | private | int | Detalle de ruta saliente por ID |
| `interactiveCdrApi()` | private | int | Cuestionario interactivo para buscar CDRs |
| `testKpiTurnos()` | private | int | KPIs de turnos por franja horaria |
| `exploreActionTypes()` | private | int | Análisis de action_type únicos en CDRs |
| `analyzeBilling()` | private | int | Usa CallBillingAnalyzer para auditar facturabilidad |
| *(+ auxiliares de filtrado y display)* | private | — | Formateo de tablas, filtros, colores de consola |

### app/Console/Commands/Concerns/ConfiguresPbx.php — Trait CLI
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `setupPbxConnection()` | protected | ?int | --pbx → configurePbx; sin --pbx → isPbxConfigured o listado |
| `showAvailablePbxConnections()` | protected | void | Tabla con centrales disponibles + hint de uso |
| `verifyConnection()` | protected | bool | testConnection + mensaje de error |

---

## Jobs

### app/Jobs/SyncPbxDataJob.php — Sincronización en Background
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `__construct($pbxId, $syncExt, $syncCalls, $year, $userName)` | public | — | Configura job con parámetros de sincronización |
| `handle()` | public | void | Lock → extensions:import --quick → calls:sync → unlock. Progreso via Cache |
| `failed($exception)` | public | void | Guarda error en Cache (TTL 300s) → limpia locks |

---

## Exports

### app/Exports/CallsExport.php — Excel Export (Maatwebsite)
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `__construct($filtros)` | public | — | Recibe array de filtros del controller |
| `query()` | public | Builder | Query con filtros: fecha, anexo, tipo (REGEXP). FromQuery → streaming |
| `headings()` | public | array | 7 columnas: Fecha, Origen, Destino, Tipo, Duración, Costo, Estado |
| `map($call)` | public | array | Transforma modelo a fila usando accessors (call_type, cost) |
| `styles($sheet)` | public | array | Fila 1 en negrita (encabezados) |

---

## Middleware

### app/Http/Middleware/AdminMiddleware.php — Alias: `admin`
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `handle($request, $next)` | public | Response | ¿auth + admin? → pass. JSON→403. Web→redirect /dashboard |

### app/Http/Middleware/CheckPbxSelected.php — Alias: `pbx.selected`
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `handle($request, $next)` | public | Response | ¿session('active_pbx_id')? → pass. No → redirect /pbx |
| `isExcludedRoute($request)` | protected | bool | Compara con lista de exclusiones (soporta wildcards `pbx.*`) |

---

## Providers

### app/Providers/AppServiceProvider.php
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `register()` | public | void | Binding dinámico de `GrandstreamService`: lee session → PbxConnection → setConnectionFromModel |
| `boot()` | public | void | (Vacío — disponible para futura configuración) |

---

## Requests

### app/Http/Requests/ProfileUpdateRequest.php
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `rules()` | public | array | name: required, max:255. email: required, unique (ignora self) |

---

## View Components

### app/View/Components/AppLayout.php
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `render()` | public | View | Renderiza `layouts.app` (layout autenticado con nav + sidebar) |

### app/View/Components/GuestLayout.php
| Función | Vis. | Retorno | Descripción |
|---|---|---|---|
| `render()` | public | View | Renderiza `layouts.guest` (layout minimalista para login) |

---

## Seeders

### database/seeders/DatabaseSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Ejecuta en orden: PbxConnectionSeeder → SettingSeeder → UserSeeder |

### database/seeders/PbxConnectionSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Crea Central Principal: IP 10.36.1.10, puerto 7110, user cdrapi, pass 123api |

### database/seeders/SettingSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Crea 3 tarifas: price_mobile=$80, price_national=$40, price_international=$500 |

### database/seeders/UserSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Crea admin + user desde `config('services.admins/users')` (env variables) |
