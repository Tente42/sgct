# Índice de Funciones por Archivo

Referencia rápida de todas las funciones/métodos del proyecto organizadas por archivo.

---

## Modelos

### app/Models/Call.php
| Función | Visibilidad | Tipo | Línea de descripción |
|---|---|---|---|
| `booted()` | protected static | void | Registra Global Scope `current_pbx` y evento `creating` |
| `pbxConnection()` | public | BelongsTo | Relación con PbxConnection |
| `getPrices()` | protected static | array | Obtiene tarifas cacheadas desde tabla settings |
| `clearPricesCache()` | public static | void | Limpia cache estática de tarifas |
| `getCostAttribute()` | public | int | Accessor: calcula costo de llamada por regex de destino chileno |
| `getCallTypeAttribute()` | public | string | Accessor: clasifica tipo de llamada (Interna/Local/Celular/Nacional/Internacional) |

### app/Models/Extension.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `booted()` | protected static | void | Global Scope + evento creating |
| `pbxConnection()` | public | BelongsTo |

### app/Models/PbxConnection.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `isReady()` | public | bool |
| `isSyncing()` | public | bool |
| `isPending()` | public | bool |
| `getStatusDisplayName()` | public | string |
| `calls()` | public | HasMany |
| `extensions()` | public | HasMany |
| `users()` | public | BelongsToMany |

### app/Models/QueueCallDetail.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `pbxConnection()` | public | BelongsTo |
| `booted()` | protected static | void |
| `scopeForQueue()` | public | Builder |
| `scopeConnected()` | public | Builder |
| `scopeForAgent()` | public | Builder |
| `scopeBetweenDates()` | public | Builder |

### app/Models/Setting.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `get()` | public static | mixed |
| `allAsArray()` | public static | array |

### app/Models/User.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `isAdmin()` | public | bool |
| `isUser()` | public | bool |
| `hasPermission()` | public | bool |
| `canSyncCalls()` | public | bool |
| `canEditExtensions()` | public | bool |
| `canUpdateIps()` | public | bool |
| `canEditRates()` | public | bool |
| `canManagePbx()` | public | bool |
| `canExportPdf()` | public | bool |
| `canExportExcel()` | public | bool |
| `canViewCharts()` | public | bool |
| `getRoleDisplayName()` | public | string |
| `pbxConnections()` | public | BelongsToMany |
| `casts()` | protected | array |

---

## Controladores

### app/Http/Controllers/CdrController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `syncCDRs()` | public | Redirect |
| `descargarPDF()` | public | Download |
| `exportarExcel()` | public | Download |
| `showCharts()` | public | View |
| `buildCallQuery()` | private | Builder |
| `validateSort()` | private | string |
| `applySorting()` | private | Builder |
| `getTypeSortSql()` | private | string |
| `getCostSortSql()` | private | string |
| `processCdrPackets()` | private | array |

### app/Http/Controllers/ExtensionController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `update()` | public | Redirect |
| `updateName()` | public | Redirect |
| `updateIps()` | public | Redirect |
| `index()` | public | View |
| `getCallForwarding()` | public | JsonResponse |
| `updateCallForwarding()` | public | JsonResponse |

### app/Http/Controllers/PbxConnectionController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `store()` | public | Redirect |
| `update()` | public | Redirect |
| `destroy()` | public | Redirect |
| `select()` | public | Redirect |
| `setup()` | public | View |
| `checkSyncStatus()` | public | JsonResponse |
| `syncExtensions()` | public | JsonResponse |
| `syncCalls()` | public | JsonResponse |
| `finishSync()` | public | JsonResponse |
| `disconnect()` | public | Redirect |
| `parseExtensionPermission()` | private | string |
| `collectCdrSegments()` | private | array |
| `consolidateCallData()` | private | array |
| `esAnexo()` | private | bool |
| `esExterno()` | private | bool |
| `validatePbx()` | private | array |
| `setActivePbx()` | private | void |
| `setPbxConnection()` | private | void |
| `determineCallType()` | private | string |

### app/Http/Controllers/StatsController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `apiKpis()` | public | JsonResponse |
| `sincronizarColas()` | public | JsonResponse |
| `obtenerRendimientoAgentes()` | private | array |
| `obtenerColasDisponibles()` | private | array |
| `calcularKpisPorHora()` | private | array |
| `extraerAgente()` | private | ?string |
| `calcularTotales()` | private | array |
| `calcularKpisPorCola()` | private | array |
| `extraerCola()` | private | ?string |
| `obtenerAgentesPorCola()` | private | array |

### app/Http/Controllers/UserController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `create()` | public | View |
| `store()` | public | Redirect |
| `edit()` | public | View/Redirect |
| `update()` | public | Redirect |
| `destroy()` | public | Redirect |
| `apiIndex()` | public | JsonResponse |
| `apiStore()` | public | JsonResponse |
| `apiUpdate()` | public | JsonResponse |
| `apiDestroy()` | public | JsonResponse |

### app/Http/Controllers/SettingController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `update()` | public | Redirect |

### app/Http/Controllers/AuthController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `showLogin()` | public | View |
| `login()` | public | Redirect |
| `logout()` | public | Redirect |

### app/Http/Controllers/EstadoCentral.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `getSystemData()` | public | array |

### app/Http/Controllers/IPController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `index()` | public | View |
| `fetchLiveAccounts()` | private | array |
| `parseAddress()` | private | string |

### app/Http/Controllers/ProfileController.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `edit()` | public | View |
| `update()` | public | Redirect |
| `destroy()` | public | Redirect |

### app/Http/Controllers/Concerns/ProcessesCdr.php (Trait)
| Función | Visibilidad | Tipo |
|---|---|---|
| `collectCdrSegments()` | protected | array |
| `consolidateCdrSegments()` | protected | array |
| `isExtension()` | protected | bool |
| `isExternalNumber()` | protected | bool |

---

## Servicios

### app/Services/GrandstreamService.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `__construct()` | public | - |
| `setConnectionFromModel()` | public | self |
| `getPbxConnectionId()` | public | ?int |
| `isConfigured()` | public | bool |
| `connectApi()` | public | array |
| `testConnection()` | public | bool |
| `authenticate()` | private | ?string |

### app/Services/CallBillingAnalyzer.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `getRealExtension()` | public | ?string |
| `isInternalExtension()` | public | bool |
| `isExternalDestination()` | public | bool |
| `isInboundFromTrunk()` | public | bool |
| `isOutboundToTrunk()` | public | bool |
| `isTrunkIdentifier()` | public | bool |
| `isInternalCall()` | public | bool |
| `isBillable()` | public | bool |
| `analyze()` | public | array |

---

## Traits

### app/Traits/GrandstreamTrait.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `getGrandstreamService()` | protected | GrandstreamService |
| `connectApi()` | protected | array |
| `testConnection()` | protected | bool |
| `isPbxConfigured()` | protected | bool |
| `getActivePbxId()` | protected | ?int |
| `configurePbx()` | protected | bool |

---

## Comandos Artisan

### app/Console/Commands/SyncCalls.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `handle()` | public | int |
| `syncCallsByMonth()` | private | void |
| `processMonth()` | private | void |
| `processCdrPackets()` | private | void |
| `collectSegments()` | private | array |
| `consolidateCall()` | private | array |
| `esAnexo()` | private | bool |
| `esExterno()` | private | bool |

### app/Console/Commands/ImportarExtensiones.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `handle()` | public | int |
| `syncAll()` | private | int |
| `fetchUserList()` | private | array |
| `processExtension()` | private | string |
| `buildExtensionData()` | private | array |
| `parsePermission()` | private | string |
| `hasChanges()` | private | bool |
| `syncSingle()` | private | int |

### app/Console/Commands/SyncQueueStats.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `handle()` | public | int |
| `syncPbx()` | private | void |
| `getQueues()` | private | array |
| `syncQueue()` | private | void |

### app/Console/Commands/TestApiCommands.php (~4220 líneas)
| Función | Visibilidad | Tipo |
|---|---|---|
| `handle()` | public | int |
| `testListExtensionGroup()` | private | int |
| `testListQueue()` | private | int |
| `testListOutboundRoute()` | private | int |
| `testListInboundRoute()` | private | int |
| `testCdrApi()` | private | int |
| `testQueueApi()` | private | int |
| `testGetSIPAccount()` | private | int |
| `testUpdateSIPAccount()` | private | int |
| `testListDepartment()` | private | int |
| `testListBridgedChannels()` | private | int |
| `testGetInboundRoute()` | private | int |
| `testGetOutboundRoute()` | private | int |
| `interactiveCdrApi()` | private | int |
| `testKpiTurnos()` | private | int |
| `exploreActionTypes()` | private | int |
| `analyzeBilling()` | private | int |
| *(+ múltiples métodos auxiliares de filtrado y display)* | | |

### app/Console/Commands/Concerns/ConfiguresPbx.php (Trait)
| Función | Visibilidad | Tipo |
|---|---|---|
| `setupPbxConnection()` | protected | ?int |
| `showAvailablePbxConnections()` | protected | void |
| `verifyConnection()` | protected | bool |

---

## Jobs

### app/Jobs/SyncPbxDataJob.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `__construct()` | public | - |
| `handle()` | public | void |
| `failed()` | public | void |

---

## Exports

### app/Exports/CallsExport.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `__construct()` | public | - |
| `query()` | public | Builder |
| `headings()` | public | array |
| `map()` | public | array |
| `styles()` | public | array |

---

## Middleware

### app/Http/Middleware/AdminMiddleware.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `handle()` | public | Response |

### app/Http/Middleware/CheckPbxSelected.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `handle()` | public | Response |
| `isExcludedRoute()` | protected | bool |

---

## Providers

### app/Providers/AppServiceProvider.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `register()` | public | void |
| `boot()` | public | void |

---

## Requests

### app/Http/Requests/ProfileUpdateRequest.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `rules()` | public | array |

---

## View Components

### app/View/Components/AppLayout.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `render()` | public | View |

### app/View/Components/GuestLayout.php
| Función | Visibilidad | Tipo |
|---|---|---|
| `render()` | public | View |

---

## Seeders

### database/seeders/DatabaseSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Ejecuta PbxConnectionSeeder, SettingSeeder, UserSeeder |

### database/seeders/PbxConnectionSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Crea Central Principal (IP: 10.36.1.10, puerto 7110) |

### database/seeders/SettingSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Crea tarifas: celular=$80, nacional=$40, internacional=$500 |

### database/seeders/UserSeeder.php
| Función | Descripción |
|---|---|
| `run()` | Crea admin y user desde config/services.php |

---

**Total de funciones documentadas: ~150+**
