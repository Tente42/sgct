# Panel de Llamadas - Resumen General del Proyecto

## Descripción

**Panel de Llamadas** es una aplicación web desarrollada con **Laravel 11.47** y **PHP 8.2** que permite gestionar, monitorear y analizar las llamadas telefónicas de centrales PBX **Grandstream UCM**. El sistema se conecta a la API de Grandstream para sincronizar registros CDR (Call Detail Records), extensiones y estadísticas de colas.

## Stack Tecnológico

| Componente | Tecnología | Versión |
|---|---|---|
| Backend | Laravel Framework | 11.47.0 |
| PHP | PHP | 8.2.12 |
| Base de Datos | MySQL | - |
| Frontend | Blade + Alpine.js | 3.15.3 |
| CSS | Tailwind CSS | 3.4.19 |
| Autenticación | Laravel Breeze | 2.3.8 |
| PDF | DomPDF (barryvdh) | - |
| Excel | Maatwebsite Excel | - |

## Arquitectura Multi-Tenant

El sistema soporta **múltiples centrales PBX**. Cada usuario selecciona una central activa desde la sesión, y todos los modelos (`Call`, `Extension`, `QueueCallDetail`) aplican un **Global Scope** que filtra automáticamente por la central activa (`pbx_connection_id`).

## Módulos Principales

### 1. Gestión de Centrales PBX
- Crear, editar, eliminar conexiones a centrales Grandstream
- Sincronización de extensiones y llamadas desde la API
- Sistema de estados: `pending` → `syncing` → `ready` / `error`

### 2. Reportes de Llamadas (CDR)
- Dashboard con listado paginado de llamadas
- Filtros por fecha, anexo, tipo de llamada (interna/externa)
- Cálculo automático de costos basado en destino (Chile)
- Exportación a PDF y Excel

### 3. Gestión de Extensiones
- Sincronización desde la central Grandstream
- Edición de datos (nombre, permisos, DND, contraseña SIP)
- Actualización de IPs en tiempo real
- Configuración de desvíos de llamadas (Call Forwarding)

### 4. Estadísticas de Colas (Queue KPIs)
- KPIs por franja horaria (08:00-20:00)
- Rendimiento de agentes
- Tasa de abandono
- Sincronización desde `queueapi`

### 5. Gestión de Usuarios
- Roles: Admin, Supervisor, Usuario
- Permisos granulares: sincronizar, editar extensiones, exportar, etc.
- Control de acceso por central: cada usuario solo ve las centrales que el admin le asigne
- Los administradores ven todas las centrales automáticamente
- Gestión integrada en la página de centrales PBX (modal interactivo)

### 6. Tarifas
- Configuración de precios por minuto (celular, nacional, internacional)
- Cálculo dinámico de costos en cada llamada

## Estructura de Archivos

```
app/
├── Console/Commands/       → Comandos Artisan (sincronización, testing)
├── Exports/                → Exportaciones Excel
├── Http/
│   ├── Controllers/        → Controladores web
│   ├── Middleware/          → Admin y selección de PBX
│   └── Requests/           → Form Requests
├── Jobs/                   → Jobs en cola (sincronización background)
├── Models/                 → Modelos Eloquent (6 modelos)
├── Providers/              → Service Provider (binding GrandstreamService)
├── Services/               → Servicios (API Grandstream, Billing Analyzer)
├── Traits/                 → GrandstreamTrait (wrapper del servicio)
└── View/Components/        → Layouts Blade
```

## Flujo de Autenticación

1. Usuario hace login (`AuthController`)
2. Selecciona una central PBX (`PbxConnectionController@select`)
3. La central se guarda en sesión (`active_pbx_id`)
4. Los Global Scopes filtran automáticamente por esa central
5. El middleware `CheckPbxSelected` verifica que haya central seleccionada

## Modelos y Relaciones

```
PbxConnection (1) ──→ (N) Call
PbxConnection (1) ──→ (N) Extension
PbxConnection (1) ──→ (N) QueueCallDetail
PbxConnection (N) ←──→ (N) User          ← Tabla pivot: pbx_connection_user
User (roles y permisos granulares)
Setting (clave-valor para tarifas)
```

La relación muchos-a-muchos entre `User` y `PbxConnection` define qué centrales puede ver cada usuario. Los administradores no necesitan esta asignación ya que ven todas las centrales automáticamente.
